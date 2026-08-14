<?php

namespace App\Livewire\Client;

use App\Attributes\DisabledIf;
use App\Helpers\ExtensionHelper;
use App\Livewire\Component;
use App\Models\Credit;
use App\Models\Gateway;
use App\Models\Invoice;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;

#[DisabledIf('credits_enabled', reverse: true)]
class Credits extends Component
{
    #[Validate('required|exists:currencies,code')]
    public $currency;

    public $amount;

    #[Locked]
    public $gateways = [];

    public $gateway;

    public function mount()
    {
        $this->amount = config('settings.credits_minimum_deposit');
        $this->currency = session('currency', config('settings.default_currency'));
        $this->gateways = ExtensionHelper::getCheckoutGateways($this->amount, $this->currency, 'credits');
        if (count($this->gateways) > 0 && !array_search($this->gateway, array_column($this->gateways, 'id')) !== false) {
            $this->gateway = $this->gateways[0]->id;
        }
    }

    public function updated($variable)
    {
        if ($variable === 'amount' || $variable === 'currency') {
            $this->gateways = ExtensionHelper::getCheckoutGateways($this->amount, $this->currency, 'credits');
            if (count($this->gateways) > 0 && !array_search($this->gateway, array_column($this->gateways, 'id')) !== false) {
                $this->gateway = $this->gateways[0]->id;
            }
        }
    }

    public function addCredit()
    {
        $this->validate([
            'currency' => 'required|exists:currencies,code',
            'amount' => 'required|numeric|min:' . config('settings.credits_minimum_deposit') . '|max:' . config('settings.credits_maximum_deposit'),
            'gateway' => 'required|in:' . implode(',', array_column($this->gateways, 'id')),
        ]);

        // Create invoice
        DB::beginTransaction();

        try {
            // Lock the user's invoices and credits
            Auth::user()->invoices()->lockForUpdate()->get();
            $credits = Auth::user()->credits()->where('currency_code', $this->currency)->lockForUpdate()->get();

            // Check if user has credits in this currency
            if ($credits->isNotEmpty()) {
                // Check if the current credits + the new credits exceed the maximum credits allowed
                if ($credits->sum('amount') + $this->amount > config('settings.credits_maximum_credit')) {
                    $this->notify('You cannot exceed the maximum credits allowed.', 'error');

                    return;
                }
            }

            // Only one credit deposit may be outstanding at a time, otherwise a customer
            // can stack unpaid deposit invoices. Rather than refusing with an error and
            // leaving them on a form they cannot submit, treat it as a notice and take
            // them to the invoice that is already waiting so they can pay it now.
            // See docs/CORE-TOUCHPOINTS.md #7.
            $pendingDeposit = Auth::user()->invoices()->where('status', Invoice::STATUS_PENDING)->whereHas('items', function ($query) {
                $query->where('reference_type', Credit::class);
            })->latest('id')->first();

            if ($pendingDeposit) {
                // Nothing has been written yet, but the transaction is open — close it
                // before redirecting so the connection is not left mid-transaction.
                DB::rollBack();

                $this->notify(__('account.credit_pending_invoice', [
                    'number' => $pendingDeposit->number ?? $pendingDeposit->id,
                    'amount' => $pendingDeposit->formattedRemaining,
                ]), 'info');

                // `?pay` opens the payment modal on arrival (Invoices\Show::$showPayModal
                // is URL-bound), so the customer lands directly on the payment step.
                return $this->redirect(route('invoices.show', $pendingDeposit) . '?pay', true);
            }

            $invoice = Invoice::create([
                'user_id' => Auth::id(),
                'currency_code' => $this->currency,
                'due_at' => now(),
            ]);

            $invoice->items()->create([
                'description' => __('account.credit_deposit', ['currency' => $this->currency]),
                'quantity' => 1,
                'price' => $this->amount,
                'reference_type' => Credit::class,
            ]);

            DB::commit();

            Session::put(['gateway' => $this->gateway]);

            // Redirect to the invoices page and pay the invoice
            if ($this->gateway) {
                // The deposit invoice is already committed above, so a gateway refusing
                // the hand-off must not blow up as a bare 500 — that loses the customer
                // with no way back. Send them to the invoice they just created so they
                // can retry or pick another method. See docs/CORE-TOUCHPOINTS.md #6.
                try {
                    $pay = ExtensionHelper::pay(Gateway::where('id', $this->gateway)->first(), $invoice->fresh());
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::channel('stack')->error('[Credits] gateway refused the deposit', [
                        'invoice' => $invoice->id,
                        'gateway' => $this->gateway,
                        'error' => $e->getMessage(),
                    ]);

                    $this->notify(__('invoices.gateway_error', ['error' => $this->gatewayErrorMessage($e)]), 'error');

                    return $this->redirect(route('invoices.show', $invoice), true);
                }

                if (is_string($pay)) {
                    return $this->redirect($pay);
                }
            }

            return $this->redirect(route('invoices.show', $invoice) . '?gateway=' . $this->gateway . '&pay', true);
        } catch (Exception $e) {
            // Rollback the transaction
            DB::rollBack();
            // Return error message
            throw $e;
        }
    }

    /**
     * Turn a gateway exception into something a customer can act on. Mirrors the same
     * mapping used on the invoice page so both payment entry points read alike; the raw
     * exception (which can carry API detail) stays in the log, not on screen.
     * See docs/CORE-TOUCHPOINTS.md #6.
     */
    private function gatewayErrorMessage(\Throwable $e): string
    {
        $raw = $e->getMessage();

        if (str_contains($raw, 'amount_too_small')) {
            return __('invoices.amount_too_small');
        }

        if (str_contains($raw, 'amount_too_large')) {
            return __('invoices.amount_too_large');
        }

        // Bad or missing credentials are an operator problem, not something the customer
        // can fix by trying again — say so plainly instead of leaking the API's wording.
        if (preg_match('/invalid.{0,20}(api|public|secret|merchant|key|uuid)/i', $raw)) {
            return __('invoices.gateway_misconfigured');
        }

        if (preg_match('/"message"\s*:\s*"([^"]{5,200})"/', $raw, $m)) {
            return $m[1];
        }

        return __('invoices.gateway_unavailable');
    }

    public function render()
    {
        return view('client.account.credits')->layoutData([
            'sidebar' => true,
            'title' => 'Add Credits',
        ]);
    }
}
