{{-- Services widget as a Six-style list group (same $services data as the default theme).
     Row layout mirrors the client's current portal: status badge, product name, and a
     per-row action button on the right. --}}
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
            <div class="wf-list-row">
                <span class="wf-row-main">
                    <span class="wf-label {{ $tone }}">{{ ucfirst($status) }}</span>
                    <span style="min-width:0">
                        <a class="wf-list-title wf-row-link" href="{{ route('services.show', $service) }}" wire:navigate>
                            {{ $service->label }}
                        </a>
                        <span class="wf-list-sub">
                            {{ $service->product?->category?->name }}
                            @if ($service->expires_at)
                                &middot; {{ __('services.renews_on') }}: {{ $service->expires_at->format('M d, Y') }}
                            @endif
                        </span>
                    </span>
                </span>
                <a class="wf-btn wf-btn--sm" href="{{ route('services.show', $service) }}" wire:navigate>
                    {{ __('services.view') }}
                </a>
            </div>
        </li>
    @empty
        <li><div class="wf-empty">{{ __('dashboard.active_services') }} &mdash; 0</div></li>
    @endforelse
</ul>
