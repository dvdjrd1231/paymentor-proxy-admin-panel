{{--
    The payment method picker inside the pay modal.

    Same options and the same Livewire bindings as the default theme — account credit,
    saved cards/agreements, then one-time gateways — restyled onto this theme's design
    system. It used to be the default's markup with its Tailwind class names left in place;
    since this theme does not load the Tailwind bundle none of them resolved, so the whole
    picker rendered as a bare list of labels with no selected state and no hit target.

    The submit stays disabled until a method is chosen: `processPayment()` returns silently
    on a null `selectedMethod`, so an always-enabled button was a control that did nothing.
--}}
<div class="wf-pay">
    @php
        $credit = Auth::user()->credits()
            ->where('currency_code', $invoice->currency_code)
            ->where('amount', '>', 0)
            ->first();
        $itemHasCredit = $invoice->items()->where('reference_type', App\Models\Credit::class)->exists();
    @endphp

    {{-- Account credit. Hidden when the invoice is itself a credit top-up, which would
         otherwise offer to buy credit with credit. --}}
    @if ($credit && !$itemHasCredit)
        <div class="wf-pay-group">
            <div class="wf-pay-group-title">{{ __('invoices.pay_with_credits') }}</div>
            <button type="button" @class(['wf-pay-option', 'wf-pay-option--on' => $selectedMethod === 'credit'])
                wire:click="$set('selectedMethod', 'credit')"
                wire:loading.attr="disabled" wire:target="selectedMethod,processPayment">
                <span class="wf-pay-ico"><x-ri-copper-coin-line /></span>
                <span class="wf-pay-text">
                    <span class="wf-pay-name">{{ __('invoices.account_credits') }}</span>
                    <span class="wf-pay-sub">{{ __('invoices.available_credits', ['amount' => $credit->formattedAmount]) }}</span>
                </span>
                <span class="wf-pay-mark" aria-hidden="true"></span>
            </button>
        </div>
    @endif

    {{-- Cards and agreements already on file. --}}
    @if ($this->savedPaymentMethods->isNotEmpty())
        <div class="wf-pay-group">
            <div class="wf-pay-group-title">{{ __('account.saved_payment_methods') }}</div>
            @foreach ($this->savedPaymentMethods as $method)
                <button type="button" @class(['wf-pay-option', 'wf-pay-option--on' => $selectedMethod === $method->ulid])
                    wire:click="$set('selectedMethod', '{{ $method->ulid }}')"
                    wire:loading.attr="disabled" wire:target="selectedMethod,processPayment">
                    <span class="wf-pay-ico wf-pay-ico--brand">
                        @switch(strtolower($method->type))
                            @case('visa') <x-icons.visa /> @break
                            @case('mastercard') <x-icons.mastercard /> @break
                            @case('amex') <x-icons.amex /> @break
                            @case('american express') <x-icons.american-express /> @break
                            @case('discover') <x-icons.discover /> @break
                            @case('paypal') <x-icons.paypal /> @break
                            @case('sepa_debit') <x-icons.sepa /> @break
                            @case('ideal') <x-icons.ideal /> @break
                            @case('bancontact') <x-icons.bancontact /> @break
                            @case('sofort') <x-icons.sofort /> @break
                            @case('us_bank_account')
                            @case('bacs_debit')
                            @case('au_becs_debit') <x-icons.bank-debit /> @break
                            @default <x-ri-bank-card-line />
                        @endswitch
                    </span>
                    <span class="wf-pay-text">
                        <span class="wf-pay-name">{{ $method->name }}</span>
                        @if ($method->expiry)
                            <span class="wf-pay-sub">
                                {{ __('account.expires', ['date' => \Carbon\Carbon::parse($method->expiry)->format('m/Y')]) }}
                            </span>
                        @endif
                    </span>
                    <span class="wf-pay-mark" aria-hidden="true"></span>
                </button>
            @endforeach

            <a href="{{ route('account.payment-methods') }}" wire:navigate class="wf-btn wf-btn--ghost wf-btn--sm">
                {{ __('account.add_payment_method') }}
            </a>
        </div>
    @endif

    {{-- No method can take this amount: say so, with the figure, instead of showing an
         empty box. Gateways enforce their own floor in canUseGateway(), so an invoice
         below every minimum legitimately has nothing to offer. --}}
    @if (count($this->gateways) === 0 && $this->savedPaymentMethods->isEmpty())
        <div class="wf-alert wf-alert--danger">
            {{ __('invoices.no_methods_for_amount', ['amount' => $invoice->formattedTotal]) }}
        </div>
    @endif

    {{-- One-time gateways. Collapsed behind a disclosure only when there are saved methods
         to prefer; with nothing on file it is the whole picker and opens expanded. --}}
    @if (count($this->gateways) > 0)
        <div class="wf-pay-group" x-data="{ showOneTime: {{ $this->savedPaymentMethods->isEmpty() ? 'true' : 'false' }} }">
            @if ($this->savedPaymentMethods->isNotEmpty())
                <button type="button" class="wf-pay-toggle" @click="showOneTime = !showOneTime"
                    :aria-expanded="showOneTime ? 'true' : 'false'">
                    <span>{{ __('invoices.pay_with_one_time_method') }}</span>
                    <span class="wf-chevron" aria-hidden="true">&#9662;</span>
                </button>
            @endif

            <div x-show="showOneTime" x-transition.opacity>
                @foreach ($this->gateways as $method)
                    <button type="button" @class(['wf-pay-option', 'wf-pay-option--on' => $selectedMethod === 'gateway-' . $method->id])
                        wire:click="$set('selectedMethod', 'gateway-{{ $method->id }}')"
                        wire:loading.attr="disabled" wire:target="selectedMethod,processPayment">
                        <span class="wf-pay-ico wf-pay-ico--brand">
                            @if ($method->meta?->icon)
                                <img src="{{ $method->meta->icon }}" alt="">
                            @else
                                <x-ri-secure-payment-line />
                            @endif
                        </span>
                        <span class="wf-pay-text">
                            <span class="wf-pay-name">{{ $method->name }}</span>
                            <span class="wf-pay-sub">{{ __('invoices.one_time_payment') }}</span>
                        </span>
                        <span class="wf-pay-mark" aria-hidden="true"></span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Only a saved agreement can become the default for renewals; a one-time gateway and
         account credit have nothing to store. --}}
    @if ($selectedMethod && $selectedMethod !== 'credit' && !str_starts_with($selectedMethod, 'gateway-') && $this->recurringServices()->exists())
        <div class="wf-pay-recurring">
            <x-form.checkbox name="setAsDefault" wire:model.live="setAsDefault" :label="__('invoices.use_for_recurring')" />
        </div>
    @endif

    <button type="button" class="wf-btn wf-btn--pay wf-btn--block wf-btn--lg" wire:click="processPayment"
        wire:loading.attr="disabled" wire:target="processPayment" @disabled(!$selectedMethod)>
        <span role="status" class="wf-btn-spin" wire:loading wire:target="processPayment" aria-hidden="true"></span>
        <span wire:loading.remove wire:target="processPayment">
            @if (!$selectedMethod)
                {{ __('invoices.select_payment_method') }}
            @elseif ($selectedMethod === 'credit' && $credit && $credit->amount >= $invoice->formattedRemaining->total)
                {{ __('invoices.apply_credits_and_pay') }}
            @elseif ($selectedMethod === 'credit' && $credit)
                {{-- Key is plural: `apply_credits_and_continue`. The singular form does not
                     exist, so this button rendered the raw key to a customer paying partly
                     by credit. --}}
                {{ __('invoices.apply_credits_and_continue', ['amount' => $credit->formattedAmount]) }}
            @else
                {{ __('invoices.pay_now', ['amount' => $invoice->formattedRemaining]) }}
            @endif
        </span>
    </button>
</div>
