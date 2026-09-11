<?php

namespace Paymenter\Extensions\Others\Quotes\Support;

use App\Helpers\NotificationHelper;
use App\Models\CronStat;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\Quotes\Models\Quote;
use Paymenter\Extensions\Others\Quotes\Models\QuoteItem;

/** The life of a quote: sent, answered, and — if accepted — turned into an invoice. */
class Quoting
{
    /** Reported on Automation Status under its own name. */
    public const STAT_KEY = 'quotes_expired';

    /** Send it: the customer can now see it and answer. */
    public static function send(Quote $quote): bool
    {
        if ($quote->status !== Quote::STATUS_DRAFT) {
            return false;
        }

        $quote->update(['status' => Quote::STATUS_SENT, 'sent_at' => now()]);

        // Best effort. A quote that is visible in the portal but whose email failed is a
        // quote the customer can still find and accept; one that was never sent because the
        // mail server was down is a sale lost to an outage.
        try {
            NotificationHelper::sendNotification('quote_sent', ['quote' => $quote], $quote->user);
        } catch (\Throwable $exception) {
            Log::warning('Quotes: could not email quote #' . $quote->id, [
                'exception' => $exception->getMessage(),
            ]);
        }

        return true;
    }

    /** The customer says yes — and the quote becomes an invoice. */
    public static function accept(Quote $quote): ?Invoice
    {
        if (!$quote->isOpen()) {
            return null;
        }

        return DB::transaction(function () use ($quote): Invoice {
            $invoice = Invoice::create([
                'user_id' => $quote->user_id,
                'status' => Invoice::STATUS_PENDING,
                'currency_code' => $quote->currency_code,
                'due_at' => now()->addDays((int) config('settings.cronjob_invoice', 7)),
            ]);

            foreach ($quote->items as $item) {
                $invoice->items()->create([
                    // Discounted, so the invoice charges what was quoted.
                    'price' => round((float) $item->price * (1 - (float) ($item->discount ?? 0) / 100), 2),
                    'quantity' => $item->quantity,
                    'description' => $item->description,
                ]);
            }

            $quote->update([
                'status' => Quote::STATUS_ACCEPTED,
                'accepted_at' => now(),
                'invoice_id' => $invoice->id,
            ]);

            return $invoice;
        });
    }

    /** The customer says no. Kept rather than deleted: a declined quote is a sales record. */
    public static function decline(Quote $quote): bool
    {
        if (!$quote->isOpen()) {
            return false;
        }

        $quote->update(['status' => Quote::STATUS_DECLINED, 'declined_at' => now()]);

        return true;
    }

    /** Copy a quote, back to draft. */
    public static function duplicate(Quote $quote): Quote
    {
        return DB::transaction(function () use ($quote): Quote {
            $copy = Quote::create([
                'user_id' => $quote->user_id,
                'subject' => $quote->subject,
                'currency_code' => $quote->currency_code,
                'status' => Quote::STATUS_DRAFT,
                'valid_until' => $quote->valid_until,
                'notes' => $quote->notes,
                'admin_id' => $quote->admin_id,
            ]);

            foreach ($quote->items as $item) {
                QuoteItem::create([
                    'quote_id' => $copy->id,
                    'description' => $item->description,
                    'price' => $item->price,
                    'quantity' => $item->quantity,
                    'sort' => $item->sort,
                ]);
            }

            return $copy;
        });
    }

    /**
     * Close quotes whose date has passed.
     *
     * @return array{expired: int, lines: array<int, string>}
     */
    public static function sweep(bool $dryRun = false): array
    {
        $due = Quote::query()
            ->where('status', Quote::STATUS_SENT)
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', now()->toDateString())
            ->with('user')
            ->get();

        $lines = [];

        foreach ($due as $quote) {
            $lines[] = 'expire quote #' . $quote->id . ' (' . $quote->subject . ') for '
                . ($quote->user?->email ?? 'deleted user')
                . ', valid until ' . $quote->valid_until->toDateString();

            if (!$dryRun) {
                $quote->update(['status' => Quote::STATUS_EXPIRED]);
            }
        }

        if (!$dryRun) {
            // Recorded even at zero, for the reason every task here records at zero: on the
            // status page, a task that writes nothing is indistinguishable from one that has
            // stopped running.
            CronStat::create([
                'key' => static::STAT_KEY,
                'value' => $due->count(),
                'date' => now()->toDateString(),
            ]);
        }

        return ['expired' => $due->count(), 'lines' => $lines];
    }
}
