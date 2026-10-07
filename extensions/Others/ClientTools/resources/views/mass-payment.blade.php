{{-- Mass Payment — settle everything outstanding in one step.

     Laid out as the reference lays it out (Leandro, 2026-10-07): one Description/Amount
     table, each invoice a banded heading over its own line items, then Sub Total and Total
     Due. No per-invoice tick boxes — the reference offers none, and the page exists to pay
     the lot. --}}
<div class="wf-page">
    <div class="wf-layout">
        <x-billing-rail active="mass-payment" />

        <div>
    <div class="wf-title">
        <h1>{{ __('clienttools.mass_payment') }}</h1>
        <span>{{ __('clienttools.mass_payment_subtitle') }}</span>
    </div>
    <hr class="wf-title-rule">

    <div class="wf-crumb">
        <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a>
        <span>/</span><a href="{{ route('dashboard') }}" wire:navigate>{{ __('theme.client_area') }}</a>
        <span>/</span>{{ __('clienttools.mass_payment') }}
    </div>

    @if ($invoices->isEmpty())
        <div class="wf-alert wf-alert--success" style="text-align:center">
            {{ __('clienttools.mass_nothing_due') }}
        </div>
    @else
        <div class="wf-table-wrap">
            <table class="wf-table wf-masspay">
                <thead>
                    <tr>
                        <th>{{ __('clienttools.mass_description') }}</th>
                        <th style="text-align:end">{{ __('clienttools.mass_amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoices as $invoice)
                        <tr class="wf-masspay-head">
                            <td colspan="2">{{ __('clienttools.mass_invoice_number', ['number' => $invoice->number ?? $invoice->id]) }}</td>
                        </tr>
                        @foreach ($invoice->items as $item)
                            <tr>
                                <td>{{ $item->description }}</td>
                                <td style="text-align:end">{{ $invoice->currency_code }} {{ number_format($item->price * $item->quantity, 2) }}</td>
                            </tr>
                        @endforeach
                    @endforeach

                    <tr class="wf-masspay-total">
                        <td>{{ __('invoices.subtotal') }}</td>
                        <td style="text-align:end">{{ number_format($selectedTotal, 2) }} {{ $currency }}</td>
                    </tr>
                    <tr class="wf-masspay-total">
                        <td>{{ __('clienttools.mass_total_due') }}</td>
                        <td style="text-align:end">{{ number_format($selectedTotal, 2) }} {{ $currency }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- The reference closes with a gateway box. Ours offers the account's credit, which
             is the one way this page can settle several invoices at once today — paying a
             batch through a gateway is not something Paymenter does, and inventing it here
             would be a payment flow with no backing. --}}
        <div class="wf-panel wf-masspay-pay">
            <div class="wf-panel-heading">{{ __('clienttools.mass_pay_heading') }}</div>
            <div class="wf-panel-body">
                <p>
                    {{ __('clienttools.mass_credit_balance', [
                        'amount' => $credit?->formatted_amount ?? (number_format(0, 2) . ' ' . $currency),
                    ]) }}
                </p>
                <p class="wf-list-sub">{{ __('clienttools.mass_credit_note') }}</p>

                <button type="button" class="wf-btn wf-btn--block" wire:click="payWithCredit"
                        wire:loading.attr="disabled" @disabled(!$credit || $credit->amount <= 0)>
                    <span wire:loading.remove wire:target="payWithCredit">{{ __('clienttools.mass_pay_with_credit') }}</span>
                    <span wire:loading wire:target="payWithCredit">…</span>
                </button>
            </div>
        </div>
    @endif
        </div>
    </div>
</div>
