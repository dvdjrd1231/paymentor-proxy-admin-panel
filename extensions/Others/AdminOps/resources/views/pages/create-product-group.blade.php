{{--
    Create Group, to the reference's screen: labelled rows in one bordered card, Save
    Changes / Cancel Changes centred beneath. See CreateProductGroup's docblock for the
    reference fields this platform has no column behind.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        <form class="ao-anc-card" wire:submit.prevent="save">
            <label class="ao-anc-row">
                <span>Product Group Name</span>
                <input type="text" wire:model.blur="name" placeholder="eg. IPv6 Proxy Monthly Plans" required>
            </label>
            @error('name') <p class="ao-anc-errors">{{ $message }}</p> @enderror

            <label class="ao-anc-row">
                <span>URL</span>
                <span class="ao-anc-field ao-cpg-url">
                    <i>{{ $urlPrefix }}</i>
                    <input type="text" wire:model="slugValue" placeholder="ipv6-proxy-monthly-plans">
                </span>
            </label>
            @error('slugValue') <p class="ao-anc-errors">{{ $message }}</p> @enderror

            {{-- Ours, not the reference's: categories nest here and the storefront renders
                 children, so a create form that could not set the parent would make a child
                 group unreachable from this page. --}}
            <label class="ao-anc-row">
                <span>Parent Group</span>
                <select wire:model="parentId">
                    <option value="">None — a top-level group</option>
                    @foreach ($parents as $parent)
                        <option value="{{ $parent->id }}">{{ $parent->name }}</option>
                    @endforeach
                </select>
            </label>

            {{-- The storefront renders this where WHMCS would render its headline and
                 tagline; there is one field here rather than two. --}}
            <label class="ao-anc-row">
                <span>Product Group Headline</span>
                <input type="text" wire:model="headline" placeholder="eg. Select Your Perfect Plan">
            </label>

            <label class="ao-anc-row">
                <span>Product Group Tagline</span>
                <input type="text" wire:model="tagline"
                    placeholder="eg. With our 30 Day Money Back Guarantee You Can't Go Wrong!">
            </label>

            {{-- A normal two-column row: `.ao-anc-row-wide` collapses to a single column,
                 which left the label above a thin strip of textarea. --}}
            <label class="ao-anc-row ao-cpg-desc">
                <span>Description</span>
                <textarea rows="4" wire:model="description"
                    placeholder="Shown on the storefront under the group's name."></textarea>
            </label>

            {{-- The reference greys its Group Features box out until the group exists. Ours
                 says the same thing in the same slot. --}}
            <div class="ao-anc-row">
                <span>Group Features</span>
                <span class="ao-cpg-muted">You must save the product group for the first time before you can add features</span>
            </div>

            <label class="ao-anc-row">
                <span>Order Form Template</span>
                <select wire:model="orderForm">
                    @foreach (\Paymenter\Extensions\Others\AdminOps\Models\Meta::ORDER_FORMS as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            {{-- Saved as GatewayRule rows scoped to this group, so it is enforced at
                 checkout rather than remembered and ignored. Everything ticked means no
                 restriction. --}}
            <div class="ao-anc-row">
                <span class="ao-cpg-hint">
                    Available Payment Gateways
                    <i>Untick one to stop it being offered for this group's products.</i>
                </span>
                <span class="ao-cpg-gateways">
                    @forelse ($allGateways as $gateway)
                        <label class="ao-check">
                            <input type="checkbox" value="{{ $gateway->extension }}" wire:model="gateways">
                            <span>{{ $gateway->name }}</span>
                        </label>
                    @empty
                        <i>No payment gateways are set up yet.</i>
                    @endforelse
                </span>
            </div>

            <label class="ao-anc-row">
                <span>Hidden</span>
                <span class="ao-anc-field">
                    <input type="checkbox" wire:model="hidden">
                    <i>Check if this is a hidden group</i>
                </span>
            </label>

            @error('parentId') <p class="ao-anc-errors">{{ $message }}</p> @enderror

            <div class="ao-pr-center ao-cpg-actions">
                <button type="submit" class="ao-find-go">Save Changes</button>
                <a class="ao-pg-btn" href="{{ $cancelUrl }}">Cancel Changes</a>
            </div>
        </form>
    </div>
</x-filament-panels::page>
