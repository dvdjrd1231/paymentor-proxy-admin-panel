{{--
    Client Area dashboard, rebuilt in the WHMCS "Six" design language:
    page header, stat tiles, then panels with headings/footers.

    Data matches the default theme exactly (same counts, same Livewire widgets),
    only the presentation changes.
--}}
@php
    $user = Auth::user();
    $activeServices = $user->services()->where('status', 'active')->count();
    $unpaidInvoices = $user->invoices()->where('status', 'pending')->count();
    $openTickets = $user->tickets()->where('status', '!=', 'closed')->count();
    $ticketsEnabled = !config('settings.tickets_disabled', false);
@endphp

<div class="wf-page">
    <div class="wf-pagehead">
        <h1>{{ __('navigation.dashboard') }}</h1>
        <p>{{ __('dashboard.dashboard_description') }}</p>
    </div>

    <div class="wf-stats">
        <div class="wf-stat">
            <div class="wf-stat-num">{{ $activeServices }}</div>
            <div class="wf-stat-label">{{ __('dashboard.active_services') }}</div>
        </div>
        <div class="wf-stat">
            <div class="wf-stat-num">{{ $unpaidInvoices }}</div>
            <div class="wf-stat-label">{{ __('dashboard.unpaid_invoices') }}</div>
        </div>
        @if ($ticketsEnabled)
            <div class="wf-stat">
                <div class="wf-stat-num">{{ $openTickets }}</div>
                <div class="wf-stat-label">{{ __('dashboard.open_tickets') }}</div>
            </div>
        @endif
    </div>

    <div class="wf-grid">
        <div>
            {{-- Active services --}}
            <div class="wf-panel">
                <div class="wf-panel-heading">
                    <span>{{ __('dashboard.active_services') }}</span>
                    <span class="wf-label wf-label--success">{{ $activeServices }}</span>
                </div>
                <livewire:services.widget status="active" />
                <div class="wf-panel-footer">
                    <a href="{{ route('services') }}" class="wf-btn wf-btn--sm" wire:navigate>
                        {{ __('dashboard.view_all') }}
                    </a>
                </div>
            </div>

            {{-- Open tickets --}}
            @if ($ticketsEnabled)
                <div class="wf-panel">
                    <div class="wf-panel-heading">
                        <span>{{ __('dashboard.open_tickets') }}</span>
                        <span class="wf-label wf-label--info">{{ $openTickets }}</span>
                    </div>
                    <livewire:tickets.widget />
                    <div class="wf-panel-footer" style="display:flex; gap:.5rem; flex-wrap:wrap">
                        <a href="{{ route('tickets') }}" class="wf-btn wf-btn--sm" wire:navigate>
                            {{ __('dashboard.view_all') }}
                        </a>
                        <a href="{{ route('tickets.create') }}" class="wf-btn wf-btn--sm wf-btn--ghost" wire:navigate>
                            {{ __('ticket.create_ticket') }}
                        </a>
                    </div>
                </div>
            @endif
        </div>

        <div>
            {{-- Unpaid invoices --}}
            <div class="wf-panel">
                <div class="wf-panel-heading">
                    <span>{{ __('dashboard.unpaid_invoices') }}</span>
                    <span class="wf-label {{ $unpaidInvoices > 0 ? 'wf-label--warning' : '' }}">{{ $unpaidInvoices }}</span>
                </div>
                <livewire:invoices.widget :limit="3" />
                <div class="wf-panel-footer">
                    <a href="{{ route('invoices') }}" class="wf-btn wf-btn--sm" wire:navigate>
                        {{ __('dashboard.view_all') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
