<?php

namespace App\Livewire\Invoices;

use App\Classes\PDF;
use App\Enums\InvoiceTransactionStatus;
use App\Helpers\ExtensionHelper;
use App\Livewire\Component;
use App\Models\Gateway;
use App\Models\Invoice;
use App\Models\Service;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

class Show extends Component
{
    #[Locked]
    public Invoice $invoice;

    public $checkPayment = false;

    private $pay = null;

    #[Url('pay', except: false, nullable: true)]
    public $showPayModal = false;

    public $lastChecked = null;

    public $selectedMethod = null;

    public $setAsDefault = false;

    public function mount()
    {
        if (Request::has('checkPayment') && $this->invoice->status === 'pending') {
            $this->checkPayment = true;
        }
        if ($this->invoice->transactions()->where('status', InvoiceTransactionStatus::Processing)->exists()) {
            $this->checkPayment = true;
        }

        // Load relations
        $this->invoice->load('transactions', 'transactions.gateway', 'transactions.invoice');

        if ($this->showPayModal && $this->invoice->status !== 'pending') {
            $this->showPayModal = false;
        }
    }

    #[Computed]
    public function gateways()
    {
        return ExtensionHelper::getCheckoutGateways($this->invoice->total, $this->invoice->currency_code, 'invoice', $this->invoice->items);
    }

    #[Computed]
    public function paymentMethods()
    {
        return ExtensionHelper::getBillingAgreementGateways($this->invoice->currency_code);
    }

    #[Computed]
    public function savedPaymentMethods()
    {
        return Auth::user()->billingAgreements()->with('gateway')->get();
    }

    #[Computed]
    public function recurringServices()
    {
        return $this->invoice->items()
            ->where('reference_type', Service::class)
            ->whereNotNull('reference_id')
            ->whereHasMorph('reference', [Service::class], function ($query) {
                $query->whereHas('plan', function ($planQuery) {
                    $planQuery->whereNotIn('type', ['one-time', 'free']);
                });
            });
    }

    public function updatedShowPayModal($value)
    {
        if ($value && $this->invoice->status !== 'pending') {
            $this->showPayModal = false;
        }
    }

    public function processPayment()
    {
        if ($this->invoice->status !== 'pending') {
            return $this->notify(__('This invoice cannot be paid.'), 'error');
        }

        if (is_null($this->selectedMethod)) {
            return;
        }

        if ($this->selectedMethod === 'credit') {
            return $this->payWithCredit();
        }

        if (str_starts_with($this->selectedMethod, 'gateway-')) {
            $gatewayId = substr($this->selectedMethod, 8);

            return $this->payWithMethod($gatewayId);
        }

        if ($this->setAsDefault) {
            $invoiceItems = $this->recurringServices()->get();
            $agreement = Auth::user()->billingAgreements()->where('ulid', $this->selectedMethod)->first();

            foreach ($invoiceItems as $invoiceItem) {
                $service = $invoiceItem->reference;
                $service->update(['billing_agreement_id' => $agreement->id]);
            }

            if ($invoiceItems->count() > 0) {
                $this->notify('Default payment method has been updated for recurring services.', 'success');
            }
        }

        return $this->payWithSavedMethod($this->selectedMethod);
    }

