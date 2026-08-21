<?php

namespace Paymenter\Extensions\Others\ClientTools\Livewire;

use App\Helpers\ExtensionHelper;
use App\Livewire\Component;
use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;

/**
 * The reference portal's "Apply Credit" panel on an invoice.
 *
 * Core can already pay an invoice from credit, but only all-or-nothing: its
 * `payWithCredit()` spends the whole balance up to the invoice total. The reference lets
 * the customer choose how much to put against this invoice and keep the rest, which is
 * what this adds.
 *
 * The invoice is `#[Locked]` so the id cannot be swapped client-side, and ownership is
 * re-checked on every apply rather than trusting the mounted model.
 */
class ApplyCredit extends Component
{
    #[Locked]
    public Invoice $invoice;

    /** Amount the customer wants to put against this invoice. */
    public $amount = null;

    public function mount(Invoice $invoice): void
    {
        $this->invoice = $invoice;

        // Default to the most that can usefully be applied — the smaller of the balance
        // and what is still owed — so the common "use it all" case is one click.
        $this->amount = $this->maxApplicable();
    }

    /** This customer's credit in the invoice's currency, or null. */
    public function credit()
    {
        return Auth::user()->credits()
            ->where('currency_code', $this->invoice->currency_code)
            ->first();
    }

    private function maxApplicable(): float
    {
        return round(min((float) ($this->credit()->amount ?? 0), (float) $this->invoice->remaining), 2);
    }

    public function apply()
    {
        if ($this->invoice->user_id !== Auth::id()) {
            abort(403);
        }

        if ($this->invoice->status !== Invoice::STATUS_PENDING) {
            return $this->notify(__('clienttools.credit_not_payable'), 'error');
        }

        $max = $this->maxApplicable();

        if ($max <= 0) {
            return $this->notify(__('clienttools.credit_none'), 'error');
        }

        $this->validate(
            ['amount' => "required|numeric|min:0.01|max:$max"],
            ['amount.max' => __('clienttools.credit_too_much', ['max' => number_format($max, 2)])],
        );

        DB::transaction(function () {
            // Re-read both sides under a lock: between rendering the form and pressing
            // Apply the balance may have been spent elsewhere, or the invoice paid.
            $credit = Auth::user()->credits()
                ->where('currency_code', $this->invoice->currency_code)
                ->lockForUpdate()
                ->first();

            $invoice = Invoice::whereKey($this->invoice->id)->lockForUpdate()->first();

            // Clamp to what is still genuinely available, so a stale form cannot spend
            // credit that is no longer there or overpay an invoice.
            $apply = round(min((float) $this->amount, (float) $credit->amount, (float) $invoice->remaining), 2);

            if ($apply <= 0) {
                return;
            }

            $credit->amount -= $apply;
            $credit->save();

            ExtensionHelper::addPayment($invoice->id, null, amount: $apply, isCreditTransaction: true);
        });

        $this->invoice = $this->invoice->fresh();
        $this->amount = $this->maxApplicable();

        return $this->notify(__('clienttools.credit_applied'));
    }

    public function render()
    {
        return view('clienttools::apply-credit', [
            'credit' => $this->credit(),
            'max' => $this->maxApplicable(),
        ]);
    }
}
