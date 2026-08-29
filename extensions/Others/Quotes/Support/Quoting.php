<?php

namespace Paymenter\Extensions\Others\Quotes\Support;

use App\Helpers\NotificationHelper;
use App\Models\CronStat;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\Quotes\Models\Quote;
use Paymenter\Extensions\Others\Quotes\Models\QuoteItem;

/**
 * The life of a quote: sent, answered, and — if accepted — turned into an invoice.
 *
 * Every transition is one-way and guarded on the state it comes from. A quote that has been
 * accepted cannot be declined, an expired one cannot be accepted by a stale browser tab, and
 * none of them can happen twice. Those guards are the whole safety of the feature: an
 * accepted quote creates a real invoice, and creating two is creating a debt that does not
 * exist.
 */
class Quoting
{
    /** Reported on Automation Status under its own name. */
    public const STAT_KEY = 'quotes_expired';

    /**
     * Send it: the customer can now see it and answer.
     *
     * Only from draft. Re-sending a quote somebody has already answered would reopen a
     * closed conversation and, worse, let them accept something already invoiced.
     */
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

    /**
     * The customer says yes — and the quote becomes an invoice.
     *
     * The invoice is created in the same transaction as the acceptance, and the quote keeps
     * its id. Two presses of the button, or two tabs, therefore produce one invoice: the
     * second call finds a status that is no longer `sent` and does nothing.
     *
     * A **lapsed** quote is still acceptable here. A customer acting at one minute past
     * midnight on the closing day has done what was asked; losing that sale to a cron
     * schedule would be a self-inflicted wound. What closes a quote is the sweep, not the
     * clock — see {@see Quote::isLapsed()}.
     */
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

    /**
     * Copy a quote, back to draft.
     *
     * The one thing anybody wants from a quoting system after the first month: last
     * quarter's proposal with two numbers changed. Items are copied too, because a quote
     * without its lines is a title.
     */
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
     * Runs daily rather than every minute: a quote is a document with a *date* on it, not a
     * clock, and expiring one at 00:04 rather than 00:00 changes nothing for anybody. A quote
     * with no `valid_until` is never swept — an open-ended offer is a legitimate thing to
     * make, and expiring it because the column is empty would be inventing a deadline nobody
     * agreed.
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
