{{--
    The reference's four homepage tiles. Unlike the action queue below, a zero *is* shown:
    the point of a fixed row is that a figure is always in the same place, and "0 tickets
    waiting" is information. A tile whose URL could not be resolved still shows its number,
    it just is not a link.
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
    <div class="ao-panel ao-tiles">
        @foreach ($tiles as $tile)
            @php
                $classes = 'ao-tile ao-tile-' . $tile['tone'];
            @endphp

            @if ($tile['url'])
                <a href="{{ $tile['url'] }}" class="{{ $classes }}">
            @else
                <div class="{{ $classes }}">
            @endif

                <x-filament::icon :icon="$tile['icon']" class="ao-tile-icon" />
                <span class="ao-tile-figure">
                    <span class="ao-tile-count">{{ number_format($tile['count']) }}</span>
                    <span class="ao-tile-label">{{ $tile['label'] }}</span>
                </span>

            @if ($tile['url'])
                </a>
            @else
                </div>
            @endif
        @endforeach
    </div>
</x-filament-widgets::widget>