    /**
     * Turn a gateway exception into something a customer can act on.
     *
     * Gateways answer with a JSON error body; showing the raw exception would leak a
     * stack trace and mean nothing to the buyer. The common refusals get a plain
     * explanation, and anything unrecognised falls back to a generic line — the detail
     * is in the log for the admin either way.
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

        // Gateway JSON bodies carry a human-readable message; prefer it when present.
        if (preg_match('/"message"\s*:\s*"([^"]{5,200})"/', $raw, $m)) {
            return $m[1];
        }

        return __('invoices.gateway_unavailable');
    }

    private function payWithMethod($methodId)
    {
        if (!in_array($methodId, array_column($this->gateways, 'id'))) {
            return $this->notify(__('Invalid payment method.'), 'error');
        }

        // Payment Method Fees (extensions/Others/PaymentFees) — apply gateway fee
        // server-side. See docs/CORE-TOUCHPOINTS.md #1. Guarded so core still runs if
        // the extension is removed; applyFee() is idempotent.
        if (class_exists(\Paymenter\Extensions\Others\PaymentFees\PaymentFees::class)) {
            $feeGateway = Gateway::find($methodId);
            if ($feeGateway) {
                \Paymenter\Extensions\Others\PaymentFees\PaymentFees::applyFee($feeGateway, $this->invoice);
                $this->invoice->refresh();
            }
        }

        // A gateway can legitimately refuse a payment — Stripe rejects anything under
        // 50c with `amount_too_small`, gateways reject unsupported currencies, and any
        // of them can be temporarily down. Without this the exception escapes as a bare
        // 500 "Server Error" page, which tells the customer nothing and loses the sale.
        // See docs/CORE-TOUCHPOINTS.md #5.
        try {
            $this->pay = ExtensionHelper::pay(Gateway::where('id', $methodId)->first(), $this->invoice);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::channel('stack')->error('[Checkout] gateway refused the payment', [
                'invoice' => $this->invoice->id,
                'gateway' => $methodId,
                'error' => $e->getMessage(),
            ]);

            return $this->notify(__('invoices.gateway_error', ['error' => $this->gatewayErrorMessage($e)]), 'error');
        }

        if (is_string($this->pay)) {
            $this->redirect($this->pay);
        }
    }

    private function payWithCredit()
    {
        DB::transaction(function () {
            $credit = Auth::user()->credits()->where('currency_code', $this->invoice->currency_code)->lockForUpdate()->first();
            if ($credit && $credit->amount > 0) {
                // Is it more credits or less credits than the total price?
                if ($credit->amount >= $this->invoice->remaining) {
                    $credit->amount -= $this->invoice->remaining;
                    $credit->save();
                    ExtensionHelper::addPayment($this->invoice->id, null, amount: $this->invoice->remaining, isCreditTransaction: true);

                    return $this->redirect(route('invoices.show', $this->invoice), true);
                } else {
                    ExtensionHelper::addPayment($this->invoice->id, null, amount: $credit->amount, isCreditTransaction: true);
                    $credit->amount = 0;
                    $credit->save();

                    $this->invoice = $this->invoice->fresh();
                    $this->notify(__('Part of the invoice has been paid with credits. Please pay the remaining amount'));
                }
            }
        });
    }

    private function payWithSavedMethod($agreementUlid)
    {
        $agreement = Auth::user()->billingAgreements()->where('ulid', $agreementUlid)->with('gateway')->first();
        if (!$agreement) {
            return $this->notify(__('Invalid payment method.'), 'error');
        }

        if (!in_array($agreement->gateway->id, array_column($this->paymentMethods, 'id'))) {
            return $this->notify(__('This payment method cannot be used for this invoice.'), 'error');
        }

        $success = ExtensionHelper::charge($agreement->gateway, $this->invoice, $agreement);

        if ($success === true) {
            $this->notify(__('Successfully charged the saved payment method.'), 'success');

            return $this->redirect(route('invoices.show', $this->invoice) . '?checkPayment', true);
        } else {
            return $this->notify(__('Could not process payment. Please try again or use a different payment method.'), 'error');
        }
    }

    public function exitPay()
    {
        $this->pay = null;
        // Dispatch event so extensions can do their thing
        $this->dispatch('invoice.payment.cancelled', $this->invoice);
        // Refresh invoice status
        $this->redirect(route('invoices.show', $this->invoice), true);
    }

    public function checkPaymentStatus()
    {
        $this->invoice->refresh();

        // Check for transactions that failed since lastChecked
        if ($this->lastChecked) {
            $failedSinceLastCheck = $this->invoice->transactions()
                ->where('status', InvoiceTransactionStatus::Failed)
                ->where('updated_at', '>', $this->lastChecked)
                ->exists();

            if ($failedSinceLastCheck) {
                $this->notify(__('Payment failed. Please try again or use a different payment method.'), 'error');
                $this->checkPayment = false;
                $this->lastChecked = null;

                return;
            }
        }

        // Update lastChecked to current time
        $this->lastChecked = now();

        // Check if invoice is paid
        if ($this->invoice->status === 'paid') {
            $this->notify(__('The invoice has been paid.'), 'success');
            $this->checkPayment = false;
            $this->lastChecked = null;
        }

        // Skip render if still checking
        if ($this->checkPayment) {
            return $this->skipRender();
        }
    }

    public function render()
    {
        return view('invoices.show')->layoutData([
            'title' => __('invoices.invoice', ['id' => $this->invoice->number]),
            'sidebar' => true,
        ]);
    }

    public function downloadPDF()
    {
        return response()->streamDownload(function () {
            echo PDF::generateInvoice($this->invoice)->stream();
        }, 'invoice-' . ($this->invoice->number ?? $this->invoice->id) . '.pdf');
    }
}
