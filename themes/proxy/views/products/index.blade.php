{{-- Product browsing — WHMCS "Six" style: category sidebar + compact product cards.
     Same routes, theme() options and Livewire navigation as the default theme. --}}
<div class="wf-page">
    <div class="wf-pagehead">
        <h1>{{ $category->name }}</h1>
        @if($category->description)
            <p>{{ strip_tags($category->description) }}</p>
        @endif
    </div>

    <div class="wf-layout">
        {{-- ── Category sidebar ────────────────────────────────────────── --}}
        <div>
            <div class="wf-panel">
                <div class="wf-panel-heading">{{ __('product.categories') ?? __('navigation.store') }}</div>
                <ul class="wf-list">
                    @foreach ($categories as $ccategory)
                        <li>
                            <a href="{{ route('category.show', ['category' => $ccategory->slug]) }}" wire:navigate
                                class="{{ $category->id == $ccategory->id ? 'is-active' : '' }}">
                                <span>{{ $ccategory->name }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Actions panel — the reference portal shows this beneath Categories --}}
            <div class="wf-panel">
                <div class="wf-panel-heading">+ {{ __('theme.actions') }}</div>
                <ul class="wf-list">
                    <li>
                        <a href="{{ route('cart') }}" wire:navigate>
                            <span>{{ __('theme.view_cart') }}</span>
                            <span class="wf-head-icon"><x-ri-shopping-cart-2-fill /></span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        {{-- ── Products ────────────────────────────────────────────────── --}}
        <div>
            @if (count($childCategories) >= 1)
                <div class="wf-cards" style="margin-bottom:1.25rem">
                    @foreach ($childCategories as $childCategory)
                        <div class="wf-card">
                            <div class="wf-card-head">{{ $childCategory->name }}</div>
                            <div class="wf-card-body">
                                @if ($childCategory->image)
                                    <img src="{{ Storage::url($childCategory->image) }}" alt="{{ $childCategory->name }}" class="wf-card-img">
                                @endif
                                @if(theme('show_category_description', true))
                                    <article class="prose dark:prose-invert">{!! $childCategory->description !!}</article>
                                @endif
                            </div>
                            <div class="wf-card-foot">
                                <a class="wf-btn wf-btn--sm wf-btn--block" href="{{ route('category.show', ['category' => $childCategory->slug]) }}" wire:navigate>
                                    {{ __('common.button.view') }}
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="wf-cards">
                @forelse ($products as $product)
                    <div class="wf-card">
                        <div class="wf-card-head">{{ $product->name }}</div>
                        <div class="wf-card-body">
                            @if ($product->image)
                                <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}" class="wf-card-img">
                            @endif
                            @if(theme('direct_checkout', false) && $product->description)
                                <article class="prose dark:prose-invert">{!! $product->description !!}</article>
                            @endif
                            {{-- "Starting from $X USD" — the reference portal's price presentation --}}
                            <div class="wf-price-from">{{ __('theme.starting_from') }}</div>
                            <div class="wf-price">{{ $product->price()->formatted->price }} {{ $product->price()->currency->code ?? '' }}</div>
                        </div>
                        <div class="wf-card-foot">
                            @if ($product->stock !== 0 && $product->price()->available)
                                <a class="wf-btn wf-btn--sm"
                                    href="{{ route('products.checkout', ['category' => $product->category, 'product' => $product->slug]) }}" wire:navigate>
                                    <span class="wf-btn-ico"><x-ri-shopping-cart-2-fill /></span>{{ __('theme.order_now') }}
                                </a>
                                <a class="wf-btn wf-btn--sm wf-btn--ghost"
                                    href="{{ route('products.show', ['category' => $product->category, 'product' => $product->slug]) }}" wire:navigate>
                                    {{ __('common.button.view') }}
                                </a>
                            @else
                                <a class="wf-btn wf-btn--sm"
                                    href="{{ route('products.show', ['category' => $product->category, 'product' => $product->slug]) }}" wire:navigate>
                                    {{ __('common.button.view') }}
                                </a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="wf-empty">{{ __('product.no_products') ?? 'No products in this category.' }}</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
