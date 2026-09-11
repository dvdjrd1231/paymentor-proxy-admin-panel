<?php

namespace Paymenter\Extensions\Others\BillableItems\Support;

use App\Models\CronStat;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\BillableItems\Models\BillableItem;

/** Putting billable items onto invoices. */
class Items
{
    /** Reported on Automation Status under the reference's own task name. */
    public const STAT_KEY = 'billable_items_invoiced';

    /**
     * Add every waiting item for this customer to an invoice, and return it.
     *
     * @param  Collection<int, BillableItem>  $items
     */
    public static function invoice(User $user, $items, ?Invoice $invoice = null): ?Invoice
    {
        $items = $items->filter(fn (BillableItem $item): bool => !$item->isInvoiced());

        if ($items->isEmpty()) {
            return $invoice;
        }

        return DB::transaction(function () use ($user, $items, $invoice): Invoice {
            // One invoice per currency is not a choice: Paymenter stores no exchange rate, so
            // a line in BRL and a line in USD on one invoice would produce a total that is
            // neither. Callers group by currency before they get here; this guards it anyway.
            $currency = $items->first()->currency_code;

            $invoice ??= Invoice::create([
                'user_id' => $user->id,
                'status' => Invoice::STATUS_PENDING,
                'currency_code' => $currency,
                'due_at' => now()->addDays((int) config('settings.cronjob_invoice', 7)),
            ]);

            foreach ($items as $item) {
                if ($item->currency_code !== $invoice->currency_code) {
                    continue;
                }

                $invoice->items()->create([
                    'reference_id' => $item->service_id,
                    'reference_type' => $item->service_id ? Service::class : null,
                    'price' => $item->amount,
                    'quantity' => $item->quantity,
                    'description' => $item->line(),
                ]);

                // Written with the invoice item, in the same transaction: an item marked
                // invoiced with no line on an invoice is money quietly given away, and the
                // other way round is money charged twice.
                $item->update([
                    'invoice_id' => $invoice->id,
                    'invoiced_at' => now(),
                ]);

                static::repeat($item);
            }

            return $invoice;
        });
    }

    /** A recurring item, queued again for its next period. */
    private static function repeat(BillableItem $item): void
    {
        if (blank($item->recur_every)) {
            return;
        }

        $next = match ($item->recur_every) {
            'week' => now()->addWeek(),
            'month' => now()->addMonth(),
            'quarter' => now()->addMonths(3),
            'year' => now()->addYear(),
            default => null,
        };

        if ($next === null) {
            return;
        }

        BillableItem::create([
            'user_id' => $item->user_id,
            'service_id' => $item->service_id,
            'description' => $item->description,
            'amount' => $item->amount,
            'quantity' => $item->quantity,
            'currency_code' => $item->currency_code,
            'invoice_action' => $item->invoice_action,
            'recur_every' => $item->recur_every,
            'next_due_at' => $next->toDateString(),
            'admin_id' => $item->admin_id,
        ]);
    }

    /**
     * One pass: raise invoices for everything that should not wait any longer.
     *
     * @return array{invoiced: int, lines: array<int, string>}
     */
    public static function sweep(bool $dryRun = false): array
    {
        $stale = now()->subDays((int) config('settings.cronjob_invoice', 7) * 2);

        $due = BillableItem::query()
            ->whereNull('invoiced_at')
            ->where(function ($query) use ($stale): void {
                $query->where('invoice_action', BillableItem::ACTION_IMMEDIATELY)
                    ->orWhere(function ($waited) use ($stale): void {
                        $waited->where('invoice_action', BillableItem::ACTION_NEXT_INVOICE)
                            ->where('created_at', '<', $stale);
                    });
            })
            ->where(function ($query): void {
                $query->whereNull('next_due_at')->orWhere('next_due_at', '<=', now()->toDateString());
            })
            ->with('user')
            ->get();

        $lines = [];
        $invoiced = 0;

        // Grouped by customer *and* currency: one invoice each, for the reason in invoice().
        foreach ($due->groupBy(['user_id', 'currency_code']) as $userId => $byCurrency) {
            foreach ($byCurrency as $currency => $items) {
                $user = $items->first()->user;

                if (!$user) {
                    continue;
                }

                $lines[] = sprintf(
                    'invoice %d %s for %s (%s %s)',
                    $items->count(),
                    str('item')->plural($items->count()),
                    $user->email,
                    number_format($items->sum(fn (BillableItem $i): float => $i->total()), 2),
                    $currency,
                );

                if (!$dryRun) {
                    try {
                        static::invoice($user, $items);
                        $invoiced += $items->count();
                    } catch (\Throwable $exception) {
                        Log::error('BillableItems: could not invoice for user #' . $userId, [
                            'exception' => $exception->getMessage(),
                        ]);
                    }
                }
            }
        }

        if (!$dryRun) {
            CronStat::create([
                'key' => static::STAT_KEY,
                'value' => $invoiced,
                'date' => now()->toDateString(),
            ]);
        }

        return ['invoiced' => $invoiced, 'lines' => $lines];
    }

    /** Ride along on an invoice that was just created for something else. */
    public static function attachToNewInvoice(Invoice $invoice): void
    {
        if ($invoice->status !== Invoice::STATUS_PENDING) {
            return;
        }

        $waiting = BillableItem::query()
            ->whereNull('invoiced_at')
            ->where('user_id', $invoice->user_id)
            ->where('currency_code', $invoice->currency_code)
            ->where('invoice_action', BillableItem::ACTION_NEXT_INVOICE)
            ->where(function ($query): void {
                $query->whereNull('next_due_at')->orWhere('next_due_at', '<=', now()->toDateString());
            })
            ->get();

        if ($waiting->isEmpty()) {
            return;
        }

        try {
            static::invoice($invoice->user, $waiting, $invoice);
        } catch (\Throwable $exception) {
            // The invoice this rode along on is already valid and already the customer's.
            // A failure here must never take it down with us; the item stays uninvoiced and
            // the sweeper picks it up.
            Log::error('BillableItems: could not attach to invoice #' . $invoice->id, [
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
