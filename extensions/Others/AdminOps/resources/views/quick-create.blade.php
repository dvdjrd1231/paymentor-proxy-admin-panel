{{-- The reference's **+** button, at the start of the menu bar. --}}
@php
    use Paymenter\Extensions\Others\AdminOps\Support\Toolbar;

    $creatable = Toolbar::quickCreate();
@endphp

@if ($creatable)
    <x-filament::dropdown placement="bottom-start" teleport>
        <x-slot name="trigger">
            <button type="button" class="ao-tool ao-tool-create" title="Create new" aria-label="Create new">
                <x-filament::icon icon="heroicon-o-plus" class="ao-tool-icon" />
            </button>
        </x-slot>

        <x-filament::dropdown.list>
            @foreach ($creatable as $item)
                <x-filament::dropdown.list.item tag="a" :href="$item['url']">
                    {{ $item['label'] }}
                </x-filament::dropdown.list.item>
            @endforeach
        </x-filament::dropdown.list>
    </x-filament::dropdown>
@endif
