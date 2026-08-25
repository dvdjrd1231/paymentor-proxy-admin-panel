{{--
    Rows come from the widget already filtered to non-zero counts and sorted by urgency, so
    this only decides how they look. A row whose URL could not be resolved (its extension is
    not installed) still shows the count — it just is not a link.
--}}
@php
    $pollingInterval = $this->getPollingInterval();
@endphp

<x-filament-widgets::widget
    :attributes="
        (new \Illuminate\View\ComponentAttributeBag)
            ->merge([
                'wire:poll.' . $pollingInterval => $pollingInterval ? true : null,
            ], escape: false)
    "
>
    <x-filament::section
        icon="heroicon-o-bell-alert"
        heading="Needs attention"
        :description="count($rows) ? 'Ordered by how much it costs to leave alone' : null"
    >
        <div class="ao-panel">
            @forelse ($rows as $row)
                @php
                    $classes = 'ao-queue-row ao-queue-row-' . $row['tone'];
                @endphp

                @if ($row['url'])
                    <a href="{{ $row['url'] }}" class="{{ $classes }}">
                @else
                    <div class="{{ $classes }}">
                @endif

                    <span class="ao-queue-count">{{ number_format($row['count']) }}</span>
                    <span class="ao-queue-label">
                        {{ $row['label'] }}
                        <span class="ao-queue-note">{{ $row['note'] }}</span>
                    </span>
                    @if ($row['url'])
                        <span class="ao-queue-go" aria-hidden="true">&rarr;</span>
                    @endif

                @if ($row['url'])
                    </a>
                @else
                    </div>
                @endif
            @empty
                <p class="ao-empty">Nothing needs attention — no unpaid invoices, no waiting tickets, nothing pending.</p>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
