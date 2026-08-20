{{--
    Client Area dashboard in the WHMCS "Six" layout the client uses on noxproxy:
    a left rail (credit balance, your info, shortcuts) beside the main column
    (welcome header, stat tiles, active services, unpaid invoices, tickets).

    Nothing here is hardcoded — every figure is queried live and every string comes
    from the language files, so the wording can be changed without touching Blade.
--}}
@php
    $user = Auth::user();

    $activeServices = $user->services()->where('status', 'active')->count();
    $unpaidInvoices = $user->invoices()->where('status', 'pending')->count();
    $openTickets = $user->tickets()->where('status', '!=', 'closed')->count();
    $totalInvoices = $user->invoices()->count();

    $ticketsEnabled = !config('settings.tickets_disabled', false);
    $creditsEnabled = (bool) config('settings.credits_enabled', false);

    // Balance in the currency the customer is browsing in, falling back to the default.
    $currency = session('currency', config('settings.default_currency'));
    $credit = $creditsEnabled
        ? $user->credits()->where('currency_code', $currency)->first()
        : null;
@endphp

<div class="wf-page">
    <div class="wf-layout">

        {{-- ── Left rail ───────────────────────────────────────────────── --}}
        <div>
            @if ($creditsEnabled)
                <div class="wf-panel wf-panel--brand">
                    <div class="wf-panel-heading">
                        <span><span class="wf-head-icon"><x-ri-wallet-3-fill /></span>{{ __('dashboard.credit_balance') }}</span>
                        <span class="wf-chevron">&#9650;</span>
                    </div>
                    <div class="wf-panel-body" style="text-align:center">
                        <div class="wf-stat-num">
                            {{ $credit?->formatted_amount ?? __('dashboard.no_credit') }}
                        </div>
                        <a class="wf-btn wf-btn--sm wf-btn--block" style="margin-top:.75rem"
                           href="{{ route('account.credits') }}" wire:navigate>
                            {{ __('dashboard.add_funds') }}
                        </a>
                    </div>
                </div>
            @endif

            <div class="wf-panel wf-panel--brand">
                <div class="wf-panel-heading">
                    <span><span class="wf-head-icon"><x-ri-user-3-fill /></span>{{ __('dashboard.your_info') }}</span>
                    <span class="wf-chevron">&#9650;</span>
                </div>
                <div class="wf-panel-body">
                    <div class="wf-list-title">{{ $user->name }}</div>
                    <span class="wf-list-sub">{{ $user->email }}</span>
                    <a class="wf-btn wf-btn--sm wf-btn--block" style="margin-top:.75rem"
                       href="{{ route('account') }}" wire:navigate>
                        {{ __('dashboard.update_details') }}
                    </a>
                </div>
            </div>

            <div class="wf-panel">
                <div class="wf-panel-heading">
                    <span><span class="wf-head-icon"><x-ri-links-fill /></span>{{ __('dashboard.shortcuts') }}</span>
                </div>
                <ul class="wf-list">
                    <li><a href="{{ route('home') }}" wire:navigate>{{ __('dashboard.order_new_services') }}</a></li>
                    <li><a href="{{ route('services') }}" wire:navigate>{{ __('navigation.services') }}</a></li>
                    <li><a href="{{ route('invoices') }}" wire:navigate>{{ __('navigation.invoices') }}</a></li>
                    @if ($ticketsEnabled)
                        <li><a href="{{ route('tickets.create') }}" wire:navigate>{{ __('ticket.create_ticket') }}</a></li>
                    @endif
                </ul>
            </div>
        </div>

        {{-- ── Main column ─────────────────────────────────────────────── --}}
        <div>
            {{-- No strapline under the heading: the reference goes straight from the
                 welcome to the breadcrumb. --}}
            <div class="wf-pagehead">
                <h1>{{ __('dashboard.welcome_back', ['name' => $user->first_name]) }}</h1>
            </div>

            <div class="wf-crumb">
                <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a>
                <span>/</span>{{ __('theme.client_area') }}
            </div>

            <div class="wf-stats">
                <a class="wf-stat" href="{{ route('services') }}" wire:navigate>
                    <div class="wf-stat-head">
                        <span class="wf-stat-num">{{ $activeServices }}</span>
                        <span class="wf-stat-icon"><x-ri-archive-stack-fill /></span>
                    </div>
                    <div class="wf-stat-label">{{ __('theme.services_short') }}</div>
                </a>
                <a class="wf-stat" href="{{ route('invoices') }}" wire:navigate>
                    <div class="wf-stat-head">
                        <span class="wf-stat-num">{{ $unpaidInvoices }}</span>
                        <span class="wf-stat-icon"><x-ri-receipt-fill /></span>
                    </div>
                    <div class="wf-stat-label">{{ __('theme.overdue_invoices') }}</div>
                </a>
                @if ($ticketsEnabled)
                    <a class="wf-stat" href="{{ route('tickets') }}" wire:navigate>
                        <div class="wf-stat-head">
                            <span class="wf-stat-num">{{ $openTickets }}</span>
                            <span class="wf-stat-icon"><x-ri-customer-service-fill /></span>
                        </div>
                        <div class="wf-stat-label">{{ __('theme.tickets_short') }}</div>
                    </a>
                @endif
                <a class="wf-stat" href="{{ route('invoices') }}" wire:navigate>
                    <div class="wf-stat-head">
                        <span class="wf-stat-num">{{ $totalInvoices }}</span>
                        <span class="wf-stat-icon"><x-ri-bank-card-fill /></span>
                    </div>
                    <div class="wf-stat-label">{{ __('theme.invoices_short') }}</div>
                </a>
            </div>

            @if (Route::has('knowledgebase.index'))
                {{-- Ask-a-question band under the counters, as on the reference. A plain GET
                     into the knowledgebase page (whose `q` is URL-bound), so it works
                     without JavaScript and the result list stays linkable. --}}
                <div class="wf-kbsearch">
                    <form action="{{ route('knowledgebase.index') }}" method="GET" role="search">
                        <div class="wf-input-ico" style="flex:1">
                            <span class="wf-ico"><x-ri-search-line /></span>
                            <input type="search" name="q" class="wf-input"
                                   placeholder="{{ __('theme.kb_search_placeholder') }}"
                                   aria-label="{{ __('theme.kb_search_placeholder') }}">
                        </div>
                        <button type="submit" class="wf-btn">{{ __('theme.search') }}</button>
                    </form>
                </div>
            @endif

            @if ($unpaidInvoices > 0)
                <div class="wf-alert wf-alert--info" style="margin-bottom:1.25rem">
                    {{ trans_choice('dashboard.unpaid_summary', $unpaidInvoices, ['count' => $unpaidInvoices]) }}
                    <a href="{{ route('invoices') }}" wire:navigate>{{ __('dashboard.view_invoices') }}</a>
                </div>
            @endif

            <div class="wf-panel wf-panel--top">
                <div class="wf-panel-heading">
                    <span><span class="wf-head-icon"><x-ri-archive-stack-fill /></span>{{ __('theme.active_products_services') }}</span>
                    <a class="wf-btn wf-btn--sm" href="{{ route('services') }}" wire:navigate>
                        &rarr; {{ __('dashboard.my_services') }}
                    </a>
                </div>
                <livewire:services.widget status="active" />
                <div class="wf-panel-foot">
                    <a href="{{ route('services') }}" wire:navigate>{{ __('theme.view_more') }}</a>
                </div>
            </div>

            <div class="wf-grid">
                <div class="wf-panel wf-panel--top">
                    <div class="wf-panel-heading">
                        <span><span class="wf-head-icon"><x-ri-receipt-fill /></span>{{ __('theme.overdue_invoices') }}</span>
                        <a class="wf-btn wf-btn--sm" href="{{ route('invoices') }}" wire:navigate>
                            &rarr; {{ __('theme.pay_now') }}
                        </a>
                    </div>
                    <livewire:invoices.widget :limit="3" />
                </div>

                @if ($ticketsEnabled)
                    <div class="wf-panel wf-panel--top">
                        <div class="wf-panel-heading">
                            <span><span class="wf-head-icon"><x-ri-customer-service-fill /></span>{{ __('theme.recent_tickets') }}</span>
                            <a class="wf-btn wf-btn--sm" href="{{ route('tickets.create') }}" wire:navigate>
                                + {{ __('theme.open_new_ticket') }}
                            </a>
                        </div>
                        <livewire:tickets.widget />
                    </div>
                @endif
            </div>

            {!! hook('pages.dashboard') !!}
        </div>
    </div>
</div>
