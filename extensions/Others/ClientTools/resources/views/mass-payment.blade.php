{{-- Mass Payment — tick the unpaid invoices to settle, see the running total, and put
     account credit against them in one action. --}}
<div class="wf-page">
    <div class="wf-title">
        <h1>{{ __('clienttools.mass_payment') }}</h1>
        <span>{{ __('clienttools.mass_payment_subtitle') }}</span>
    </div>
    <hr class="wf-title-rule">

    <div class="wf-crumb">
        <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a>
        <span>/</span>{{ __('clienttools.mass_payment') }}
    </div>

    @if ($invoices->isEmpty())
        <div class="wf-alert wf-alert--success" style="text-align:center">
            {{ __('clienttools.mass_nothing_due') }}
        </div>
    @else
        <div class="wf-panel">
            <div class="wf-panel-heading">
                <span>{{ __('clienttools.mass_unpaid_invoices') }}</span>
                <button type="button" class="wf-btn wf-btn--sm" wire:click="toggleAll">
                    {{ __('clienttools.mass_toggle_all') }}
                </button>
            </div>

            <div class="wf-table-wrap">
                <table class="wf-table">
                    <thead>
                        <tr>
                            <th style="width:2.5rem"></th>
                            <th>{{ __('invoices.invoice') }}</th>
                            <th>{{ __('invoices.due_date') }}</th>
                            <th style="text-align:end">{{ __('invoices.total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td>
                                    <label class="wf-check">
                                        <input type="checkbox" value="{{ $invoice->id }}" wire:model.live="selected">
                                        <span class="sr-only">#{{ $invoice->id }}</span>
                                    </label>
                                </td>
                                <td>
                                    <a href="{{ route('invoices.show', $invoice) }}" wire:navigate>#{{ $invoice->id }}</a>
                                </td>
                                <td>{{ $invoice->due_at?->format('d/m/Y') ?? '—' }}</td>
                                <td style="text-align:end">{{ $invoice->formatted_remaining ?? $invoice->remaining }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="wf-panel-foot">
                <div class="wf-total-row wf-total-row--grand">
                    <span>{{ __('clienttools.mass_selected_total') }}</span>
                    <span>{{ number_format($selectedTotal, 2) }} {{ $currency }}</span>
                </div>
            </div>
        </div>

        <div class="wf-panel wf-panel--brand">
            <div class="wf-panel-heading">
                <span><span class="wf-head-icon"><x-ri-wallet-3-fill /></span>{{ __('dashboard.credit_balance') }}</span>
            </div>
            <div class="wf-panel-body">
                <p>
                    {{ __('clienttools.mass_credit_balance', [
                        'amount' => $credit?->formatted_amount ?? (number_format(0, 2) . ' ' . $currency),
                    ]) }}
                </p>
                <p class="wf-list-sub">{{ __('clienttools.mass_credit_note') }}</p>

                <div class="wf-actions">
                    <button type="button" class="wf-btn" wire:click="payWithCredit"
                            wire:loading.attr="disabled" @disabled(!$credit || $credit->amount <= 0)>
                        {{ __('clienttools.mass_pay_with_credit') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
