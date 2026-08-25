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

    $shortcuts = Rail::shortcuts();
    $searches = Rail::searches();
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
        @if ($shortcuts)
            <section class="ao-rail-panel">
                <h2 class="ao-rail-heading">Shortcuts</h2>
                <ul class="ao-rail-list">
                    @foreach ($shortcuts as $shortcut)
                        <li><a href="{{ $shortcut['url'] }}">{{ $shortcut['label'] }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="ao-rail-panel">
            <h2 class="ao-rail-heading">System Information</h2>
            <dl class="ao-rail-facts">
                @foreach (Rail::systemInformation() as $label => $value)
                    <dt>{{ $label }}</dt>
                    <dd>{{ $value }}</dd>
                @endforeach
            </dl>
        </section>

        @if ($searches)
            <section class="ao-rail-panel">
                <h2 class="ao-rail-heading">Advanced Search</h2>
                <ul class="ao-rail-list ao-rail-list-counted">
                    @foreach ($searches as $search)
                        <li>
                            <a href="{{ $search['url'] }}">
                                <span>{{ $search['label'] }}</span>
                                <span @class(['ao-rail-count', 'ao-rail-count-zero' => $search['count'] === 0])>
                                    {{ number_format($search['count']) }}
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="ao-rail-panel">
            <h2 class="ao-rail-heading">Staff Online</h2>
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
