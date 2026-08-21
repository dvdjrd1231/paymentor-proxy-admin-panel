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
            {{-- A full @php block, not the inline @php(...) form. Blade compiled that
                 expression into an unterminated PHP open tag — no semicolon and no closing
                 tag — so every line after it was parsed as PHP and the component died with
                 "syntax error, unexpected token class". The inline form cannot cope with
                 calls nested this deep. --}}
            @php
                $accountCredit = Auth::user()
                    ->credits()
                    ->where('currency_code', session('currency', config('settings.default_currency')))
                    ->first();
            @endphp
            <div class="wf-stat-num">{{ $accountCredit?->formatted_amount ?? __('dashboard.no_credit') }}</div>
            @if (Route::has('account.credits') && config('settings.credits_enabled'))
                <a class="wf-btn wf-btn--sm wf-btn--block" href="{{ route('account.credits') }}" wire:navigate>{{ __('dashboard.add_funds') }}</a>
            @endif
        </div>
    </div>

    {{-- The reference portal's "Account" rail, in its order: Account Details, User
         Management, Payment Methods, Contacts, Email History. It replaces a Billing panel
         that duplicated the Billing menu in the bar above.

         Built from a list so an entry whose extension is disabled drops out instead of
         producing a dead link. --}}
    @php
        $accountRail = array_values(array_filter([
            ['key' => 'details', 'name' => __('theme.account_details'), 'route' => 'account'],
            ['key' => 'users', 'name' => __('clienttools.user_management'), 'route' => 'account.users'],
            ['key' => 'payment-methods', 'name' => __('theme.payment_methods'), 'route' => 'account.payment-methods'],
            ['key' => 'contacts', 'name' => __('clienttools.contacts'), 'route' => 'account.contacts'],
            ['key' => 'email-history', 'name' => __('clienttools.email_history'), 'route' => 'account.email-history'],
        ], fn ($i) => Route::has($i['route'])));
    @endphp

    <div class="wf-panel wf-panel--brand">
        <div class="wf-panel-heading">
            <span><span class="wf-head-icon"><x-ri-account-box-fill /></span>{{ __('navigation.account') }}</span>
            <span class="wf-chevron">▲</span>
        </div>
        <ul class="wf-list">
            @foreach ($accountRail as $item)
                <li>
                    <a class="{{ $active === $item['key'] ? 'is-active' : '' }}"
                       href="{{ route($item['route']) }}" wire:navigate>{{ $item['name'] }}</a>
                </li>
            @endforeach
        </ul>
    </div>
</div>
