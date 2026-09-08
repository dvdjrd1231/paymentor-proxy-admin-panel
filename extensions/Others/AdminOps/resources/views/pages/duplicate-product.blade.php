{{--
    Duplicate a Product, to the reference's screen: the product to copy, the name of the
    copy, Continue. Two rows in one card, exactly as the reference has it.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        <form class="ao-anc-card" wire:submit.prevent="duplicate">
            <label class="ao-anc-row">
                <span>Existing Product</span>
                <select wire:model="sourceId" required>
                    @forelse ($products as $product)
                        {{-- "Group - Product", the reference's own labelling, so two
                             similarly-named plans in different groups are told apart. --}}
                        <option value="{{ $product->id }}">
                            {{ $product->category?->name ? $product->category->name . ' - ' : '' }}{{ $product->name }}
                        </option>
                    @empty
                        <option value="">There are no products to copy yet</option>
                    @endforelse
                </select>
            </label>
            @error('sourceId') <p class="ao-anc-errors">{{ $message }}</p> @enderror

            <label class="ao-anc-row">
                <span>New Product Name</span>
                <input type="text" wire:model="name" required>
            </label>
            @error('name') <p class="ao-anc-errors">{{ $message }}</p> @enderror

            <div class="ao-pr-center ao-cpg-actions">
                <button type="submit" class="ao-find-go" @disabled($products->isEmpty())>Continue &raquo;</button>
                <a class="ao-pg-btn" href="{{ $cancelUrl }}">Cancel</a>
            </div>

            <p class="ao-cp-note">
                The copy takes the original's plans, prices, settings and configurable
                options, and lands in the same group at the end of the list.
            </p>
        </form>
    </div>
</x-filament-panels::page>
