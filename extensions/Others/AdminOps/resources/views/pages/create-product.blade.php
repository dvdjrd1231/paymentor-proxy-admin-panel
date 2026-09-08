{{--
    Create a New Product, to the reference's screen: group, name, URL, module and hidden,
    then Continue » into the product's own editor for pricing.

    Each row carries the reference's small grey hint under its label. The Product Type tiles
    are absent — see CreateProduct's docblock.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        <form class="ao-anc-card ao-cp-create" wire:submit.prevent="create">
            <label class="ao-anc-row">
                <span>
                    Product Group
                    <i>Which group the product is ordered from.
                        <a class="ao-link" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\CreateProductGroup::getUrl() }}">Create a new one</a>.</i>
                </span>
                <select wire:model="categoryId" required>
                    @foreach ($groups as $group)
                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                    @endforeach
                </select>
            </label>
            @error('categoryId') <p class="ao-anc-errors">{{ $message }}</p> @enderror

            <label class="ao-anc-row">
                <span>
                    Product Name
                    <i>The default display name for your new product</i>
                </span>
                <input type="text" wire:model.blur="name" required>
            </label>
            @error('name') <p class="ao-anc-errors">{{ $message }}</p> @enderror

            <label class="ao-anc-row">
                <span>
                    URL
                    <i>A friendly URL to use to link to this product.</i>
                </span>
                <span class="ao-anc-field ao-cpg-url">
                    <i>{{ $urlPrefix }}</i>
                    <input type="text" wire:model="slugValue">
                </span>
            </label>
            @error('slugValue') <p class="ao-anc-errors">{{ $message }}</p> @enderror

            <label class="ao-anc-row">
                <span>
                    Module
                    <i>Choose a module for automation</i>
                </span>
                <select wire:model="serverId">
                    <option value="">No Module</option>
                    @foreach ($servers as $server)
                        <option value="{{ $server->id }}">
                            {{ strcasecmp($server->name, $server->extension) === 0 ? $server->name : $server->name . ' (' . $server->extension . ')' }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="ao-anc-row">
                <span>
                    Create as Hidden
                    <i>A hidden product is not visible to end users</i>
                </span>
                <span class="ao-anc-field">
                    <input type="checkbox" wire:model="hidden">
                    <i>Hidden from the storefront</i>
                </span>
            </label>

            <div class="ao-pr-center ao-cpg-actions">
                <button type="submit" class="ao-find-go">Continue &raquo;</button>
                <a class="ao-pg-btn" href="{{ $cancelUrl }}">Cancel</a>
            </div>

            {{-- The reference closes with a MarketConnect note here. Ours says the one thing
                 that is actually true of the next screen. --}}
            <p class="ao-cp-note">
                Continue creates the product and opens its own page, where its plan, billing
                period and price are set. A product without a price cannot be ordered.
            </p>
        </form>
    </div>
</x-filament-panels::page>
