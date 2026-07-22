{{-- Services widget as a Six-style list group (same $services data as the default theme). --}}
<ul class="wf-list">
    @forelse ($services as $service)
        @php
            $status = $service->status;
            $tone = match ($status) {
                'active' => 'wf-label--success',
                'suspended' => 'wf-label--danger',
                'pending' => 'wf-label--warning',
                default => '',
            };
        @endphp
        <li>
            <a href="{{ route('services.show', $service) }}" wire:navigate>
                <span style="min-width:0">
                    <span class="wf-list-title">{{ $service->label }}</span>
                    <span class="wf-list-sub">
                        {{ $service->product?->category?->name }}
                        @if ($service->expires_at)
                            &middot; {{ __('services.renews_on') }}: {{ $service->expires_at->format('M d, Y') }}
                        @endif
                    </span>
                </span>
                <span class="wf-label {{ $tone }}">{{ ucfirst($status) }}</span>
            </a>
        </li>
    @empty
        <li><div class="wf-empty">{{ __('dashboard.active_services') }} &mdash; 0</div></li>
    @endforelse
</ul>
