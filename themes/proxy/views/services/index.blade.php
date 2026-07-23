{{-- Services list — WHMCS "Six" style table in a panel. Same $services paginator. --}}
<div class="wf-page">
    <div class="wf-pagehead">
        <h1>{{ __('navigation.services') }}</h1>
    </div>

    <div class="wf-panel">
        <div class="wf-panel-heading">{{ __('dashboard.active_services') }}</div>
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
                        <tr>
                            <td>
                                <a href="{{ route('services.show', $service) }}" wire:navigate>
                                    <span class="wf-list-title">{{ $service->label }}</span>
                                </a>
                                <span class="wf-list-sub">{{ $service->product?->category?->name }}</span>
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
