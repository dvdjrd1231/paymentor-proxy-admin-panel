{{--
    The dashboard's chrome: drag, collapse, refresh, hide — and the menu that brings a
    hidden panel back. The reference's four behaviours, in the reference's division of
    where each is remembered: order and hidden per admin in the database, collapsed in the
    browser.

    This widget draws no panel of its own. It renders the settings menu into the corner the
    reference puts it in and then decorates its neighbours, which is why it sorts first.

    Plain JS and the HTML5 drag API, as everywhere else in this extension: `extensions/` is
    not scanned by the admin theme and there is no build step in the deployment path, so
    there is nothing here to bundle and nothing to version. The reference uses Packery for a
    masonry layout; Filament's dashboard is a CSS grid, which reflows on its own, so what is
    needed is the ordering, not the layout engine.
--}}
@php
    $layout = $this->getLayout();
@endphp

{{--
    Wrapped like every other widget, and that matters more than it looks: the wrapper is what
    supplies the `.fi-wi-widget.fi-grid-col` box Filament lays the dashboard out with. Rendered
    as a bare div, this sat in a schema wrapper of its own instead of beside its neighbours —
    so `closest('.fi-wi-widget')` found nothing, the grid could not be identified, and the
    script returned on its second line without decorating anything or logging a word.
--}}
<x-filament-widgets::widget>
<div class="ao-dash-tools" data-ao-dash>
    <div class="ao-dash-settings" data-ao-settings hidden>
        <button type="button" class="ao-dash-settings-button" data-ao-settings-button
            aria-haspopup="true" aria-expanded="false" title="Choose panels">
            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" width="16" height="16">
                <path d="M8.34 1.8a1 1 0 0 1 .98-.8h1.36a1 1 0 0 1 .98.8l.2 1a6.9 6.9 0 0 1 1.2.7l.96-.33a1 1 0 0 1 1.2.45l.68 1.17a1 1 0 0 1-.22 1.26l-.77.66a6.9 6.9 0 0 1 0 1.38l.77.66a1 1 0 0 1 .22 1.26l-.68 1.17a1 1 0 0 1-1.2.45l-.97-.33c-.37.28-.77.51-1.2.7l-.2 1a1 1 0 0 1-.97.8H9.32a1 1 0 0 1-.98-.8l-.2-1a6.9 6.9 0 0 1-1.2-.7l-.96.33a1 1 0 0 1-1.2-.45l-.68-1.17a1 1 0 0 1 .22-1.26l.77-.66a6.9 6.9 0 0 1 0-1.38l-.77-.66a1 1 0 0 1-.22-1.26l.68-1.17a1 1 0 0 1 1.2-.45l.96.33c.37-.28.77-.51 1.2-.7l.2-1ZM10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/>
            </svg>
            <span class="ao-sr">Choose which panels to show</span>
        </button>

        <div class="ao-dash-menu" data-ao-menu hidden>
            <h4>Show/Hide Widgets</h4>
            <ul data-ao-menu-list></ul>
            <p class="ao-dash-menu-note">Drag a panel by its heading to move it.</p>
        </div>
    </div>
</div>

