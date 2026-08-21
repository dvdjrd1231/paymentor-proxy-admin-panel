{{-- Invoice detail — WHMCS "Six" style. Same Livewire bindings as the default theme
     (pay modal, payment polling, PDF download); only the chrome is restyled. --}}
<div class="wf-page">
    <div @if ($checkPayment) wire:poll.5s="checkPaymentStatus" @endif>
        @if ($this->pay || $showPayModal)
            @include('invoices.partials.payment-modal')
        @endif

        {{-- ── Header ──────────────────────────────────────────────────────
             The reference portal's invoice head: number on the left, and on the right a
             large status word, the due date under it, and the pay action. --}}
        <div class="wf-inv-head">
            <h1 class="wf-inv-number">
                {{ !$invoice->number && config('settings.invoice_proforma', false)
                    ? __('invoices.proforma_invoice', ['id' => $invoice->id])
                    : __('invoices.invoice', ['id' => $invoice->number]) }}
            </h1>

            <div class="wf-inv-status-block">
                @php
                    $isPaid = $invoice->status === 'paid';
                    $isCancelled = $invoice->status === 'cancelled';
                    $processing = $invoice->transactions
                        ->where('status', \App\Enums\InvoiceTransactionStatus::Processing)->count() > 0;
                @endphp

                <div @class([
                    'wf-inv-status',
                    'wf-inv-status--paid' => $isPaid,
                    'wf-inv-status--unpaid' => !$isPaid && !$isCancelled && !$processing,
                    'wf-inv-status--other' => $isCancelled || $processing,
                ])>
                    @if ($isPaid)
                        {{ __('invoices.paid') }}
                    @elseif ($processing)
                        {{ __('invoices.payment_processing') }}
                    @elseif ($isCancelled)
                        {{ ucfirst($invoice->status) }}
                    @else
                        {{ __('invoices.unpaid') }}
                    @endif
                </div>

                @if ($invoice->due_at)
                    <div class="wf-inv-due">
                        {{ __('invoices.due_date') }}: {{ $invoice->due_at->translatedFormat('l, F jS, Y') }}
                    </div>
                @endif

                <div class="wf-actions wf-actions--center" style="margin-top:.6rem">
                    @if ($invoice->status === 'pending' && !$checkPayment)
                        {{-- Green, as on the reference, where the brand colour is reserved for
                             navigation and the pay action is the one green control. --}}
                        <button type="button" class="wf-btn wf-btn--pay" wire:click="processPayment"
                            wire:loading.attr="disabled" wire:target="processPayment">
                            <span wire:loading.remove wire:target="processPayment">{{ __('invoices.pay_now') }}</span>
                            <span wire:loading wire:target="processPayment">…</span>
                        </button>
                    @endif
                    <button type="button" class="wf-btn wf-btn--ghost wf-btn--sm" wire:click="downloadPDF">
                        <span wire:loading.remove wire:target="downloadPDF">{{ __('invoices.download_pdf') }}</span>
                        <span wire:loading wire:target="downloadPDF">…</span>
                    </button>
                </div>
            </div>
        </div>

        <hr class="wf-title-rule">

        {{-- The duplicate-payment warning is the only thing from the old status bar that is
             not carried by the new header, so it stays as a standalone notice. --}}
        @if ($invoice->transactions->where('status', \App\Enums\InvoiceTransactionStatus::Processing)->count() > 0 && !$checkPayment)
            <div class="wf-alert wf-alert--notice">{{ __('invoices.duplicate_payment') }}</div>
        @endif

        {{-- ── Parties + dates ─────────────────────────────────────────── --}}
        <div class="wf-grid">
            <div class="wf-panel">
                <div class="wf-panel-heading">{{ __('invoices.issued_to') }}</div>
                <div class="wf-panel-body">
                    <p>{{ $invoice->user_name }}</p>
                    @foreach($invoice->user_properties as $property)
                        <p>{{ $property }}</p>
                    @endforeach
                </div>
            </div>

            <div class="wf-panel">
                <div class="wf-panel-heading">{{ __('invoices.bill_to') }}</div>
                <div class="wf-panel-body">
                    <p>{!! nl2br(e($invoice->bill_to)) !!}</p>
                </div>
            </div>
        </div>

        {{-- Invoice Date on the left, Payment Method on the right — the reference's second
             row. The select is bound to the same `selectedMethod` the pay modal uses, so
             choosing here and pressing Pay Now goes through core's existing
             processPayment() with no parallel payment path. --}}
        <div class="wf-grid wf-inv-meta">
            <div>
                <div class="wf-inv-label">
                    {{ !$invoice->number && config('settings.invoice_proforma', false) ? __('invoices.proforma_invoice_date') : __('invoices.invoice_date') }}
                </div>
                <p>{{ $invoice->created_at->translatedFormat('l, F jS, Y') }}</p>
                @if ($invoice->number)
                    <div class="wf-inv-label">{{ __('invoices.invoice_no') }}</div>
                    <p>{{ $invoice->number }}</p>
                @endif
            </div>

            @if ($invoice->status === 'pending')
                <div class="wf-inv-meta-right">
                    <div class="wf-inv-label">{{ __('invoices.payment_method') }}</div>
                    <select class="wf-select" wire:model.live="selectedMethod">
                        <option value="">{{ __('invoices.select_payment_method') }}</option>
                        @if (config('settings.credits_enabled') && ($credit = Auth::user()->credits()->where('currency_code', $invoice->currency_code)->first()) && $credit->amount > 0)
                            <option value="credit">{{ __('invoices.credits') }} ({{ $credit->formatted_amount }})</option>
                        @endif
                        @foreach ($this->gateways() as $gateway)
                            <option value="gateway-{{ $gateway->id }}">{{ $gateway->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>

        {{-- ── Apply Credit ────────────────────────────────────────────────
             The reference portal puts this between the invoice header and the line items.
             Provided by the Client Tools extension (core can only spend the whole balance),
             so it is rendered only when that extension is enabled, and it hides itself when
             there is no credit to apply or the invoice is already settled. --}}
        @if ($invoice->status === 'pending' && class_exists(\Paymenter\Extensions\Others\ClientTools\ClientTools::class))
            <livewire:clienttools.apply-credit :invoice="$invoice" :key="'credit-' . $invoice->id" />
        @endif

        {{-- ── Line items ──────────────────────────────────────────────── --}}
        <div class="wf-panel">
            <div class="wf-panel-heading">{{ __('invoices.invoice', ['id' => $invoice->number ?? $invoice->id]) }}</div>
            <div class="wf-table-wrap">
                <table class="wf-table">
                    <thead>
                        <tr>
                            <th>{{ __('invoices.item') }}</th>
                            <th>{{ __('invoices.price') }}</th>
                            <th>{{ __('invoices.quantity') }}</th>
                            <th style="text-align:end">{{ __('invoices.total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->items as $item)
                            <tr>
                                <td>
                                    @if(in_array($item->reference_type, ['App\Models\Service', 'App\Models\ServiceUpgrade']))
                                        <a href="{{ route('services.show', $item->reference_type == 'App\Models\Service' ? $item->reference_id : $item->reference->service_id) }}">
                                            {{ $item->description }}
                                        </a>
                                    @else
                                        {{ $item->description }}
                                    @endif
                                </td>
                                <td>{{ $item->formattedPrice }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td style="text-align:end"><strong>{{ $item->formattedTotal }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="wf-panel-footer">
                <div class="wf-totals">
                    {{-- Sub Total is always shown, as on the reference. It used to be inside
                         the tax condition, so a zero-rated invoice jumped straight from the
                         line items to Credit and Total with nothing to add up. --}}
                    <div class="wf-total-row">
                        <span>{{ __('invoices.subtotal') }}</span>
                        <span>{{ $invoice->formattedTotal->format($invoice->formattedTotal->subtotal) }}</span>
                    </div>

                    @if ($invoice->formattedTotal->tax > 0)
                        <div class="wf-total-row">
                            <span>{{ $invoice->tax->name }} ({{ $invoice->tax->rate }}%)</span>
                            <span>{{ $invoice->formattedTotal->formatted->tax }}</span>
                        </div>
                    @endif
                    {{-- The reference shows what credit has already been put against the
                         invoice as its own line between Sub Total and Total, so the amount
                         still owed is explained rather than just smaller. --}}
                    @php
                        $creditApplied = $invoice->transactions
                            ->where('is_credit_transaction', true)
                            ->where('status', \App\Enums\InvoiceTransactionStatus::Succeeded)
                            ->sum('amount');
                    @endphp
                    <div class="wf-total-row">
                        <span>{{ __('invoices.credit_line') }}</span>
                        <span>{{ $invoice->formattedTotal->format($creditApplied) }}</span>
                    </div>

                    <div class="wf-total-row wf-total-row--grand">
                        <span>{{ __('invoices.total') }}</span>
                        <span>{{ $invoice->formattedTotal }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Transactions (per-gateway payment log) ──────────────────── --}}
        @if ($invoice->transactions->isNotEmpty())
            <div class="wf-panel">
                <div class="wf-panel-heading">{{ __('invoices.transactions') }}</div>
                <div class="wf-table-wrap">
                    <table class="wf-table">
                        <thead>
                            <tr>
                                <th>{{ __('invoices.date') }}</th>
                                <th>{{ __('invoices.transaction_id') }}</th>
                                <th>{{ __('invoices.gateway') }}</th>
                                <th>{{ __('invoices.amount') }}</th>
                                <th style="text-align:end">{{ __('invoices.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoice->transactions->sortByDesc('created_at') as $transaction)
                                <tr>
                                    <td>{{ $transaction->created_at->format('d M Y H:i') }}</td>
                                    <td class="wf-kv-value">{{ $transaction->transaction_id }}</td>
                                    <td>
                                        @if($transaction->is_credit_transaction)
                                            {{ __('invoices.paid_with_credits') }}
                                        @else
                                            {{ $transaction->gateway?->name }}
                                        @endif
                                    </td>
                                    <td>{{ $transaction->formattedAmount }}</td>
                                    <td style="text-align:end">
                                        @if($transaction->status == \App\Enums\InvoiceTransactionStatus::Succeeded)
                                            <span class="wf-label wf-label--success">{{ __('invoices.transaction_statuses.succeeded') }}</span>
                                        @elseif($transaction->status == \App\Enums\InvoiceTransactionStatus::Processing)
                                            <span class="wf-label wf-label--warning">{{ __('invoices.transaction_statuses.processing') }}</span>
                                        @elseif($transaction->status == \App\Enums\InvoiceTransactionStatus::Failed)
                                            <span class="wf-label wf-label--danger">{{ __('invoices.transaction_statuses.failed') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
