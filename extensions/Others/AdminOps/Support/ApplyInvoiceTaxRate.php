<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use App\Events\Invoice\Paid;
use App\Models\Property;
use Illuminate\Support\Facades\Event;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\EditInvoice;

/**
 * Carries the Options tab's Tax Rate onto the invoice's snapshot.
 *
 * Core builds the snapshot when an invoice is paid, taking the rate from the store's
 * settings for the client's country. That discards a rate staff set by hand on the
 * invoice, so this runs after core's listener on the same event and puts it back.
 * {@see EditInvoice::TAX_KEY}
 */
class ApplyInvoiceTaxRate
{
    public static function register(): void
    {
        // Registered from an extension's boot(), so it is appended after core's
        // CreateInvoiceSnapshotListener — the snapshot exists by the time this runs.
        Event::listen(Paid::class, function (Paid $event): void {
            $invoice = $event->invoice;

            $rates = Property::where('model_type', $invoice->getMorphClass())
                ->where('model_id', $invoice->id)
                ->whereIn('key', [EditInvoice::TAX_KEY, EditInvoice::TAX2_KEY])
                ->pluck('value', 'key');

            if ($rates->isEmpty() || !($snapshot = $invoice->fresh()->snapshot)) {
                return;
            }

            // Both levels as the one percentage core charges. {@see EditInvoice::TAX2_KEY}
            $snapshot->tax_rate = EditInvoice::combinedTaxRate(
                (float) ($rates[EditInvoice::TAX_KEY] ?? 0),
                (float) ($rates[EditInvoice::TAX2_KEY] ?? 0),
            );

            // Core leaves tax_name empty when the store has no rate for the client's
            // country, and Invoice::tax() reads the snapshot only when tax_name is set —
            // so a hand-entered rate needs a name or it would be silently ignored.
            if (!$snapshot->tax_name) {
                $snapshot->tax_name = 'Tax';
            }

            $snapshot->save();
        });
    }
}
