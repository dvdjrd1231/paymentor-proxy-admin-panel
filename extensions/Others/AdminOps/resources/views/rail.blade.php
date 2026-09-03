{{--
    WHMCS's left sidebar column, rendered on every admin page through the
    `panels::layout.start` hook — which puts it inside `.fi-layout`, immediately before
    Filament's own (off-screen) sidebar, so it becomes the first column of the page.

    Static markup on purpose: this is not a Livewire component, so it costs one render and
    never polls. Everything on it changes on the scale of minutes, and the dashboard widgets
    already carry the live figures.
--}}
@php
    use Paymenter\Extensions\Others\AdminOps\Support\Rail;

    $sections = Rail::sections();
    $shortcuts = Rail::shortcuts();
    $search = Rail::advancedSearch();
    $staff = Rail::staffOnline();
@endphp

{{-- Plain localStorage rather than Alpine's $persist magic: Filament bundles the persist
     plugin for its own stores, but relying on a magic this view does not register would
     make the rail depend on an implementation detail of the panel's JS build. --}}
<aside class="ao-rail"
    x-data="{ open: (localStorage.getItem('ao_rail_open') ?? '1') === '1' }"
    x-effect="localStorage.setItem('ao_rail_open', open ? '1' : '0')"
    x-bind:class="{ 'ao-rail-collapsed': ! open }">
    <div class="ao-rail-inner">
        {{-- The section you are in, top of the column — the reference's contextual rail.
             On the dashboard there is no section, so Shortcuts takes the slot instead. --}}
        @if ($sections !== [])
            @foreach ($sections as $section)
                <section class="ao-rail-panel">
                    <h2 class="ao-rail-heading">
                        @if ($section['icon'])
                            <x-filament::icon :icon="$section['icon']" class="ao-rail-heading-icon" />
                        @endif
                        {{ $section['label'] }}
                    </h2>
                    @if (($section['form'] ?? null) === 'tag-cloud')
                        <p class="ao-rail-none">None</p>
                    @elseif (($section['form'] ?? null) === 'filter-tickets')
                        {{-- The reference's Filter Tickets box: a real form — everything
                             lands on the tickets page as URL filters. --}}
                        <form class="ao-rail-filter" method="get" action="{{ $section['action'] }}">
                            <label>Status
                                <select name="view">
                                    <option value="open">Awaiting Reply ({{ number_format($section['counts']['open'] ?? 0) }})</option>
                                    <option value="flagged">Flagged Tickets ({{ number_format($section['counts']['flagged'] ?? 0) }})</option>
                                    <option value="active">All Active Tickets ({{ number_format($section['counts']['active'] ?? 0) }})</option>
                                    <option value="answered">Answered ({{ number_format($section['counts']['answered'] ?? 0) }})</option>
                                    <option value="closed">Closed ({{ number_format($section['counts']['closed'] ?? 0) }})</option>
                                </select>
                            </label>
                            <label>Department
                                <select name="dept">
                                    <option value="">- Any -</option>
                                    @foreach ($section['departments'] as $department)
                                        <option value="{{ $department }}">{{ $department }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>Subject/Message
                                <input type="text" name="q">
                            </label>
                            <label>Email Address
                                <input type="text" name="email">
                            </label>
                            <button type="submit">Filter &raquo;</button>
                        </form>
                    @else
                        <ul class="ao-rail-list ao-rail-list-counted">
                            @foreach ($section['items'] as $item)
                                <li @if (str_starts_with($item['label'], '- ')) class="ao-rail-sub" @endif>
                                    @if ($item['url'] ?? false)
                                        <a href="{{ $item['url'] }}">
                                            <span>{{ $item['label'] }}</span>
                                            @if ($item['badge'])
                                                <span class="ao-rail-count">{{ $item['badge'] }}</span>
                                            @endif
                                        </a>
                                    @else
                                        {{-- An honestly-dead entry: listed where the reference
                                             lists it, the reason it does nothing on its title. --}}
                                        <span class="ao-rail-dead" title="{{ $item['title'] ?? '' }}">{{ $item['label'] }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach
        @elseif ($shortcuts)
            <section class="ao-rail-panel">
                <h2 class="ao-rail-heading">
                    <x-filament::icon icon="ri-star-line" class="ao-rail-heading-icon" />
                    Shortcuts
                </h2>
                <ul class="ao-rail-list">
                    @foreach ($shortcuts as $shortcut)
                        <li>
                            <a href="{{ $shortcut['url'] }}">
                                <x-filament::icon :icon="$shortcut['icon']" class="ao-rail-link-icon" />
                                {{ $shortcut['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- No System Information box: the reference's rail never carries one (issue #25 —
             the extra box also ran the rail taller than the window, which is where the
             permanent page scrollbar came from). Those facts live on the dashboard. --}}

        @if ($search)
            {{-- The reference's Advanced Search widget: area, field, term, Search. A plain
                 GET form — the term submits as the chosen field's query param, which the
                 destination page already reads as a #[Url] filter. --}}
            <section class="ao-rail-panel">
                <h2 class="ao-rail-heading">
                    <x-filament::icon icon="ri-search-eye-line" class="ao-rail-heading-icon" />
                    Advanced Search
                </h2>
                <form class="ao-rail-filter" method="get"
                    x-data="{ types: {{ \Illuminate\Support\Js::from($search) }}, type: 0, field: 0 }"
                    x-bind:action="types[type].action">
                    <select x-model.number="type" x-on:change="field = 0" aria-label="Search in">
                        <template x-for="(t, i) in types" :key="i">
                            <option :value="i" x-text="t.label"></option>
                        </template>
                    </select>
                    <select x-model.number="field" aria-label="Search by">
                        <template x-for="(f, i) in types[type].fields" :key="type + '-' + i">
                            <option :value="i" x-text="f.label"></option>
                        </template>
                    </select>
                    <div class="ao-rail-search-row">
                        <input type="text" x-bind:name="types[type].fields[field].param"
                            x-bind:placeholder="types[type].fields[field].label" aria-label="Search term">
                        <button type="submit">Search</button>
                    </div>
                </form>
            </section>
        @endif

        <section class="ao-rail-panel">
            <h2 class="ao-rail-heading">
                <x-filament::icon icon="ri-group-line" class="ao-rail-heading-icon" />
                Staff Online
            </h2>
            <ul class="ao-rail-staff">
                @foreach ($staff as $member)
                    <li>
                        <span class="ao-rail-staff-name">{{ $member['name'] }}</span>
                        @if ($member['seen'])
                            <span class="ao-rail-staff-seen">{{ $member['self'] ? 'you' : $member['seen'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    </div>

    {{-- WHMCS's "Minimise Sidebar" bar, pinned to the bottom of the column. The state is
         persisted, so it stays collapsed across page loads the way the reference does. --}}
    <button type="button" class="ao-rail-toggle" x-on:click="open = ! open">
        <span x-show="open">&laquo; Minimise Sidebar</span>
        <span x-show="! open" x-cloak>&raquo;</span>
    </button>
</aside>
