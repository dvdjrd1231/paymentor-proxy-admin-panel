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
                    <div class="wf-panel-heading">{{ __('dashboard.credit_balance') }}</div>
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
                <div class="wf-panel-heading">{{ __('dashboard.your_info') }}</div>
                <div class="wf-panel-body">
                    <div class="wf-list-title">{{ $user->name }}</div>
                    <span class="wf-list-sub">{{ $user->email }}</span>
                    <a class="wf-btn wf-btn--ghost wf-btn--sm wf-btn--block" style="margin-top:.75rem"
                       href="{{ route('account') }}" wire:navigate>
                        {{ __('dashboard.update_details') }}
                    </a>
                </div>
            </div>

            <div class="wf-panel">
                <div class="wf-panel-heading">{{ __('dashboard.shortcuts') }}</div>
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
            <div class="wf-pagehead">
                <h1>{{ __('dashboard.welcome_back', ['name' => $user->first_name]) }}</h1>
                <p>{{ __('dashboard.dashboard_description') }}</p>
            </div>

            <div class="wf-crumb">
                <a href="{{ route('home') }}" wire:navigate>{{ __('navigation.home') }}</a>
                <span>/</span>{{ __('navigation.dashboard') }}
            </div>

            <div class="wf-stats">
                <a class="wf-stat" href="{{ route('services') }}" wire:navigate>
                    <div class="wf-stat-head">
                        <span class="wf-stat-num">{{ $activeServices }}</span>
                        <span class="wf-stat-icon"><x-ri-archive-stack-fill /></span>
                    </div>
                    <div class="wf-stat-label">{{ __('dashboard.active_services') }}</div>
                </a>
                <a class="wf-stat" href="{{ route('invoices') }}" wire:navigate>
                    <div class="wf-stat-head">
                        <span class="wf-stat-num">{{ $unpaidInvoices }}</span>
                        <span class="wf-stat-icon"><x-ri-receipt-fill /></span>
                    </div>
                    <div class="wf-stat-label">{{ __('dashboard.unpaid_invoices') }}</div>
                </a>
                @if ($ticketsEnabled)
                    <a class="wf-stat" href="{{ route('tickets') }}" wire:navigate>
                        <div class="wf-stat-head">
                            <span class="wf-stat-num">{{ $openTickets }}</span>
                            <span class="wf-stat-icon"><x-ri-customer-service-fill /></span>
                        </div>
                        <div class="wf-stat-label">{{ __('dashboard.open_tickets') }}</div>
                    </a>
                @endif
                <a class="wf-stat" href="{{ route('invoices') }}" wire:navigate>
                    <div class="wf-stat-head">
                        <span class="wf-stat-num">{{ $totalInvoices }}</span>
                        <span class="wf-stat-icon"><x-ri-bank-card-fill /></span>
                    </div>
                    <div class="wf-stat-label">{{ __('navigation.invoices') }}</div>
                </a>
            </div>

            @if ($unpaidInvoices > 0)
                <div class="wf-alert wf-alert--info" style="margin-bottom:1.25rem">
                    {{ trans_choice('dashboard.unpaid_summary', $unpaidInvoices, ['count' => $unpaidInvoices]) }}
                    <a href="{{ route('invoices') }}" wire:navigate>{{ __('dashboard.view_invoices') }}</a>
                </div>
            @endif

            <div class="wf-panel">
                <div class="wf-panel-heading">
                    <span>{{ __('dashboard.active_services') }}</span>
                    <a class="wf-btn wf-btn--sm wf-btn--ghost" href="{{ route('services') }}" wire:navigate>
                        {{ __('dashboard.my_services') }}
                    </a>
                </div>
                <livewire:services.widget status="active" />
            </div>

            <div class="wf-grid">
                <div class="wf-panel">
                    <div class="wf-panel-heading">
                        <span>{{ __('navigation.invoices') }}</span>
                        <a class="wf-btn wf-btn--sm wf-btn--ghost" href="{{ route('invoices') }}" wire:navigate>
                            {{ __('common.button.view_all') }}
                        </a>
                    </div>
                    <livewire:invoices.widget :limit="3" />
                </div>

                @if ($ticketsEnabled)
                    <div class="wf-panel">
                        <div class="wf-panel-heading">
                            <span>{{ __('navigation.tickets') }}</span>
                            <a class="wf-btn wf-btn--sm wf-btn--ghost" href="{{ route('tickets.create') }}" wire:navigate>
                                {{ __('ticket.create_ticket') }}
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
