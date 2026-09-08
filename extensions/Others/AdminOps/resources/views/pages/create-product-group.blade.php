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
            <label class="ao-anc-row ao-anc-row-wide">
                <span>Description</span>
                <textarea rows="4" wire:model="description"
                    placeholder="Shown on the storefront under the group's name."></textarea>
            </label>

            <div class="ao-pr-center ao-cpg-actions">
                <button type="submit" class="ao-find-go">Save Changes</button>
                <a class="ao-pg-btn" href="{{ $cancelUrl }}">Cancel Changes</a>
            </div>
        </form>
    </div>
</x-filament-panels::page>