<script>
    (() => {
    const boot = () => {
        const mount = document.querySelector('[data-ao-dash]');
        if (!mount || mount.dataset.aoBound || !window.Livewire) return;

        // Whatever level our own widget sits at in the grid, its neighbours sit at the
        // same one — so this is the container to reorder, by construction rather than by
        // guessing a Filament class name.
        const grid = mount.closest('.fi-wi-widget')?.parentElement;
        if (!grid) return;

        // ── Masonry ──────────────────────────────────────────────────────────────────
        // The reference packs its columns with Packery, so a short panel never leaves a
        // hole beside a tall one. Same effect, no library: 10px grid rows, each panel
        // spanning as many as its content needs, so every column packs independently.
        //
        // A first attempt at this was reverted for collapsing the chart, and both causes
        // are gone by construction now: the items are `align-self: start`, so a panel's
        // height is its *content's* height whatever span it holds — measuring it cannot
        // feed back into what was measured — and the chart canvas has a fixed 300px
        // height, so nothing sizes itself from the row any more.
        const ROW = 10;

        const pack = () => {
            const gap = parseFloat(getComputedStyle(grid).rowGap) || 0;

            for (const child of grid.children) {
                // display:none (our collapsed widget, hidden panels) has no box; spanning
                // it would hold empty rows open where the panel used to be.
                if (!child.getClientRects().length) {
                    if (child.style.getPropertyValue('--ao-span')) child.style.removeProperty('--ao-span');
                    continue;
                }

                const span = Math.max(1, Math.ceil((child.offsetHeight + gap) / (ROW + gap)));

                // Write only on change: a ResizeObserver that sees its own writes is an
                // infinite loop with extra steps.
                if (child.style.getPropertyValue('--ao-span') !== String(span)) {
                    child.style.setProperty('--ao-span', span);
                }
            }
        };

        let packFrame = null;
        const repack = () => {
            cancelAnimationFrame(packFrame);
            packFrame = requestAnimationFrame(pack);
        };

        // Panels change height on their own — a chart draws, a poll returns more rows —
        // and each change has to re-pack the column it sits in.
        const sizes = new ResizeObserver(repack);
        window.addEventListener('resize', repack);

        grid.classList.add('ao-grid');

        // Pack *now*, placeholders included — not only from decorate(), which returns
        // early while everything is still lazy. Unpacked placeholders held their default
        // span, the lazy widgets sat below the viewport, and nothing ever loaded to
        // trigger the packing that would have brought them up: a deadlock the first
        // masonry attempt died of without anyone diagnosing it.
        for (const child of grid.children) sizes.observe(child);
        pack();

        // The reference puts this gear at the top right of the page, level with the
        // "Dashboard" heading — not in the grid. This widget has to *be* in the grid (it is
        // how it sorts first and finds its neighbours), so the gear is moved out of it into
        // Filament's page header once there is something to put in the menu.
        //
        // Moved rather than rendered there: a render hook cannot reach the widget's own
        // Livewire component, and the checkboxes have to call its methods.
        const lift = () => {
            const header = document.querySelector('.fi-header, .fi-page-header');
            const settings = mount.querySelector('[data-ao-settings]');

            if (!settings || !header) return;

            // If one has already been lifted, anything still sitting in the widget is a
            // second copy that a re-render put back, and two gears is how the working one
            // ends up buried under a dead one. `#[Renderless]` on the actions is what stops
            // that happening; this is the guard for anything else that re-renders us.
            if (header.querySelector('[data-ao-settings][data-ao-lifted]')) {
                settings.remove();

                return;
            }

            header.classList.add('ao-dash-header');
            header.append(settings);
            settings.dataset.aoLifted = '1';

            // The row this widget occupied is now empty, and an empty grid row is a gap
            // above the tiles that looks like a rendering fault.
            mount.closest('.fi-wi-widget')?.classList.add('ao-dash-collapsed');
        };

        const saved = @js($layout);
        const COLLAPSED = 'aoCollapsedWidgets';

        // `Livewire.find()` hands back the `$wire` proxy, which is what carries `call()`.
        // `Livewire.all()` hands back the raw components, which do not — hence `.$wire`
        // wherever a neighbour is asked to do something. Getting this the wrong way round is
        // silent: the refresh button span for 600ms and refreshed nothing.
        const self = () => {
            const id = mount.closest('[wire\\:id]')?.getAttribute('wire:id');
            return id ? Livewire.find(id) : null;
        };

        // This widget renders with the page, so the script can run before Livewire has
        // registered anything. Claim the mount only once there is a component to talk to,
        // or the retry on `livewire:initialized` would find it already claimed and bail —
        // and nothing would ever bind.
        const tools = self();
        if (!tools) return;

        mount.dataset.aoBound = '1';

        // ── Which panels are on this screen ──────────────────────────────────────────
        // Keyed by Livewire component name: the only identifier that is the same string
        // on the next page load. The DOM id is per-render and the position is what we
        // are trying to remember in the first place.
        //
        // `block` is what gets moved: the outermost element that is a direct child of the
        // grid. Filament may wrap a widget in schema markup, so the widget's own root is
        // not necessarily the thing the grid lays out — reordering the inner element would
        // leave an empty wrapper behind in the old position.
        const blockOf = (root) => {
            let node = root;

            while (node?.parentElement && node.parentElement !== grid) {
                node = node.parentElement;
            }

            return node?.parentElement === grid ? node : null;
        };

        const panels = () => Livewire.all()
            .map((component) => {
                const el = component.el ?? document.querySelector(`[wire\\:id="${component.id}"]`);
                const root = el?.closest('.fi-wi-widget') ?? el;
                const block = root ? blockOf(root) : null;

                return block ? { name: component.name, root, block, component } : null;
            })
            .filter(Boolean)
            // Static panels — the reference stamps its own, and these are ours. Decided from
            // the markup rather than from a list of component names resolved in PHP: Livewire 4
            // has no stable class-to-name API, and the Livewire 3 one that was used here threw
            // `Target class [ComponentRegistry] does not exist` while rendering the dashboard.
            // This asks the DOM a question the DOM can answer.
            .filter((panel) => !panel.block.contains(mount)      // this widget
                && !panel.block.querySelector('.ao-tiles')       // the tile row
                // Every other widget is lazy: what is in the grid at first paint is an empty
                // `.fi-loading-section` box, and its real markup — heading included — is
                // morphed in later. Decorating the box would put the tools on something that
                // is about to be thrown away, and read a title that is not there yet.
                && !panel.root.classList.contains('fi-loading-section')
                && !panel.root.querySelector('.fi-loading-section'));

        const collapsed = () => {
            try {
                return JSON.parse(localStorage.getItem(COLLAPSED)) ?? [];
            } catch {
                // A corrupt value is not worth failing the dashboard over.
                return [];
            }
        };

        const rememberCollapsed = (list) => {
            try {
                localStorage.setItem(COLLAPSED, JSON.stringify(list));
            } catch {
                // Private browsing, or a full quota. The panel still collapses; it just
                // will not still be collapsed tomorrow.
            }
        };

        // ── The heading each panel is dragged by ─────────────────────────────────────
        // Filament gives most widgets a section header and gives chart widgets their own.
        // A widget with neither — a bare view — gets a slim bar of ours, so that every
        // panel has something to take hold of, which is the reference's promise.
        const headerOf = (root, title) => {
            // `.fi-section-header` is what Filament 5 actually emits. The `.fi-sc-` spelling
            // that was here matched nothing, so every panel that already had a perfectly good
            // heading got a second one bolted above it.
            //
            // `.ao-wi-bar` is in this list because this runs on every observer pass: without
            // it the stand-in is not *found*, it is *created again*, and the panel grows a
            // title bar per tick.
            // `.fi-ta-header` is the table widget's own heading (the Support panel). It was
            // missing from this list, so that panel got a second title bar bolted above the
            // one it already had.
            const existing = root.querySelector(
                '.ao-wi-bar, .fi-section-header, .fi-ta-header, .fi-wi-chart-header, .fi-wi-stats-overview-header, .fi-sc-section-header',
            );

            if (existing) return existing;

            const bar = document.createElement('div');
            bar.className = 'ao-wi-bar';
            bar.innerHTML = '<span class="ao-wi-bar-title"></span>';
            bar.firstChild.textContent = title;
            root.firstElementChild?.prepend(bar) ?? root.prepend(bar);

            return bar;
        };

        const titleOf = (root, name) => {
            const heading = root.querySelector(
                '.fi-section-header-heading, .fi-ta-header-heading, .fi-wi-chart-header-heading, .fi-sc-section-header-heading, h1, h2, h3',
            );

            if (heading?.textContent.trim()) return heading.textContent.trim();

            // Last resort: the component name, which is at least stable and readable.
            return name.split(/[.\\]/).pop().replace(/-/g, ' ').replace(/^./, (c) => c.toUpperCase());
        };

        // ── Apply what was saved ─────────────────────────────────────────────────────
        // Not once. Every panel but this one is lazy, so they arrive one at a time, minutes
        // apart if a query is slow — and each `wire:poll` tick morphs one of them again,
        // which throws away any tools sitting inside the part that was replaced.
        //
        // So this is written to be run repeatedly and to be a no-op when there is nothing
        // to do: decorate what is undecorated, leave the rest alone. A MutationObserver on
        // the grid runs it whenever the dashboard changes underneath us.
        const hidden = new Set(saved.hidden ?? []);
        const rolled = new Set(collapsed());
        const order = [...(saved.order ?? [])];
        const menu = mount.querySelector('[data-ao-menu-list]');

        let menuKey = '';

        const decorate = () => {
            const found = panels();
            if (!found.length) return;

            // Saved order first, in order; anything the admin has never moved — a widget
            // added by an extension since — keeps Filament's own position, after them.
            const ordered = [
                ...order.map((name) => found.find((panel) => panel.name === name)).filter(Boolean),
                ...found.filter((panel) => !order.includes(panel.name)),
            ];

            // Only touch the DOM if the order is actually wrong. Re-appending every panel on
            // every observer tick would fight `wire:poll` for the scroll position and make
            // the dashboard twitch once a minute for no reason.
            const laidOut = Array.from(grid.children).filter((el) => ordered.some((p) => p.block === el));
            const settled = laidOut.length === ordered.length
                && ordered.every((panel, index) => laidOut[index] === panel.block);

            if (!settled) ordered.forEach((panel) => grid.appendChild(panel.block));

            ordered.forEach((panel) => {
                const title = titleOf(panel.root, panel.name);
                const header = headerOf(panel.root, title);

                panel.block.dataset.aoWidget = panel.name;
                panel.block.classList.add('ao-wi');

                // Set, not added: a panel brought back from the menu has to lose the class
                // again, and a morph can restore one we had removed.
                panel.block.classList.toggle('ao-wi-hidden', hidden.has(panel.name));
                panel.block.classList.toggle('ao-wi-collapsed', rolled.has(panel.name));

                // Asked of the whole panel, not of this header. A widget can gain a real
                // section header *after* we have already given it one of our slim bars —
                // the heading arrives with the second morph — and a per-header check then
                // hands out a second set of tools, six icons in one panel.
                if (panel.block.querySelector('.ao-wi-tools')) return;

                // If a real heading has since appeared, the stand-in is no longer wanted.
                if (header !== panel.block.querySelector('.ao-wi-bar')) {
                    panel.block.querySelector('.ao-wi-bar')?.remove();
                }

                header.classList.add('ao-wi-header');
                header.setAttribute('title', 'Drag to move this panel');
                // A div is not focusable, and a dashboard that can only be rearranged with a
                // mouse is not the feature that was asked for.
                header.setAttribute('tabindex', '0');
                header.setAttribute('role', 'button');
                header.setAttribute('aria-label', 'Move this panel with the left and right arrow keys');
                header.append(buildTools(panel, title));
            });

            // Rebuilt only when the roster changes — otherwise just re-tick the boxes, so
            // that a checkbox the admin is looking at does not get replaced under the cursor.
            const key = ordered.map((panel) => panel.name).join('|');

            if (menu && key !== menuKey) {
                menuKey = key;
                menu.textContent = '';
                ordered.forEach((panel) => menu.append(
                    buildMenuRow(panel, titleOf(panel.root, panel.name)),
                ));
            } else if (menu) {
                ordered.forEach((panel) => {
                    const box = menu.querySelector(`input[data-ao-box="${CSS.escape(panel.name)}"]`);
                    if (box) box.checked = !hidden.has(panel.name);
                });
            }

            // Lift first, then reveal whichever one is now in the page — after the lift the
            // gear is no longer inside `mount`, so looking for it there would find nothing.
            lift();
            document.querySelector('[data-ao-settings]')?.removeAttribute('hidden');

            // Watch every child for size changes (observing twice is a no-op), and pack now.
            for (const child of grid.children) sizes.observe(child);
            pack();
        };

        // The observer must not see its own work, or `decorate` would trigger the tick that
        // runs `decorate`. Disconnecting across the call is what makes that terminate.
        let observer = null;
        let timer = null;

        // Declared here, not in the drag section below: sync() consults it and runs before
        // that section does — a later `let` would be a temporal-dead-zone crash on boot.
        let dragged = null;
        let before = null;

        const watch = () => observer?.observe(grid, { childList: true, subtree: true });

        const sync = () => {
            // Never while a drag is in flight. Every insert the drag makes wakes the
            // observer, and 100ms later decorate() would lay the grid back out to the
            // *saved* order — snatching the panel back out from under the cursor, which is
            // exactly what a drag that "isn't smooth" feels like. The drop calls sync()
            // itself once the new order is the saved order.
            if (dragged) return;

            observer?.disconnect();

            try {
                decorate();
            } catch (error) {
                // A broken panel must not take the dashboard's chrome with it.
                console.error('AdminOps dashboard:', error);
            } finally {
                watch();
            }
        };

        observer = new MutationObserver(() => {
            clearTimeout(timer);
            timer = setTimeout(sync, 100);
        });

        sync();

        function buildTools(panel, title) {
            const wrap = document.createElement('span');
            wrap.className = 'ao-wi-tools';

            // Drawn, not typed. These were text glyphs — ↻ ▲ ✕ — and a glyph is at the mercy
            // of whatever font resolves it: different weights, different baselines, different
            // sizes on Windows and on a Mac, and no way to make three of them look like one
            // set. These are strokes on the same 16-unit grid at the same width, so they line
            // up with each other and with Filament's own outline icons.
            const icon = (paths) => `<svg viewBox="0 0 16 16" width="14" height="14" fill="none"`
                + ` stroke="currentColor" stroke-width="1.5" stroke-linecap="round"`
                + ` stroke-linejoin="round" aria-hidden="true">${paths}</svg>`;

            const button = (label, glyph, onClick) => {
                const el = document.createElement('button');
                el.type = 'button';
                el.className = 'ao-wi-tool';
                el.title = `${label} — ${title}`;
                el.innerHTML = glyph;
                el.append(Object.assign(document.createElement('span'), {
                    className: 'ao-sr',
                    textContent: `${label} ${title}`,
                }));
                // The header is the drag handle; a press on a button must not start one.
                el.addEventListener('mousedown', (event) => event.stopPropagation());
                el.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    onClick(el);
                });

                return el;
            };

            wrap.append(
                // Every widget is its own Livewire component, so "refresh" is the component
                // refreshing itself — no second endpoint, and no view rendered twice.
                button('Refresh', icon('<path d="M2.6 8a5.4 5.4 0 0 1 9.2-3.8L14 6.4"/>'
                    + '<path d="M13.4 8a5.4 5.4 0 0 1-9.2 3.8L2 9.6"/>'
                    + '<path d="M14 3.2v3.2h-3.2"/><path d="M2 12.8V9.6h3.2"/>'), (el) => {
                    el.classList.add('ao-wi-spin');

                    // `$wire`, not the component: `Livewire.all()` returns the raw component
                    // objects, and `$refresh` lives on the proxy. Called on the component it
                    // is simply `undefined`, so the button spun and refreshed nothing.
                    const done = () => el.classList.remove('ao-wi-spin');
                    const refresh = panel.component.$wire?.$refresh?.();

                    refresh?.then ? refresh.then(done, done) : setTimeout(done, 600);
                }),
                button('Collapse', icon('<path d="M4.2 9.8 8 6l3.8 3.8"/>'), () => {
                    const isCollapsed = panel.block.classList.toggle('ao-wi-collapsed');

                    // The set the observer reapplies from, so a poll does not unroll it.
                    isCollapsed ? rolled.add(panel.name) : rolled.delete(panel.name);
                    rememberCollapsed([...rolled]);
                }),
                button('Hide', icon('<path d="M4.2 4.2 11.8 11.8"/><path d="M11.8 4.2 4.2 11.8"/>'), () => {
                    hidden.add(panel.name);
                    panel.block.classList.add('ao-wi-hidden');

                    const box = menu?.querySelector(`input[data-ao-box="${CSS.escape(panel.name)}"]`);
                    if (box) box.checked = false;

                    tools.call('toggleHidden', panel.name);
                }),
            );

            return wrap;
        }

        function buildMenuRow(panel, title) {
            const row = document.createElement('li');
            const label = document.createElement('label');
            const box = document.createElement('input');

            box.type = 'checkbox';
            box.checked = !hidden.has(panel.name);
            box.dataset.aoBox = panel.name;
            box.addEventListener('change', () => {
                box.checked ? hidden.delete(panel.name) : hidden.add(panel.name);
                panel.block.classList.toggle('ao-wi-hidden', !box.checked);
                tools.call('toggleHidden', panel.name);
            });

            label.append(box, document.createTextNode(' ' + title));
            row.append(label);

            return row;
        }

        // ── The settings menu ────────────────────────────────────────────────────────
        // Delegated from the document rather than bound to the button, and registered once
        // for the life of the page. Binding to the element meant the gear worked only for as
        // long as that exact node survived — and it does not: it is moved into the page
        // header, and anything that re-renders the widget replaces it. A handler that finds
        // the button at click time cannot go stale, whatever happens to the DOM.
        if (!window.aoDashMenuBound) {
            window.aoDashMenuBound = true;

            document.addEventListener('click', (event) => {
                const button = event.target.closest('[data-ao-settings-button]');
                const menu = document.querySelector('[data-ao-menu]');

                if (!menu) return;

                if (button) {
                    const open = menu.hasAttribute('hidden');
                    menu.toggleAttribute('hidden', !open);
                    button.setAttribute('aria-expanded', String(open));
                    event.stopPropagation();

                    return;
                }

                // A click inside the menu is a checkbox, not a dismissal.
                if (menu.hasAttribute('hidden') || event.target.closest('[data-ao-settings]')) return;

                menu.setAttribute('hidden', '');
                document.querySelector('[data-ao-settings-button]')?.setAttribute('aria-expanded', 'false');
            });

            // Escape closes it, as it closes every other menu in the panel.
            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape') return;

                const menu = document.querySelector('[data-ao-menu]');
                if (!menu || menu.hasAttribute('hidden')) return;

                menu.setAttribute('hidden', '');
                const button = document.querySelector('[data-ao-settings-button]');
                button?.setAttribute('aria-expanded', 'false');
                button?.focus();
            });
        }

        // ── Dragging ─────────────────────────────────────────────────────────────────
        // By the heading, as the reference does (`handle: '.panel-title'`). A widget full
        // of links and buttons cannot be draggable everywhere or nothing in it is clickable.
        // (`dragged` and `before` are declared up by the observer, which reads them.)
        const currentOrder = () => Array.from(grid.querySelectorAll(':scope > [data-ao-widget]'))
            .map((root) => root.dataset.aoWidget);

        // Save, and keep the list `decorate` lays out from in step — otherwise the next
        // observer tick would put the panel straight back where it was dragged from.
        const persistOrder = () => {
            const now = currentOrder();
            order.splice(0, order.length, ...now);
            tools.call('saveOrder', now);

            return now;
        };

        // On hover, not on mousedown — and that is the whole reason dragging did not work.
        //
        // Chrome decides whether a gesture is a drag when the button goes *down*: an element
        // that becomes draggable during its own `mousedown` is already too late, and no
        // `dragstart` is ever fired. (The handlers below were fine all along — synthetic drag
        // events reordered the dashboard correctly. Nothing was ever starting them.)
        //
        // So the panel under the pointer is made draggable as soon as the pointer is over its
        // heading, and only one panel is draggable at a time. Interactive things in the
        // heading are excluded, or the tool buttons could not be clicked.
        grid.addEventListener('mouseover', (event) => {
            const header = event.target.closest('.ao-wi-header');
            const interactive = event.target.closest('a, button, input, select, canvas');
            const root = header && !interactive ? header.closest('[data-ao-widget]') : null;

            for (const el of grid.querySelectorAll('[data-ao-widget][draggable]')) {
                if (el !== root && el !== dragged) el.removeAttribute('draggable');
            }

            root?.setAttribute('draggable', 'true');
        });

        grid.addEventListener('dragstart', (event) => {
            const root = event.target.closest('[data-ao-widget]');
            if (!root || root.getAttribute('draggable') !== 'true') return;

            dragged = root;
            before = currentOrder().join();
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', root.dataset.aoWidget);
            root.classList.add('ao-wi-dragging');
        });

        // FLIP: record where every panel is, make the change, then let each panel *slide*
        // from its old place to its new one. Without it a reorder is a teleport — the grid
        // reflows in one frame — and dragging feels like shuffling cards, not moving panels.
        // The dragged panel itself is excluded: the browser is already drawing its ghost.
        const flip = (mutate) => {
            const kids = [...grid.children].filter((el) => el !== dragged && el.getClientRects().length);
            const before = new Map(kids.map((el) => [el, el.getBoundingClientRect()]));

            mutate();

            for (const el of kids) {
                const a = before.get(el);
                const b = el.getBoundingClientRect();
                const dx = a.left - b.left;
                const dy = a.top - b.top;

                if (!dx && !dy) continue;

                el.style.transition = 'none';
                el.style.transform = `translate(${dx}px, ${dy}px)`;
                void el.offsetWidth; // commit the starting position before animating away from it
                el.style.transition = 'transform 170ms ease';
                el.style.transform = '';
                el.addEventListener('transitionend', () => { el.style.transition = ''; }, { once: true });
            }
        };

        grid.addEventListener('dragover', (event) => {
            if (!dragged) return;

            const over = event.target.closest('[data-ao-widget]');
            if (!over || over === dragged || over.parentElement !== grid) return;

            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';

            // A grid wraps, so "after" is right-of *or* below — comparing the pointer with
            // the middle of the box on both axes puts the panel where it looks like it will go.
            const box = over.getBoundingClientRect();
            const after = (event.clientY - box.top) / box.height > 0.5
                || (event.clientX - box.left) / box.width > 0.5;

            const ref = after ? over.nextSibling : over;

            // dragover fires on every mouse move; only act when the drop point actually
            // changed, or the grid re-lays-out dozens of times a second for nothing.
            if (ref === dragged || ref === dragged.nextSibling) return;

            flip(() => grid.insertBefore(dragged, ref));
        });

        grid.addEventListener('drop', (event) => event.preventDefault());

        // Chrome fires no `drop` unless `dragover` was cancelled over the exact element
        // released on; `dragend` always runs.
        grid.addEventListener('dragend', () => {
            if (!dragged) return;

            dragged.classList.remove('ao-wi-dragging');
            dragged.removeAttribute('draggable');

            if (currentOrder().join() !== before) persistOrder();

            dragged = null;

            // The observer stayed silent for the whole drag; run the pass it skipped, now
            // that the DOM order and the saved order agree again.
            sync();
        });

        // Keyboard equivalent, for the same reason as the catalogue: the HTML5 drag API
        // does nothing at all on a touch screen, and a dashboard nobody can rearrange
        // without a mouse is not the feature that was asked for.
        grid.addEventListener('keydown', (event) => {
            const header = event.target.closest('.ao-wi-header');
            if (!header) return;

            const step = { ArrowLeft: -1, ArrowRight: 1 }[event.key];
            if (!step) return;

            const root = header.closest('[data-ao-widget]');
            const roots = Array.from(grid.querySelectorAll(':scope > [data-ao-widget]'));
            const target = roots.indexOf(root) + step;
            if (target < 0 || target >= roots.length) return;

            event.preventDefault();
            grid.insertBefore(root, step < 0 ? roots[target] : roots[target].nextSibling);
            header.focus();
            persistOrder();
        });
    };

    // Livewire has to have booted before any of this: every panel is one of its components
    // and `Livewire.all()` is how they are found. Under `->spa()` the dashboard can also
    // arrive by `wire:navigate`, when it already has.
    boot();

    if (!window.aoDashBound) {
        window.aoDashBound = true;
        document.addEventListener('livewire:initialized', () => boot());
        document.addEventListener('livewire:navigated', () => boot());
    }
    })();
</script>
</x-filament-widgets::widget>
