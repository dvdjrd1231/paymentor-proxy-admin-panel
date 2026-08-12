{{-- Product detail — WHMCS "Six" style. Same routes and stock/price logic as the
     default theme. --}}
<div class="wf-page">
    <div class="wf-pagehead">
        <h1>{{ $product->name }}</h1>
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
                    {{ __('product.add_to_cart') }}
                </a>
            </div>
        @endif
    </div>
</div>
