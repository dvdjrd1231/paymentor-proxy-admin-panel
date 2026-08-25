{{--
    The reference's utility icons — system health, updates, setup, help — at the end of the
    menu bar.

    Rendered through `panels::global-search.after`, which puts them inside `.fi-topbar-end`
    immediately after the search field and before the user menu. That is the reference's own
    order: magnifier first, avatar last.

    The badge on the health icon is the reference's red marker on its gear. It shows a number
    or nothing, never a zero — same rule as the menu badges, for the same reason: a permanent
    grey 0 is something you learn to stop seeing.
--}}
@php
    use Paymenter\Extensions\Others\AdminOps\Support\Toolbar;

    $clusters = Toolbar::utilities();
@endphp

@foreach ($clusters as $cluster)
    <x-filament::dropdown placement="bottom-end" teleport>
        <x-slot name="trigger">
            <button
                type="button"
                class="ao-tool ao-tool-{{ $cluster['key'] }}"
                title="{{ $cluster['label'] }}"
                aria-label="{{ $cluster['label'] }}"
            >
                <x-filament::icon :icon="$cluster['icon']" class="ao-tool-icon" />

                @if ($cluster['badge'])
                    <span class="ao-tool-badge">{{ $cluster['badge'] > 99 ? '99+' : $cluster['badge'] }}</span>
                @endif
            </button>
        </x-slot>

        <x-filament::dropdown.list>
            @foreach ($cluster['items'] as $item)
                <x-filament::dropdown.list.item
                    tag="a"
                    :href="$item['url']"
                    :target="$item['target']"
                >
                    {{ $item['label'] }}
                </x-filament::dropdown.list.item>
            @endforeach
        </x-filament::dropdown.list>
    </x-filament::dropdown>
@endforeach
