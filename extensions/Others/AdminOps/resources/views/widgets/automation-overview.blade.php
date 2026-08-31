<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Automation Overview</x-slot>
        <div class="ao-wg-auto">
            @foreach ($stats as [$label, $value, $color])
                <div class="ao-wg-auto-stat">
                    {{-- The reference's little hump, in the stat's own colour. --}}
                    <svg viewBox="0 0 90 22" aria-hidden="true">
                        <polyline points="0,21 25,21 45,4 65,21 90,21" fill="none" stroke="{{ $color }}" stroke-width="2" />
                    </svg>
                    <span>{{ $label }}</span>
                    <b style="color: {{ $color }}">{{ number_format($value) }}</b>
                </div>
            @endforeach
        </div>
        @if ($lastRun)
            <p class="ao-wg-lastrun">&#10003; Last Automation Run: <b>{{ $lastRun }}</b></p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
