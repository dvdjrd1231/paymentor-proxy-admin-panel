{{-- One group, to issues #35 and #41: the reference's grey band — drag handle at its left,
     "Group Name: X", edit and delete icons at its right — then the group's products as rows
     of the one shared grid, each with its own drag/edit/delete icons in the last column. --}}
@php
    $category = $node['category'];
    $products = $category->products;
    $categoryUrl = $this->categoryUrl($category);
@endphp

<li class="ao-cat" data-ao-id="{{ $category->id }}" wire:key="ao-cat-{{ $category->id }}">
    <div class="ao-ct-band">
        {{-- The reference leads the band with the group's drag handle — the four-way move
             cross immediately before "Group Name:" (Leandro, 2026-09-07, screenshots of
             both his own catalogue and the demo). It used to lead with a boxed ⊕ that
             started a product in the group; that moves to the icon cluster on the right,
             where it is still one click and no longer occupies the reference's handle
             position. --}}
        @if ($canReorderCategories)
            <span class="ao-grip ao-ct-band-grip" data-ao-grip role="button" tabindex="0"
                aria-label="Reorder the group {{ $category->name }}. Drag, or use the arrow keys."
                title="Drag to reorder">&#10021;</span>
        @endif

        <span class="ao-ct-band-name">
            <b>Group Name:</b> {{ $category->name }}
            {{-- Marked the same way a hidden product is, so the flag is visible from the
                 catalogue rather than only inside the group's own form. --}}
            @if (($meta['category'][$category->id]['hidden'] ?? null) === '1')
                <i class="ao-cat-flag">(Hidden)</i>
            @endif
        </span>

        {{-- Two icons, as the reference's group row has: edit and delete. An
             add-a-product-to-this-group link used to sit here as well; Leandro asked for it
             gone (2026-09-08), and Create a New Product above the table already does the
             job with the group pickable on the form. --}}
        <span class="ao-ct-icons">
            @if ($categoryUrl)
                <a href="{{ $categoryUrl }}" title="Edit group">
                    <x-filament::icon icon="ri-edit-box-line" class="ao-mu-cell-icon" />
                </a>
            @endif
            <button type="button" title="Delete group" wire:click="confirmDelete('category', {{ $category->id }})">
                <x-filament::icon icon="ri-indeterminate-circle-fill" class="ao-mu-cell-icon ao-mu-icon-red" />
            </button>
        </span>
    </div>

    @if ($products->isEmpty())
        <p class="ao-cat-empty">No products in this group.</p>
    @else
        <div data-ao-scope="product" data-ao-category="{{ $category->id }}">
            @foreach ($products as $product)
                @php $productUrl = $this->productUrl($product); @endphp
                <div class="ao-ct-row ao-ct-product" data-ao-id="{{ $product->id }}" wire:key="ao-prod-{{ $product->id }}">
                    <span class="ao-ct-name">
                        @if ($productUrl)
                            <a href="{{ $productUrl }}">{{ $product->name }}</a>
                        @else
                            {{ $product->name }}
                        @endif

                        {{-- The reference marks these the same way, in the name itself. --}}
                        @if ($product->hidden)
                            <i class="ao-cat-flag">(Hidden)</i>
                        @endif
                    </span>
                    {{-- Reads as the reference's plain text; click it to change the type,
                         which core's product editor cannot show. --}}
                    <span class="ao-ct-type">
                        @if ($canReorderProducts)
                            <button type="button" class="ao-ct-type-btn"
                                wire:click="$set('typingId', {{ $product->id }})"
                                title="Change this product's type">{{ $this->typeLabel($product) }}</button>
                        @else
                            {{ $this->typeLabel($product) }}
                        @endif
                    </span>
                    <span class="ao-ct-pay">{{ $this->payTypeLabel($product) }}</span>
                    <span>{{ $product->stock ?? '-' }}</span>
                    <span>{{ $this->autoSetupLabel($product) }}</span>
                    <span class="ao-ct-features">{{ $this->featuresLabel($product) }}</span>
                    <span class="ao-ct-icons">
                        @if ($canReorderProducts)
                            <span class="ao-grip" data-ao-grip role="button" tabindex="0"
                                aria-label="Reorder {{ $product->name }}. Drag, or use the arrow keys."
                                title="Drag to reorder">&#10021;</span>
                        @endif
                        @if ($productUrl)
                            <a href="{{ $productUrl }}" title="Edit product">
                                <x-filament::icon icon="ri-edit-box-line" class="ao-mu-cell-icon" />
                            </a>
                        @endif
                        <button type="button" title="Delete product" wire:click="confirmDelete('product', {{ $product->id }})">
                            <x-filament::icon icon="ri-indeterminate-circle-fill" class="ao-mu-cell-icon ao-mu-icon-red" />
                        </button>
                    </span>
                </div>
            @endforeach
        </div>
    @endif

    @if (! empty($node['children']))
        <ul class="ao-cat-list ao-cat-children" data-ao-scope="category"
            data-ao-parent="{{ $category->id }}">
            @foreach ($node['children'] as $child)
                @include('adminops::pages.catalogue-group', ['node' => $child])
            @endforeach
        </ul>
    @endif
</li>
