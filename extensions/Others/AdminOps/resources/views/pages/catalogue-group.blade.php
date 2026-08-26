{{--
    One group: its heading band, its products, and any child groups.

    Included recursively — a child group is the same thing one level in — so the depth of
    the tree is whatever `categories.parent_id` says rather than a fixed two.

    `$node` is `['category' => Category, 'children' => array]`; everything else
    (`$canReorderCategories`, `$canReorderProducts`) comes from the parent view.
--}}
@php
    $category = $node['category'];
    $products = $category->products;
    $categoryUrl = $this->categoryUrl($category);
@endphp

{{--
    `wire:key` on every row that can move. A drag reorders the DOM before the server knows
    anything about it, so the re-render that follows is morphing new HTML onto a list in a
    different order than it was sent in. Keyed, Livewire matches rows by identity and moves
    them; unkeyed, it matches by position and rewrites each row's contents in place — which
    looks like the drag half-worked.
--}}
<li class="ao-cat" data-ao-id="{{ $category->id }}" wire:key="ao-cat-{{ $category->id }}">
    <div class="ao-cat-head">
        @if ($canReorderCategories)
            <span class="ao-grip" data-ao-grip role="button" tabindex="0"
                aria-label="Reorder the group {{ $category->name }}. Drag, or use the arrow keys."
                title="Drag to reorder">&#10021;</span>
        @endif

        <span class="ao-cat-name">Group Name: {{ $category->name }}</span>

        <span class="ao-cat-meta">
            {{ $products->count() }} {{ Str::plural('product', $products->count()) }}
        </span>

        @if ($categoryUrl)
            <a class="ao-cat-edit" href="{{ $categoryUrl }}">Edit</a>
        @endif
    </div>

    @if ($products->isEmpty())
        <p class="ao-cat-empty">No products in this group.</p>
    @else
        <table class="ao-cat-table">
            <thead>
                <tr>
                    @if ($canReorderProducts)
                        <th class="ao-col-grip"><span class="sr-only">Reorder</span></th>
                    @endif
                    <th>Product Name</th>
                    <th>Type</th>
                    <th>Pay Type</th>
                    <th class="ao-col-stock">Stock</th>
                </tr>
            </thead>
            <tbody data-ao-scope="product" data-ao-category="{{ $category->id }}">
                @foreach ($products as $product)
                    @php $productUrl = $this->productUrl($product); @endphp
                    <tr data-ao-id="{{ $product->id }}" wire:key="ao-prod-{{ $product->id }}">
                        @if ($canReorderProducts)
                            <td class="ao-col-grip">
                                <span class="ao-grip" data-ao-grip role="button" tabindex="0"
                                    aria-label="Reorder {{ $product->name }}. Drag, or use the arrow keys."
                                    title="Drag to reorder">&#10021;</span>
                            </td>
                        @endif

                        <td>
                            @if ($productUrl)
                                <a href="{{ $productUrl }}">{{ $product->name }}</a>
                            @else
                                {{ $product->name }}
                            @endif

                            {{-- The reference marks these the same way, in the name itself. --}}
                            @if ($product->hidden)
                                <span class="ao-cat-flag">(Hidden)</span>
                            @endif
                        </td>

                        <td>{{ $this->typeLabel($product) }}</td>
                        <td>{{ $this->payTypeLabel($product) }}</td>
                        <td class="ao-col-stock">{{ $product->stock ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
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
