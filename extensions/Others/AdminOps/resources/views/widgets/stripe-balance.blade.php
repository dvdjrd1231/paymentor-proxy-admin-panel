<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Stripe Balance</x-slot>
        @if ($tiles === [])
            <p class="ao-wg-empty">Stripe is not connected, or its key is not set.</p>
        @else
            <div class="ao-wg-cols">
                @foreach ($tiles as $tile)
                    <div class="ao-wg-stat">
                        <b class="{{ $tile['label'] === 'Available' ? 'ao-wg-green' : 'ao-wg-blue' }}">{{ $tile['amount'] }}</b>
                        <span>{{ $tile['label'] }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
