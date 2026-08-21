<?php

namespace Paymenter\Extensions\Others\ClientTools\Livewire;

use App\Helpers\ExtensionHelper;
use App\Livewire\Component;
use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Mass Payment — settle several unpaid invoices in one action.
 *
 * The reference portal's version batches invoices into a single gateway payment.
 * Paymenter's gateways are driven one invoice at a time (`ExtensionHelper::pay()` takes
 * a single invoice), so batching through a gateway would mean inventing a synthetic
 * invoice and reconciling it afterwards — the kind of thing that goes wrong quietly with
 * real money. What is offered instead is the part that can be done atomically and
 * correctly: apply account credit across the selection, oldest first, and say plainly
 * what is left to pay individually.
 */
class MassPayment extends Component
{
    /** Invoice ids the customer has ticked. */
    public array $selected = [];

    public function mount(): void
    {
        // Pre-tick everything, which is what the reference does — the common case is
        // "pay all of it".
        $this->selected = $this->invoices()->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    /** This customer's unpaid invoices, oldest first. */
    public function invoices()
    {
        return Invoice::where('user_id', Auth::id())
            ->where('status', Invoice::STATUS_PENDING)
            ->orderBy('created_at')
            ->get();
    }

    public function toggleAll(): void
    {
        $all = $this->invoices()->pluck('id')->map(fn ($id) => (string) $id)->all();

        $this->selected = count($this->selected) === count($all) ? [] : $all;
    }

    /**
     * Apply credit to the ticked invoices, oldest first.
     *
     * Each invoice is settled inside its own locked transaction so a crash midway leaves
     * earlier invoices genuinely paid rather than half-applied, and re-running is safe.
     */
    public function payWithCredit()
    {
        $invoices = $this->invoices()->whereIn('id', $this->selected);

        if ($invoices->isEmpty()) {
            return $this->notify(__('clienttools.mass_none_selected'), 'error');
        }

        $paid = 0;
        $exhausted = false;

        foreach ($invoices as $invoice) {
            $settled = DB::transaction(function () use ($invoice) {
                $credit = Auth::user()->credits()
                    ->where('currency_code', $invoice->currency_code)
                    ->lockForUpdate()
                    ->first();

                // Only settle an invoice that credit covers in full. A partial payment
                // here would leave the customer with neither the credit nor a paid
                // invoice, which is worse than being told to pay it directly.
                if (!$credit || $credit->amount < $invoice->remaining) {
                    return false;
                }

                $credit->amount -= $invoice->remaining;
                $credit->save();

                ExtensionHelper::addPayment(
                    $invoice->id,
                    null,
                    amount: $invoice->remaining,
                    isCreditTransaction: true,
                );

                return true;
            });

            if ($settled) {
                $paid++;
            } else {
                $exhausted = true;
                break;
            }
        }

        if ($paid === 0) {
            return $this->notify(__('clienttools.mass_no_credit'), 'error');
        }

        $this->selected = [];

        return $this->notify($exhausted
            ? __('clienttools.mass_partial', ['count' => $paid])
            : __('clienttools.mass_paid', ['count' => $paid]));
    }

    public function render()
    {
        $invoices = $this->invoices();
        $selectedTotal = $invoices->whereIn('id', $this->selected)->sum('remaining');

        $currency = $invoices->first()?->currency_code ?? config('settings.default_currency');
        $credit = Auth::user()->credits()->where('currency_code', $currency)->first();

        return view('clienttools::mass-payment', [
            'invoices' => $invoices,
            'selectedTotal' => $selectedTotal,
            'credit' => $credit,
            'currency' => $currency,
        ]);
    }
}
