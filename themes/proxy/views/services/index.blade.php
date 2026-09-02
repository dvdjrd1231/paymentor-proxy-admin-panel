{{-- Services list — WHMCS "Six" style: a status-filter rail beside the table, matching the
     reference portal's "My Products & Services". Counts are queried live from the customer's
     own services, so the rail never claims a status the account does not have. --}}
@php
    $user = Auth::user();
    $statusCounts = [
        'active' => $user->services()->where('status', 'active')->count(),
        'pending' => $user->services()->where('status', 'pending')->count(),
        'suspended' => $user->services()->where('status', 'suspended')->count(),
        'terminated' => $user->services()->where('status', 'terminated')->count(),
        'cancelled' => $user->services()->where('status', 'cancelled')->count(),
    ];
    $creditsEnabled = (bool) config('settings.credits_enabled', false);
    $currency = session('currency', config('settings.default_currency'));
    $credit = $creditsEnabled ? $user->credits()->where('currency_code', $currency)->first() : null;

    // Issue #7: "in Paymenter, it simply functions as a service" — an addon *is* a service
    // row (that is what buys it the whole billing lifecycle for free), but nothing on the
    // one list a client actually looks at ever said so. core's own Services\Index knows
    // nothing of ServiceAddon and cannot be taught without editing it, so the tie is read
    // here instead, scoped to the page of services actually being shown.
    $addonParents = class_exists(\Paymenter\Extensions\Others\AdminOps\Models\ServiceAddon::class)
        ? \Paymenter\Extensions\Others\AdminOps\Models\ServiceAddon::whereIn('service_id', $services->pluck('id'))
            ->with('parent.product')->get()->keyBy('service_id')
        : collect();
@endphp

<div class="wf-page">
    <div class="wf-pagehead">
        <h1>{{ __('navigation.services') }}</h1>
    </div>

    <div class="wf-crumb">
        <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a>
        <span>/</span><a href="{{ route('dashboard') }}" wire:navigate>{{ __('theme.client_area') }}</a>
        <span>/</span>{{ __('navigation.services') }}
    </div>

    <div class="wf-layout">
        {{-- ── Status rail ─────────────────────────────────────────────── --}}
        <div>
            @if ($creditsEnabled)
                <div class="wf-panel wf-panel--brand">
                    <div class="wf-panel-heading">
                        <span>{{ __('dashboard.credit_balance') }}</span>
                        <span class="wf-chevron">▲</span>
                    </div>
                    <div class="wf-panel-body" style="text-align:center">
                        <div class="wf-stat-num">{{ $credit?->formatted_amount ?? __('dashboard.no_credit') }}</div>
                        <a class="wf-btn wf-btn--sm wf-btn--block" style="margin-top:.75rem"
                           href="{{ route('account.credits') }}" wire:navigate>{{ __('dashboard.add_funds') }}</a>
                    </div>
                </div>
            @endif

            <div class="wf-panel wf-panel--brand">
                <div class="wf-panel-heading">
                    <span><span class="wf-head-icon"><x-ri-archive-stack-fill /></span>{{ __('theme.view') }}</span>
                    <span class="wf-chevron">&#9650;</span>
                </div>
                <ul class="wf-list">
                    @foreach ($statusCounts as $status => $count)
                        <li>
                            <a href="{{ route('services') }}" wire:navigate>
                                <span>{{ __('theme.status_' . $status) }}</span>
                                <span class="wf-label">{{ $count }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="wf-panel">
                <div class="wf-panel-heading">+ {{ __('theme.actions') }}</div>
                <ul class="wf-list">
                    <li>
                        <a href="{{ route('home') }}" wire:navigate>
                            <span>{{ __('theme.place_new_order') }}</span>
                            <span class="wf-head-icon"><x-ri-shopping-cart-2-fill /></span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        {{-- ── Table ───────────────────────────────────────────────────── --}}
        <div>
    <div class="wf-panel">
        <div class="wf-panel-heading">{{ __('theme.active_products_services') }}</div>
        <div class="wf-table-wrap">
            <table class="wf-table">
                <thead>
                    <tr>
                        <th>{{ __('navigation.services') }}</th>
                        <th>{{ __('services.renews_on') ?? 'Renews' }}</th>
                        <th style="text-align:end">{{ __('invoices.status') ?? 'Status' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($services as $service)
                        @php
                            $tone = match ($service->status) {
                                'active' => 'wf-label--success',
                                'suspended', 'cancelled' => 'wf-label--danger',
                                'pending' => 'wf-label--warning',
                                default => '',
                            };
                        @endphp
                        @php($addon = $addonParents->get($service->id))
                        <tr>
                            <td>
                                <a href="{{ route('services.show', $service) }}" wire:navigate>
                                    <span class="wf-list-title">
                                        {{-- The reference nests an addon under the service it
                                             extends rather than listing it as its own thing. --}}
                                        @if ($addon?->parent)&#8618; @endif{{ $service->label }}
                                    </span>
                                </a>
                                <span class="wf-list-sub">
                                    @if ($addon?->parent)
                                        {{ __('theme.addon_of', ['service' => $addon->parent->product?->name ?? ('#' . $addon->parent->id)]) }}
                                    @else
                                        {{ $service->product?->category?->name }}
                                    @endif
                                </span>
                            </td>
                            <td>{{ $service->expires_at ? $service->expires_at->format('M d, Y') : '—' }}</td>
                            <td style="text-align:end"><span class="wf-label {{ $tone }}">{{ ucfirst($service->status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="3"><div class="wf-empty">{{ __('services.no_services') }}</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

            {{ $services->links() }}
        </div>
    </div>
</div>
