{{--
    System Settings, to issues #34 and #40: the reference's landing screen — the
    setup-tasks progress box up top, a left rail with Search, the category list and
    Recently Visited, then the "All Settings" band with its sort control over the grid
    of tall cards.

    Everything on it is real. The progress bar counts actual setup state; the search
    filters the cards that are on the page; Recently Visited is this browser's own
    trail, kept in localStorage; the sort control reorders the cards it says it does.
--}}
<x-filament-panels::page>
    <div class="ao-mu ao-ssx">
        {{-- The reference's setup-tasks box, top right of the hero. --}}
        <div class="ao-ssx-hero">
            <div class="ao-ssx-tasks">
                <button type="button" class="ao-ssx-tasks-toggle" wire:click="$toggle('tasksOpen')">
                    Click here to view the setup tasks
                </button>
                <div class="ao-ssx-progress" role="progressbar" aria-valuenow="{{ $tasksPct }}"
                    aria-valuemin="0" aria-valuemax="100" title="{{ $tasksDone }} of {{ count($tasks) }} setup tasks complete">
                    <span class="ao-ssx-progress-bar" style="width: {{ $tasksPct }}%"></span>
                </div>
                <span class="ao-ssx-progress-pct">{{ $tasksPct }}%</span>

                @if ($tasksOpen)
                    <ul class="ao-ssx-tasklist">
                        @foreach ($tasks as $task)
                            <li class="{{ $task['done'] ? 'ao-ssx-task-done' : '' }}">
                                <x-filament::icon
                                    :icon="$task['done'] ? 'ri-checkbox-circle-fill' : 'ri-checkbox-blank-circle-line'"
                                    class="ao-ssx-task-ic" />
                                @if (!$task['done'] && $task['url'])
                                    <a href="{{ $task['url'] }}">{{ $task['label'] }}</a>
                                @else
                                    <span>{{ $task['label'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="ao-ssx-cols">
            {{-- The reference's left rail: search, the areas, Recently Visited. --}}
            <aside class="ao-ssx-rail">
                <label class="ao-ssx-search">
                    <x-filament::icon icon="ri-search-line" class="ao-ssx-search-ic" />
                    <input type="search" placeholder="Search" data-ao-ss-search
                        aria-label="Search settings" autocomplete="off">
                </label>

                <ul class="ao-ssx-areas">
                    <li>
                        <button type="button" class="{{ $area === 'All' ? 'ao-on' : '' }}"
                            wire:click="$set('area', 'All')">All</button>
                    </li>
                    @foreach ($areas as $name)
                        <li>
                            <button type="button" class="{{ $area === $name ? 'ao-on' : '' }}"
                                wire:click="$set('area', '{{ $name }}')">{{ $name }}</button>
                        </li>
                    @endforeach
                </ul>

                <div class="ao-ssx-recent" data-ao-ss-recent hidden>
                    <h4>Recently Visited</h4>
                    <ol></ol>
                </div>
            </aside>

            <div class="ao-ssx-main">
                {{-- The reference's band over the grid: the pick's name, the sort. --}}
                <div class="ao-ssx-band">
                    <h3>{{ $area === 'All' ? 'All Settings' : $area }}</h3>
                    <select wire:model.live="sort" aria-label="Sort settings">
                        <option value="popularity">Popularity</option>
                        <option value="alphabetical">A → Z</option>
                    </select>
                </div>

                @foreach ($sections as $category => $tiles)
                    @if ($area === 'All')
                        <h3 class="ao-rp-cat">{{ $category }}</h3>
                    @endif
                    <div class="ao-ss-grid">
                        @foreach ($tiles as [$label, $href, $icon, $description])
                            <a class="ao-ss-card" href="{{ $href }}" data-ao-ss-card
                                data-ao-ss-text="{{ Str::lower($label . ' ' . $description) }}"
                                data-ao-ss-label="{{ $label }}">
                                <span class="ao-ss-card-ic"><x-filament::icon :icon="$icon" /></span>
                                <span class="ao-ss-card-body">
                                    <span class="ao-ss-card-title">{{ $label }}</span>
                                    <span class="ao-ss-card-desc">{{ $description }}</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endforeach

                <p class="ao-ssx-none" data-ao-ss-none hidden>Nothing matches that search.</p>
            </div>
        </div>
    </div>

    <script>
        (() => {
        const boot = () => {
            const rail = document.querySelector('[data-ao-ss-search]');
            if (!rail || rail.dataset.aoBound) return;
            rail.dataset.aoBound = '1';

            // The search filters the cards that are already on the page — no roundtrip.
            rail.addEventListener('input', () => {
                const needle = rail.value.trim().toLowerCase();
                let shown = 0;

                document.querySelectorAll('[data-ao-ss-card]').forEach((card) => {
                    const hit = needle === '' || card.dataset.aoSsText.includes(needle);
                    card.hidden = !hit;
                    if (hit) shown++;
                });

                // Category headings with every card hidden hide too.
                document.querySelectorAll('.ao-rp-cat').forEach((head) => {
                    const grid = head.nextElementSibling;
                    const any = grid && grid.querySelector('[data-ao-ss-card]:not([hidden])');
                    head.hidden = !any;
                    if (grid) grid.hidden = !any;
                });

                const none = document.querySelector('[data-ao-ss-none]');
                if (none) none.hidden = shown > 0;
            });

            // Recently Visited is this browser's own trail: a click on any card is
            // remembered in localStorage and the last five are offered next time.
            const KEY = 'ao-ss-recent';
            const read = () => {
                try { return JSON.parse(localStorage.getItem(KEY)) || []; } catch { return []; }
            };

            // Delegated, so cards re-rendered by Livewire (area or sort changes) are
            // still recorded without rebinding anything.
            if (!window.aoSsClicks) {
                window.aoSsClicks = true;
                document.addEventListener('click', (event) => {
                    const card = event.target.closest('[data-ao-ss-card]');
                    if (!card) return;
                    const entry = { label: card.dataset.aoSsLabel, href: card.getAttribute('href') };
                    const rest = read().filter((e) => e.label !== entry.label);
                    localStorage.setItem(KEY, JSON.stringify([entry, ...rest].slice(0, 5)));
                });
            }

            const box = document.querySelector('[data-ao-ss-recent]');
            const trail = read();
            if (box && trail.length) {
                const list = box.querySelector('ol');
                trail.forEach((e, i) => {
                    const li = document.createElement('li');
                    const a = document.createElement('a');
                    a.href = e.href;
                    a.textContent = (i + 1) + '. ' + e.label;
                    li.appendChild(a);
                    list.appendChild(li);
                });
                box.hidden = false;
            }
        };

        boot();

        if (!window.aoSsBound) {
            window.aoSsBound = true;
            document.addEventListener('livewire:init', boot);
            document.addEventListener('livewire:navigated', boot);
        }
        })();
    </script>
</x-filament-panels::page>
