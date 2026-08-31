<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Billing</x-slot>
        <div class="ao-wg-cols ao-wg-wrap">
            @foreach ($figures as [$label, $amount, $class])
                <div class="ao-wg-stat">
                    <b class="{{ $class }}">{{ $amount }}</b>
                    <span>{{ $label }}</span>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
