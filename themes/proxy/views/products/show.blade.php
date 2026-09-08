{{-- Product detail — WHMCS "Six" style. Same routes and stock/price logic as the
     default theme. --}}
<div class="wf-page">
    <div class="wf-pagehead">
        <h1>{{ $product->name }}</h1>
    </div>

    <div class="wf-layout">
        <x-store-rail :active="$category" />

        <div>
            <div class="wf-crumb">
                <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a>
                <span>/</span><a href="{{ route('category.show', ['category' => $category->slug]) }}" wire:navigate>{{ $category->name }}</a>
                <span>/</span>{{ $product->name }}
            </div>

            <div class="wf-panel">
                <div class="wf-panel-body">
                    <div class="wf-product-intro">
                        @if ($product->image)
                            <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}">
                        @endif

                        <div style="flex:1; min-width:0">
                            @if ($product->stock === 0)
                                <span class="wf-label wf-label--danger">{{ __('product.out_of_stock', ['product' => $product->name]) }}</span>
                            @elseif($product->stock > 0)
                                <span class="wf-label wf-label--success">{{ __('product.in_stock') }}</span>
                            @endif

                            <div class="wf-price" style="margin:.6rem 0">{{ $product->price()->formatted->price }}</div>

                            <article class="prose dark:prose-invert">{!! $product->description !!}</article>
                        </div>
                </div>
            </div>
                @if ($product->stock !== 0 && $product->price()->available)
                    <div class="wf-panel-footer">
                        <a class="wf-btn" wire:navigate
                            href="{{ route('products.checkout', ['category' => $category, 'product' => $product->slug]) }}">
                            <span class="wf-btn-ico"><x-ri-shopping-cart-2-fill /></span>{{ __('theme.order_now') }}
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Cross-sells: the products the admin has recommended alongside this one, set on
         Edit Product → Cross-sells. Without this the tab would store a list nobody reads. --}}
    @php
        $crossSells = collect();

        if (class_exists(\Paymenter\Extensions\Others\AdminOps\Models\Meta::class)) {
            $ids = array_filter(array_map('intval', explode(',',
                (string) (\Paymenter\Extensions\Others\AdminOps\Models\Meta::for($product)['cross_sells'] ?? ''))));

            if ($ids !== []) {
                $crossSells = \App\Models\Product::with('category')
                    ->whereIn('id', $ids)->where('hidden', false)->get();
            }
        }
    @endphp

    @if ($crossSells->isNotEmpty())
        <div class="wf-panel wf-crosssell">
            <div class="wf-panel-heading">{{ __('theme.you_may_also_like') }}</div>
            <div class="wf-panel-body wf-crosssell-list">
                @foreach ($crossSells as $other)
                    <a class="wf-crosssell-item" wire:navigate
                        href="{{ route('products.show', ['category' => $other->category->slug, 'product' => $other->slug]) }}">
                        <span class="wf-crosssell-name">{{ $other->name }}</span>
                        <span class="wf-crosssell-price">{{ $other->price()->formatted->price ?? '' }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
