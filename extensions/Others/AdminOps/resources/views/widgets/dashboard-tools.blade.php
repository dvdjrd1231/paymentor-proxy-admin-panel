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

<div class="ao-dash-tools" data-ao-dash>
    <div class="ao-dash-settings" data-ao-settings hidden>
        <button type="button" class="ao-dash-settings-button" data-ao-settings-button
            aria-haspopup="true" aria-expanded="false" title="Choose panels">
            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" width="16" height="16">
                <path d="M8.34 1.8a1 1 0 0 1 .98-.8h1.36a1 1 0 0 1 .98.8l.2 1a6.9 6.9 0 0 1 1.2.7l.96-.33a1 1 0 0 1 1.2.45l.68 1.17a1 1 0 0 1-.22 1.26l-.77.66a6.9 6.9 0 0 1 0 1.38l.77.66a1 1 0 0 1 .22 1.26l-.68 1.17a1 1 0 0 1-1.2.45l-.97-.33c-.37.28-.77.51-1.2.7l-.2 1a1 1 0 0 1-.97.8H9.32a1 1 0 0 1-.98-.8l-.2-1a6.9 6.9 0 0 1-1.2-.7l-.96.33a1 1 0 0 1-1.2-.45l-.68-1.17a1 1 0 0 1 .22-1.26l.77-.66a6.9 6.9 0 0 1 0-1.38l-.77-.66a1 1 0 0 1-.22-1.26l.68-1.17a1 1 0 0 1 1.2-.45l.96.33c.37-.28.77-.51 1.2-.7l.2-1ZM10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/>
            </svg>
            <span class="sr-only">Choose which panels to show</span>
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

        // The reference puts this gear at the top right of the page, level with the
        // "Dashboard" heading — not in the grid. This widget has to *be* in the grid (it is
        // how it sorts first and finds its neighbours), so the gear is moved out of it into
        // Filament's page header once there is something to put in the menu.
        //
        // Moved rather than rendered there: a render hook cannot reach the widget's own
        // Livewire component, and the checkboxes have to call its methods.
        const lift = () => {
            const settings = mount.querySelector('[data-ao-settings]');
            const header = document.querySelector('.fi-header, .fi-page-header');

            if (!settings || !header || settings.dataset.aoLifted) return;

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
            const existing = root.querySelector(
                '.fi-sc-section-header, .fi-wi-chart-header, .fi-wi-stats-overview-header',
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
                '.fi-sc-section-header-heading, .fi-wi-chart-header-heading, h1, h2, h3',
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

                if (header.querySelector('.ao-wi-tools')) return;

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
                    const box = menu.querySelector(`input[data-ao-widget="${CSS.escape(panel.name)}"]`);
                    if (box) box.checked = !hidden.has(panel.name);
                });
            }

            mount.querySelector('[data-ao-settings]')?.removeAttribute('hidden');
            lift();
        };

        // The observer must not see its own work, or `decorate` would trigger the tick that
        // runs `decorate`. Disconnecting across the call is what makes that terminate.
        let observer = null;
        let timer = null;

        const watch = () => observer?.observe(grid, { childList: true, subtree: true });

        const sync = () => {
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

            const button = (label, glyph, onClick) => {
                const el = document.createElement('button');
                el.type = 'button';
                el.className = 'ao-wi-tool';
                el.title = `${label} — ${title}`;
                el.innerHTML = `<span aria-hidden="true">${glyph}</span>`;
                el.append(Object.assign(document.createElement('span'), {
                    className: 'sr-only',
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
                button('Refresh', '&#8635;', (el) => {
                    el.classList.add('ao-wi-spin');

                    // `$wire`, not the component: `Livewire.all()` returns the raw component
                    // objects, and `$refresh` lives on the proxy. Called on the component it
                    // is simply `undefined`, so the button spun and refreshed nothing.
                    const done = () => el.classList.remove('ao-wi-spin');
                    const refresh = panel.component.$wire?.$refresh?.();

                    refresh?.then ? refresh.then(done, done) : setTimeout(done, 600);
                }),
                button('Collapse', '&#9650;', () => {
                    const isCollapsed = panel.block.classList.toggle('ao-wi-collapsed');

                    // The set the observer reapplies from, so a poll does not unroll it.
                    isCollapsed ? rolled.add(panel.name) : rolled.delete(panel.name);
                    rememberCollapsed([...rolled]);
                }),
                button('Hide', '&#10005;', () => {
                    hidden.add(panel.name);
                    panel.block.classList.add('ao-wi-hidden');

                    const box = menu?.querySelector(`input[data-ao-widget="${CSS.escape(panel.name)}"]`);
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
            box.dataset.aoWidget = panel.name;
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
        const settingsButton = mount.querySelector('[data-ao-settings-button]');
        const settingsMenu = mount.querySelector('[data-ao-menu]');

        settingsButton?.addEventListener('click', (event) => {
            event.stopPropagation();
            const open = settingsMenu.hasAttribute('hidden');
            settingsMenu.toggleAttribute('hidden', !open);
            settingsButton.setAttribute('aria-expanded', String(open));
        });

        document.addEventListener('click', (event) => {
            if (!settingsMenu || settingsMenu.hasAttribute('hidden')) return;
            if (event.target.closest('[data-ao-settings]')) return;
            settingsMenu.setAttribute('hidden', '');
            settingsButton?.setAttribute('aria-expanded', 'false');
        });

        // ── Dragging ─────────────────────────────────────────────────────────────────
        // By the heading, as the reference does (`handle: '.panel-title'`). A widget full
        // of links and buttons cannot be draggable everywhere or nothing in it is clickable.
        let dragged = null;
        let before = null;

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

        grid.addEventListener('mousedown', (event) => {
            const header = event.target.closest('.ao-wi-header');
            if (!header || event.target.closest('a, button, input, select, canvas')) return;
            header.closest('[data-ao-widget]')?.setAttribute('draggable', 'true');
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

            grid.insertBefore(dragged, after ? over.nextSibling : over);
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
