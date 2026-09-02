{{--
    Products/Services — the catalogue, ordered by dragging.

    Nested lists rather than the reference's one flat table, because Paymenter's categories
    nest and WHMCS's groups do not. A group is a bordered panel with the reference's grey
    heading band; its products are the table inside it. It reads as the reference and it can
    show a child group without pretending the tree is flat.

    Drag-and-drop is written here, in plain JS, for the reason in styles.blade.php: the admin
    theme does not scan `extensions/`, and there is no build step in the deployment path. It
    is the HTML5 drag API rather than a library — nothing to bundle, nothing to version.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        {{-- The reference's three buttons, in its order (issue #35). --}}
        <div class="ao-tx-tabs">
            @if ($urls['newCategory'])
                <a class="ao-mu-tab" href="{{ $urls['newCategory'] }}">&#10010; Create a New Group</a>
            @endif
            @if ($urls['newProduct'])
                <a class="ao-mu-tab" href="{{ $urls['newProduct'] }}">&#10010; Create a New Product</a>
            @endif
            <button type="button" class="ao-mu-tab {{ $duplicating ? 'ao-on' : '' }}" wire:click="toggleDuplicating">Duplicate a Product</button>
        </div>

        @if ($duplicating)
            <form class="ao-anc-card" wire:submit.prevent="duplicate">
                <label class="ao-anc-row">
                    <span>Product to Duplicate</span>
                    <select class="ao-w-40" wire:model="duplicateSource" required>
                        <option value="">Choose a product</option>
                        @foreach ($allProducts as $option)
                            <option value="{{ $option->id }}">{{ $option->name }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="ao-pr-center"><button type="submit" class="ao-find-go">Duplicate</button></div>
            </form>
            @if ($errors->any())
                <ul class="ao-anc-errors">
                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            @endif
        @endif

    @if (empty($tree))
        <div class="ao-catalogue-empty">
            <p>No product groups yet.</p>
            <p>A group has to exist before a product can go in one.</p>
        </div>
    @else
        <div class="ao-catalogue ao-ct" data-ao-catalogue>
            {{-- The reference's one navy header over the whole catalogue. --}}
            <div class="ao-ct-row ao-ct-head">
                <span>Product Name</span>
                <span>Type</span>
                <span>Pay Type</span>
                <span>Stock</span>
                <span>Auto Setup</span>
                <span></span>
            </div>

            <ul class="ao-cat-list" data-ao-scope="category" data-ao-parent="">
                @foreach ($tree as $node)
                    @include('adminops::pages.catalogue-group', ['node' => $node])
                @endforeach
            </ul>

            <p class="ao-catalogue-count">
                {{ count($tree) }} top-level {{ Str::plural('group', count($tree)) }} ·
                {{ $productCount }} {{ Str::plural('product', $productCount) }}
            </p>
        </div>

        @if ($confirmKind)
            <div class="ao-mud-overlay" wire:click.self="$set('confirmKind', null)">
                <div class="ao-mud ao-mud-sm" role="alertdialog" aria-modal="true">
                    <div class="ao-mud-head">
                        Are you sure?
                        <button type="button" wire:click="$set('confirmKind', null)" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text">
                        @if ($confirmKind === 'product')
                            <p>Are you sure you wish to delete this product?</p>
                            <p>A product with services attached will be refused.</p>
                        @else
                            <p>Are you sure you wish to delete this group?</p>
                            <p>A group that still holds products will be refused.</p>
                        @endif
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="$set('confirmKind', null)">Cancel</button>
                            <button type="button" class="ao-mud-delete" wire:click="runDelete">OK</button>
                        </span>
                    </div>
                </div>
            </div>
        @endif

        @if ($canReorder)
            <script>
                (() => {
                const boot = () => {
                    const root = document.querySelector('[data-ao-catalogue]');
                    if (!root || root.dataset.aoBound) return;

                    const id = root.closest('[wire\\:id]')?.getAttribute('wire:id');
                    const wire = id && window.Livewire ? Livewire.find(id) : null;
                    if (!wire) return;

                    root.dataset.aoBound = '1';

                    // A row is only draggable while the pointer is actually on its handle.
                    // Left draggable permanently, every product name became an unintended
                    // drag instead of a click through to the product.
                    root.addEventListener('mousedown', (event) => {
                        const grip = event.target.closest('[data-ao-grip]');
                        if (!grip) return;
                        grip.closest('[data-ao-id]')?.setAttribute('draggable', 'true');
                    });

                    const release = (row) => row?.removeAttribute('draggable');

                    let dragged = null;
                    let list = null;
                    let before = null;

                    const order = (container) => Array.from(
                        container.querySelectorAll(':scope > [data-ao-id]'),
                    ).map((row) => row.dataset.aoId);

                    root.addEventListener('dragstart', (event) => {
                        const row = event.target.closest('[data-ao-id]');
                        if (!row || row.getAttribute('draggable') !== 'true') return;

                        // Without this a product's dragstart also starts its group's, and the
                        // browser drags whichever the outermost listener saw last.
                        event.stopPropagation();

                        dragged = row;
                        list = row.parentElement;
                        before = order(list).join();

                        event.dataTransfer.effectAllowed = 'move';
                        // Firefox will not start a drag at all without payload.
                        event.dataTransfer.setData('text/plain', row.dataset.aoId);
                        row.classList.add('ao-dragging');
                    });

                    root.addEventListener('dragover', (event) => {
                        if (!dragged) return;

                        const over = event.target.closest('[data-ao-id]');
                        // Only within the list the drag started in: a product cannot be
                        // dropped into another group here, and a group cannot become a child.
                        if (!over || over === dragged || over.parentElement !== list) return;

                        event.preventDefault();
                        event.dataTransfer.dropEffect = 'move';

                        const box = over.getBoundingClientRect();
                        const after = (event.clientY - box.top) / box.height > 0.5;
                        list.insertBefore(dragged, after ? over.nextSibling : over);
                    });

                    // Chrome fires no `drop` unless `dragover` was cancelled over the exact
                    // element released on, so the save hangs off `dragend`, which always runs.
                    root.addEventListener('dragend', () => {
                        if (!dragged) return;

                        dragged.classList.remove('ao-dragging');
                        release(dragged);

                        const ids = order(list);
                        if (ids.join() !== before) save(list, ids);

                        dragged = null;
                        list = null;
                    });

                    root.addEventListener('drop', (event) => event.preventDefault());

                    // Keyboard equivalent. The handle is focusable, so the whole page works
                    // without a mouse — and on a touch screen, where HTML5 drag does nothing.
                    root.addEventListener('keydown', (event) => {
                        const grip = event.target.closest('[data-ao-grip]');
                        if (!grip) return;

                        const step = { ArrowUp: -1, ArrowDown: 1 }[event.key];
                        if (!step) return;

                        const row = grip.closest('[data-ao-id]');
                        const container = row.parentElement;
                        const rows = Array.from(container.querySelectorAll(':scope > [data-ao-id]'));
                        const target = rows.indexOf(row) + step;
                        if (target < 0 || target >= rows.length) return;

                        event.preventDefault();
                        container.insertBefore(
                            row,
                            step < 0 ? rows[target] : rows[target].nextSibling,
                        );
                        grip.focus();
                        save(container, order(container));
                    });

                    const save = (container, ids) => {
                        container.classList.add('ao-saving');

                        const done = () => container.classList.remove('ao-saving');

                        if (container.dataset.aoScope === 'product') {
                            wire.call('reorderProducts', Number(container.dataset.aoCategory), ids)
                                .then(done, done);

                            return;
                        }

                        const parent = container.dataset.aoParent;

                        wire.call('reorderCategories', parent === '' ? null : Number(parent), ids)
                            .then(done, done);
                    };
                };

                // This runs while the page is being parsed, so Livewire may not have booted
                // yet — and under `->spa()` the page can also arrive by `wire:navigate`, when
                // it has. Both are covered; `data-ao-bound` makes the second call a no-op.
                //
                // The IIFE is not decoration: `wire:navigate` re-executes this script in the
                // same context, and a top-level `const` would throw "already declared" on the
                // second visit. The listeners are registered once for the same reason.
                boot();

                if (!window.aoCatalogueBound) {
                    window.aoCatalogueBound = true;
                    document.addEventListener('livewire:init', boot);
                    document.addEventListener('livewire:navigated', boot);
                }
                })();
            </script>
        @endif
    @endif
    </div>
</x-filament-panels::page>
