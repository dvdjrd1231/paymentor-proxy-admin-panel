{{-- Invoice detail — WHMCS "Six" style. Same Livewire bindings as the default theme
     (pay modal, payment polling, PDF download); only the chrome is restyled. --}}
<div class="wf-page">
    <div @if ($checkPayment) wire:poll.5s="checkPaymentStatus" @endif>
        @if ($this->pay || $showPayModal)
            @include('invoices.partials.payment-modal')
        @endif

        <div class="wf-pagehead">
            <h1>
                {{ !$invoice->number && config('settings.invoice_proforma', false)
                    ? __('invoices.proforma_invoice', ['id' => $invoice->id])
                    : __('invoices.invoice', ['id' => $invoice->number]) }}
            </h1>
        </div>

        {{-- ── Status + actions ────────────────────────────────────────── --}}
        <div class="wf-panel">
            <div class="wf-panel-body wf-invoice-bar">
                <div>
                    @if ($invoice->status == 'paid')
                        <span class="wf-label wf-label--success">{{ __('invoices.paid') }}</span>
                    @elseif ($invoice->status == 'pending')
                        @if($checkPayment || $invoice->transactions->where('status', \App\Enums\InvoiceTransactionStatus::Processing)->where('created_at', '>=', now()->subDays(1))->count() > 0)
                            <span class="wf-label wf-label--warning">{{ __('invoices.payment_processing') }}</span>
                        @elseif($invoice->transactions->where('status', \App\Enums\InvoiceTransactionStatus::Processing)->count() > 0)
                            <span class="wf-label wf-label--warning">{{ __('invoices.payment_processing') }}</span>
                            <span class="wf-section-note">{{ __('invoices.duplicate_payment') }}</span>
                        @else
                            <span class="wf-label wf-label--warning">{{ __('invoices.payment_pending') }}</span>
                        @endif
                    @else
                        <span class="wf-label">{{ ucfirst($invoice->status) }}</span>
                    @endif
                </div>

                <div class="wf-actions" style="margin-top:0">
                    <button type="button" class="wf-btn wf-btn--ghost wf-btn--sm" wire:click="downloadPDF">
                        <span wire:loading.remove wire:target="downloadPDF">{{ __('invoices.download_pdf') }}</span>
                        <span wire:loading wire:target="downloadPDF">…</span>
                    </button>

                    @if ($invoice->status == 'pending' && !$checkPayment)
                        <button type="button" class="wf-btn wf-btn--sm" wire:click="$set('showPayModal', true)"
                            wire:loading.attr="disabled" wire:target="$set('showPayModal')">
                            <span wire:loading.remove wire:target="pay">{{ __('invoices.pay') ?? 'Pay' }}</span>
                            <span wire:loading wire:target="pay">…</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>

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

        <div class="wf-panel">
            <table class="wf-table wf-table--kv">
                <tbody>
                    <tr>
                        <th>{{ !$invoice->number && config('settings.invoice_proforma', false) ? __('invoices.proforma_invoice_date') : __('invoices.invoice_date') }}</th>
                        <td>{{ $invoice->created_at->format('d M Y') }}</td>
                    </tr>
                    @if($invoice->due_at)
                        <tr>
                            <th>{{ __('invoices.due_date') }}</th>
                            <td>{{ $invoice->due_at->format('d M Y') }}</td>
                        </tr>
                    @endif
                    @if($invoice->number)
                        <tr>
                            <th>{{ __('invoices.invoice_no') }}</th>
                            <td>{{ $invoice->number }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

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
                    @if ($invoice->formattedTotal->tax > 0)
                        <div class="wf-total-row">
                            <span>{{ __('invoices.subtotal') }}</span>
                            <span>{{ $invoice->formattedTotal->format($invoice->formattedTotal->subtotal) }}</span>
                        </div>
                        <div class="wf-total-row">
                            <span>{{ $invoice->tax->name }} ({{ $invoice->tax->rate }}%)</span>
                            <span>{{ $invoice->formattedTotal->formatted->tax }}</span>
                        </div>
                    @endif
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
