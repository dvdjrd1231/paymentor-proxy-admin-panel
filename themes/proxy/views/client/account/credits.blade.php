{{-- Add Funds — reference portal layout with the core Credits Livewire bindings preserved. --}}
@php
    $user = Auth::user();
    $currencyModel = \App\Models\Currency::where('code', $currency)->first();
    $credit = $user->credits()->where('currency_code', $currency)->first();
    $formatAmount = fn ($amount) => new \App\Classes\Price(['price' => $amount, 'currency' => $currencyModel]);
@endphp

<div class="wf-page">
    <div class="wf-layout">
        <div>
            <div class="wf-panel wf-panel--brand">
                <div class="wf-panel-heading">
                    <span>{{ __('account.add_credit') }}</span>
                    <span class="wf-chevron">▲</span>
                </div>
                <div class="wf-panel-body">
                    <p class="wf-rail-note">{{ __('theme.add_funds_help') }}</p>
                </div>
            </div>

            <div class="wf-panel wf-panel--brand">
                <div class="wf-panel-heading">
                    <span>{{ __('dashboard.credit_balance') }}</span>
                    <span class="wf-chevron">▲</span>
                </div>
                <div class="wf-panel-body" style="text-align:center">
                    <div class="wf-stat-num">{{ $credit?->formattedAmount ?? __('dashboard.no_credit') }}</div>
                    <a class="wf-btn wf-btn--sm wf-btn--block" href="{{ route('account.credits') }}" wire:navigate>
                        + {{ __('dashboard.add_funds') }}
                    </a>
                </div>
            </div>

            <div class="wf-panel wf-panel--brand">
                <div class="wf-panel-heading">
                    <span><span class="wf-head-icon"><x-ri-bank-card-fill /></span>{{ __('theme.billing') }}</span>
                    <span class="wf-chevron">▲</span>
                </div>
                <ul class="wf-list">
                    <li><a href="{{ route('invoices') }}" wire:navigate>{{ __('theme.my_invoices') }}</a></li>
                    @if (Route::has('account.payment-methods'))
                        <li><a href="{{ route('account.payment-methods') }}" wire:navigate>{{ __('theme.payment_methods') }}</a></li>
                    @endif
                    <li><a class="is-active" href="{{ route('account.credits') }}" wire:navigate>{{ __('dashboard.add_funds') }}</a></li>
                </ul>
            </div>
        </div>

        <div>
            <div class="wf-pagehead">
                <h1>{{ __('account.add_credit') }}</h1>
                <span>{{ __('theme.deposit_money_in_advance') }}</span>
            </div>
            <div class="wf-crumb">
                <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a>
                <span>/</span><a href="{{ route('dashboard') }}" wire:navigate>{{ __('theme.client_area') }}</a>
                <span>/</span>{{ __('account.add_credit') }}
            </div>

            <div class="wf-panel wf-panel--limits">
                <div class="wf-total-row"><span>{{ __('theme.minimum_deposit') }}</span><strong>{{ $formatAmount(config('settings.credits_minimum_deposit')) }}</strong></div>
                <div class="wf-total-row"><span>{{ __('theme.maximum_deposit') }}</span><strong>{{ $formatAmount(config('settings.credits_maximum_deposit')) }}</strong></div>
                <div class="wf-total-row"><span>{{ __('theme.maximum_balance') }}</span><strong>{{ $formatAmount(config('settings.credits_maximum_credit')) }}</strong></div>
            </div>

            <div class="wf-form-card wf-form-card--narrow">
                <form wire:submit.prevent="addCredit">
                    <div class="wf-field">
                        <label for="amount">{{ __('theme.amount_to_add') }}<span class="wf-req">*</span></label>
                        <input id="amount" class="wf-input" type="number" min="{{ config('settings.credits_minimum_deposit') }}"
                            max="{{ config('settings.credits_maximum_deposit') }}" step="0.01"
                            wire:model.live.debounce.250ms="amount" required>
                        @error('amount') <span class="wf-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="wf-field">
                        <label for="gateway">{{ __('product.payment_method') }}<span class="wf-req">*</span></label>
                        <select id="gateway" class="wf-select" wire:model.live="gateway" required>
                            @foreach ($gateways as $gatewayOption)
                                <option value="{{ $gatewayOption->id }}">{{ $gatewayOption->name }}</option>
                            @endforeach
                        </select>
                        @error('gateway') <span class="wf-error">{{ $message }}</span> @enderror
                    </div>

                    <button type="submit" class="wf-btn wf-btn--block">{{ __('account.add_credit') }}</button>
                </form>
                <div class="wf-form-card-foot">* {{ __('theme.deposits_non_refundable') }}</div>
            </div>
        </div>
    </div>
</div>
