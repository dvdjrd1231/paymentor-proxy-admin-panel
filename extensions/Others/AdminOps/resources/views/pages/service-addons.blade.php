{{--
    Service Addons, to issue #7: addons attached to running services, each with its own
    recurring price, renewing on the parent's invoice. The catalogue is the "Service
    Addons" product category; this page lists instances and attaches new ones.
--}}
<x-filament-panels::page>
    <div class="ao-mu ao-sa">
        <div class="ao-tx-tabs">
            <button type="button" class="ao-mu-tab {{ $adding ? 'ao-on' : '' }}" wire:click="toggleAdding">Add Addon</button>
        </div>

        @if ($categoryEmpty)
            <p class="ao-sa-hint">
                The <b>Service Addons</b> product category is empty. Define addons there first —
                ordinary products with a monthly price (e.g. "Additional IPv6 /64", "Extra 100 Mbps") —
                then attach them to services here.
            </p>
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
            <span>{{ number_format($addons->count()) }} Addons</span>
        </div>

        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Addon</th>
                    <th>On Service</th>
                    <th>Client</th>
                    <th>Qty</th>
                    <th>Recurring</th>
                    <th>Next Due Date</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($addons as $addon)
                    <tr>
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
                        <td>{{ (int) $addon->service->quantity }}</td>
                        <td>${{ number_format((float) $addon->service->price, 2) }} {{ $addon->service->currency_code }}</td>
                        <td>{{ $addon->service->expires_at?->format('m/d/Y') ?? '-' }}</td>
                        <td><span class="ao-mu-status ao-mu-st-{{ $addon->service->status }}">{{ ucfirst($addon->service->status) }}</span></td>
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
                    <tr><td colspan="9" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

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
</x-filament-panels::page>
