{{--
    Service Addons, to the reference screenshot: the Search/Filter framed panel, the navy
    grid — ID, Addon, Product/Service, Client Name, Billing Cycle, Price, Next Due Date,
    Status — With Selected: Send Message, and the Hide Inactive pill. Add Addon attaches a
    new one; billing rides the parent service.
--}}
<x-filament-panels::page>
    <div class="ao-mu ao-sa">
        <div class="ao-tx-tabs">
            <button type="button" class="ao-mu-tab {{ $filter ? 'ao-on' : '' }}" wire:click="toggleFilter">Search/Filter</button>
            <button type="button" class="ao-mu-tab {{ $adding ? 'ao-on' : '' }}" wire:click="toggleAdding">Add Addon</button>
        </div>

        @if ($categoryEmpty)
            <p class="ao-sa-hint">
                The <b>Service Addons</b> product category is empty. Define addons there first —
                ordinary products with a monthly price (e.g. "Additional IPv6 /64", "Extra 100 Mbps") —
                then attach them to services here.
            </p>
        @endif

        @if ($filter)
            {{-- The reference's two-column framed filter; every control is live. --}}
            <div class="ao-stf ao-stf-two">
                <label class="ao-stf-row">
                    <span>Addon</span>
                    <select class="ao-stf-small" wire:model.live="addon">
                        <option value="">Any</option>
                        @foreach ($catalogue as $product)
                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ao-stf-row">
                    <span>Server</span>
                    <select class="ao-stf-small" wire:model.live="server">
                        <option value="">Any</option>
                        @foreach ($servers as $row)
                            <option value="{{ $row->id }}">{{ $row->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ao-stf-row">
                    <span>Product/Service</span>
                    <select class="ao-stf-small" wire:model.live="parentProduct">
                        <option value="">Any</option>
                        @foreach ($parentProducts as $product)
                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ao-stf-row">
                    <span>Billing Cycle</span>
                    <select class="ao-stf-small" wire:model.live="cycle">
                        <option value="">Any</option>
                        <option>Monthly</option>
                        <option>Quarterly</option>
                        <option>Semi-Annually</option>
                        <option>Annually</option>
                        <option>One Time</option>
                    </select>
                </label>
                <label class="ao-stf-row">
                    <span>Status</span>
                    <select class="ao-stf-small" wire:model.live="status">
                        <option value="">Any</option>
                        <option value="pending">Pending</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                        <option value="cancelled">Terminated</option>
                    </select>
                </label>
                <label class="ao-stf-row">
                    <span>Client Name</span>
                    <input type="text" class="ao-stf-mid" wire:model.live.debounce.500ms="clientName" placeholder="Name or email">
                </label>
            </div>
        @endif

        @if ($adding)
            <form class="ao-anc-card" wire:submit.prevent="attach">
                <label class="ao-anc-row">
                    <span>Service</span>
                    <select class="ao-w-45" wire:model="parentId" required>
                        <option value="">Pick the service the addon extends</option>
                        @foreach ($parents as $parent)
                            <option value="{{ $parent->id }}">
                                #{{ $parent->id }} · {{ $parent->product?->name }} ·
                                {{ trim(($parent->user->first_name ?? '') . ' ' . ($parent->user->last_name ?? '')) ?: $parent->user?->email }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label class="ao-anc-row">
                    <span>Addon</span>
                    <select class="ao-w-40" wire:model.live="productId" required>
                        <option value="">Pick an addon from the catalogue</option>
                        @foreach ($catalogue as $product)
                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ao-anc-row">
                    <span>Quantity</span>
                    <input type="number" class="ao-w-25" min="1" wire:model="quantity" required>
                </label>
                <label class="ao-anc-row">
                    <span>Recurring Price</span>
                    <input type="text" class="ao-w-25" inputmode="decimal" wire:model="price" placeholder="0.00" required>
                </label>
                <div class="ao-pr-center">
                    <button type="submit" class="ao-find-go">Attach Addon</button>
                </div>
            </form>

            @if ($errors->any())
                <ul class="ao-anc-errors">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
        @endif

        <div class="ao-mu-line">
            <span>{{ number_format($addons->count()) }} Records Found</span>
            <span class="ao-mu-line-right">
                <button type="button" class="ao-mu-toggle {{ $hideInactive ? 'ao-on' : '' }}" wire:click="toggleInactive">
                    <i>{{ $hideInactive ? 'ON' : 'OFF' }}</i>
                    Hide Inactive ({{ number_format($hiddenCount) }})
                </button>
            </span>
        </div>

        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th class="ao-mu-check"><input type="checkbox" data-ao-check-all></th>
                    <th>ID</th>
                    <th>Addon</th>
                    <th>Product/Service</th>
                    <th>Client Name</th>
                    <th>Billing Cycle</th>
                    <th>Price</th>
                    <th>Next Due Date</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($addons as $addon)
                    <tr>
                        <td class="ao-mu-check"><input type="checkbox" data-ao-check value="{{ $addon->parent->user?->email }}"></td>
                        <td>{{ $addon->service->id }}</td>
                        <td class="ao-mu-left">{{ $addon->service->product?->name ?? '—' }}</td>
                        <td class="ao-mu-left">
                            <a href="{{ \App\Admin\Resources\ServiceResource::getUrl('edit', ['record' => $addon->parent->id]) }}">
                                #{{ $addon->parent->id }} · {{ $addon->parent->product?->name }}
                            </a>
                        </td>
                        <td>
                            <a href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $addon->parent->user_id]) }}">
                                {{ trim(($addon->parent->user->first_name ?? '') . ' ' . ($addon->parent->user->last_name ?? '')) ?: ($addon->parent->user->email ?? '—') }}
                            </a>
                        </td>
                        <td>{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ProductsServices::cycle($addon->service) }}</td>
                        <td>${{ number_format((float) $addon->service->price, 2) }} {{ $addon->service->currency_code }}</td>
                        <td>{{ $addon->service->expires_at?->format('m/d/Y') ?? '-' }}</td>
                        <td><span class="ao-mu-status ao-mu-st-{{ $addon->service->status }}">{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ProductsServices::statusLabel($addon->service->status) }}</span></td>
                        <td class="ao-mu-actions">
                            @if ($addon->service->status !== 'cancelled')
                                <button type="button" class="ao-mo-delete" title="Cancel addon"
                                    wire:click="$set('confirmingCancel', '{{ $addon->id }}')">
                                    <x-filament::icon icon="ri-indeterminate-circle-fill" class="ao-mu-cell-icon" />
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="ao-mu-selected">
            With Selected:
            <button type="button" data-ao-send-message>Send Message</button>
        </div>

        <nav class="ao-mu-pages">
            <button type="button" disabled>&laquo; Previous Page</button>
            <span class="ao-mu-page-now">1</span>
            <button type="button" disabled>Next Page &raquo;</button>
        </nav>

        @if ($confirmingCancel)
            <div class="ao-mud-overlay" wire:click.self="$set('confirmingCancel', null)">
                <div class="ao-mud ao-mud-sm" role="alertdialog" aria-modal="true">
                    <div class="ao-mud-head">
                        Are you sure?
                        <button type="button" wire:click="$set('confirmingCancel', null)" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text">
                        <p>Are you sure you wish to cancel this addon?</p>
                        <p>It stops renewing; the parent service is untouched.</p>
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="$set('confirmingCancel', null)">Cancel</button>
                            <button type="button" class="ao-mud-delete" wire:click="cancel">OK</button>
                        </span>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <script>
        (() => {
            const root = document.currentScript.closest('.fi-page') ?? document;

            root.addEventListener('change', (event) => {
                if (!event.target.matches('[data-ao-check-all]')) return;
                for (const box of root.querySelectorAll('[data-ao-check]')) box.checked = event.target.checked;
            });

            root.addEventListener('click', (event) => {
                if (!event.target.closest('[data-ao-send-message]')) return;
                const picked = [...root.querySelectorAll('[data-ao-check]:checked')].map((b) => b.value).filter(Boolean);
                if (!picked.length) { alert('Tick at least one row first.'); return; }
                window.location.href = 'mailto:' + encodeURIComponent([...new Set(picked)].join(','));
            });
        })();
    </script>
</x-filament-panels::page>
