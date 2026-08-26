{{--
    The utility icons at the end of the menu bar, rendered at `panels::global-search.after`.

    The reference's order runs …wrench, avatar, question mark, so the account menu belongs
    between the last two — but there is no render hook there, so the skin reorders the flex
    row instead (`.ao-tool-wrap-help`). Done in CSS rather than by rebuilding the account menu
    here, which keeps Filament's own sign-out and theme switcher.

    The cogs and the updater are plain links, not dropdowns, as on the reference. The cogs
    badge shows a number or nothing, never a zero.
--}}
@php
    use Paymenter\Extensions\Others\AdminOps\Support\Toolbar;

    $clusters = Toolbar::utilities();
@endphp

@foreach ($clusters as $cluster)
    @if ($cluster['type'] === 'link')
        <a
            href="{{ $cluster['url'] }}"
            class="ao-tool ao-tool-{{ $cluster['key'] }}"
            title="{{ $cluster['label'] }}"
            aria-label="{{ $cluster['label'] }}"
        >
            <x-filament::icon :icon="$cluster['icon']" class="ao-tool-icon" />

            @if ($cluster['badge'])
                <span class="ao-tool-badge">{{ $cluster['badge'] > 99 ? '99+' : $cluster['badge'] }}</span>
            @endif
        </a>
    @else
        {{-- `shift` keeps these on screen: they hang off the right-hand end of the bar, and
             without it floating-ui lets the three-column setup panel run off the window edge.
             `width` is not decoration either — `.fi-dropdown-panel` ships
             `max-width: 14rem !important`, which no rule in the skin can outrank, so the
             tiles overflowed the white panel and sat on the page behind it. --}}
        <x-filament::dropdown
            placement="bottom-end"
            shift
            teleport
            :width="($cluster['grid'] ?? false) ? 'sm' : null"
            class="ao-tool-wrap ao-tool-wrap-{{ $cluster['key'] }}"
        >
            <x-slot name="trigger">
                <button
                    type="button"
                    class="ao-tool ao-tool-{{ $cluster['key'] }}"
                    title="{{ $cluster['label'] }}"
                    aria-label="{{ $cluster['label'] }}"
                >
                    <x-filament::icon :icon="$cluster['icon']" class="ao-tool-icon" />
                </button>
            </x-slot>

            @if ($cluster['grid'] ?? false)
                {{-- The reference's `ul.drop-icons`: centred tiles, icon over label, three
                     to a row. Plain markup rather than Filament's dropdown list, because a
                     list item is a row by construction and this needs a grid cell. --}}
                <div class="ao-drop-icons">
                    @foreach ($cluster['items'] as $item)
                        <a
                            href="{{ $item['url'] }}"
                            @if ($item['target']) target="{{ $item['target'] }}" rel="noopener" @endif
                            class="ao-drop-icon"
                        >
                            @if ($item['icon'])
                                <span class="ao-drop-icon-mark">
                                    <x-filament::icon :icon="$item['icon']" />
                                </span>
                            @endif

                            <span class="ao-drop-icon-label">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            @else
                <x-filament::dropdown.list>
                    @foreach ($cluster['items'] as $item)
                        <x-filament::dropdown.list.item
                            tag="a"
                            :href="$item['url']"
                            :target="$item['target']"
                            @class(['ao-drop-item-separated' => $item['separated'] ?? false])
                        >
                            {{ $item['label'] }}
                        </x-filament::dropdown.list.item>
                    @endforeach
                </x-filament::dropdown.list>
            @endif
        </x-filament::dropdown>
    @endif
@endforeach
