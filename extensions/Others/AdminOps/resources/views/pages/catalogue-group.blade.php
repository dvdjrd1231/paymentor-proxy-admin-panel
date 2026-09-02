{{--
    One group, to issues #35 and #41: the reference's grey band — drag handle at its left,
    "Group Name: X", edit and delete icons at its right — then the group's products as rows
    of the one shared grid, each with its own drag/edit/delete icons in the last column.

    Included recursively — a child group is the same thing one level in.

    `$node` is `['category' => Category, 'children' => array]`; everything else
    (`$canReorderCategories`, `$canReorderProducts`) comes from the parent view.

    `wire:key` on every row that can move: a drag reorders the DOM before the server knows
    anything about it, so the re-render that follows morphs new HTML onto a list in a
    different order than it was sent in. Keyed, Livewire matches rows by identity.
--}}
@php
    $category = $node['category'];
    $products = $category->products;
    $categoryUrl = $this->categoryUrl($category);
@endphp

<li class="ao-cat" data-ao-id="{{ $category->id }}" wire:key="ao-cat-{{ $category->id }}">
    <div class="ao-ct-band">
        @if ($canReorderCategories)
            <span class="ao-grip" data-ao-grip role="button" tabindex="0"
                aria-label="Reorder the group {{ $category->name }}. Drag, or use the arrow keys."
                title="Drag to reorder">&#10021;</span>
        @endif

        <span class="ao-ct-band-name"><b>Group Name:</b> {{ $category->name }}</span>

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
                    <span>{{ $this->typeLabel($product) }}</span>
                    <span>{{ $this->payTypeLabel($product) }}</span>
                    <span>{{ $product->stock ?? '-' }}</span>
                    <span>{{ $this->autoSetupLabel($product) }}</span>
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
