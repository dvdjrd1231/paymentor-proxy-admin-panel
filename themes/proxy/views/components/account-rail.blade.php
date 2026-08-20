@props(['active' => null])

<div>
    @if (Route::has('account.credits') && config('settings.credits_enabled'))
        <div class="wf-panel wf-panel--brand">
            <div class="wf-panel-heading"><span>{{ __('dashboard.add_funds') }}</span><span class="wf-chevron">▲</span></div>
            <div class="wf-panel-body"><p class="wf-rail-note">{{ __('theme.add_funds_help') }}</p></div>
        </div>
    @endif

    <div class="wf-panel wf-panel--brand">
        <div class="wf-panel-heading"><span>{{ __('dashboard.credit_balance') }}</span><span class="wf-chevron">▲</span></div>
        <div class="wf-panel-body" style="text-align:center">
            @php($accountCredit = Auth::user()->credits()->where('currency_code', session('currency', config('settings.default_currency')))->first())
            <div class="wf-stat-num">{{ $accountCredit?->formatted_amount ?? __('dashboard.no_credit') }}</div>
            @if (Route::has('account.credits') && config('settings.credits_enabled'))
                <a class="wf-btn wf-btn--sm wf-btn--block" href="{{ route('account.credits') }}" wire:navigate>{{ __('dashboard.add_funds') }}</a>
            @endif
        </div>
    </div>

    <div class="wf-panel wf-panel--brand">
        <div class="wf-panel-heading"><span><span class="wf-head-icon"><x-ri-bank-card-fill /></span>{{ __('theme.billing') }}</span><span class="wf-chevron">▲</span></div>
        <ul class="wf-list">
            <li><a class="{{ $active === 'invoices' ? 'is-active' : '' }}" href="{{ route('invoices') }}" wire:navigate>{{ __('theme.my_invoices') }}</a></li>
            @if (Route::has('account.payment-methods'))
                <li><a class="{{ $active === 'payment-methods' ? 'is-active' : '' }}" href="{{ route('account.payment-methods') }}" wire:navigate>{{ __('theme.payment_methods') }}</a></li>
            @endif
            @if (Route::has('account.credits') && config('settings.credits_enabled'))
                <li><a class="{{ $active === 'credits' ? 'is-active' : '' }}" href="{{ route('account.credits') }}" wire:navigate>{{ __('dashboard.add_funds') }}</a></li>
            @endif
        </ul>
    </div>
</div>
