{{--
    The reference's utility icons — automation status, updates, setup, help — at the end of
    the menu bar.

    Rendered through `panels::global-search.after`, which puts them inside `.fi-topbar-end`
    immediately after the search field and before the user menu. The reference's order runs
    …wrench, avatar, question mark, so the account menu belongs *between* the last two of
    these; there is no render hook at that point, so the skin reorders the flex row instead
    (`.fi-topbar-end` and `.ao-tool-wrap-help`). Doing it in CSS rather than rebuilding the
    account menu here keeps Filament's own user menu — and with it the working sign-out and
    the theme switcher — instead of a second copy of both.

    Two of these are plain links, not dropdowns, because that is what they are on the
    reference: its cogs opens Automation Status and its arrow opens the updater. See the table
    in {@see Toolbar}.

    The badge on the cogs is the reference's red marker. It shows a number or nothing, never a
    zero — same rule as the menu badges, for the same reason: a permanent grey 0 is something
    you learn to stop seeing.
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
        {{-- `shift` on top of the default `flip`: these hang off the right-hand end of the
             bar, and the setup panel is three columns wide, so without it floating-ui
             anchors the panel and lets it run off the edge of the window — the last column
             of the grid was simply not reachable. Shift slides it back inside instead. --}}
        {{-- `width` is not decoration: `.fi-dropdown-panel` ships `max-width: 14rem
             !important`, and a three-column tile grid does not fit in 14rem. The tiles
             overflowed the white panel and sat on the page behind it — an `!important` in
             the compiled theme cannot be outranked from this stylesheet, so the panel is
             widened through Filament's own width prop, which is written to beat it. --}}
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
