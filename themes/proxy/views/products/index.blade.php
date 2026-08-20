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
        <x-store-rail :active="$category" />

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

            {{-- Two cards per row, each laid out as feature list | buy column — the
                 reference portal's product card. --}}
            <div class="wf-cards wf-cards--products">
                @forelse ($products as $product)
                    @php
                        $price = $product->price();
                        // Cheapest available plan drives the cycle label under the price,
                        // so "Starting from" and "Monthly" describe the same plan.
                        $cheapest = $product->availablePlans()->first();
                        $canOrder = $product->stock !== 0 && $price->available;
                    @endphp
                    <div class="wf-card">
                        <div class="wf-card-head">{{ $product->name }}</div>
                        <div class="wf-prod-body">
                            <div class="wf-prod-feat">
                                @if ($product->image)
                                    <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}" class="wf-card-img">
                                @endif
                                {{-- Always shown. This used to be gated on theme('direct_checkout'),
                                     an unrelated setting that is off by default — which is why every
                                     card rendered without its feature list. --}}
                                @if ($product->description)
                                    {!! $product->description !!}
                                @endif
                            </div>

                            <div class="wf-prod-buy">
                                <div class="wf-price-from">{{ __('theme.starting_from') }}</div>
                                <div class="wf-price">{{ $price->formatted->price }} {{ $price->currency->code ?? '' }}</div>
                                <x-cycle :plan="$cheapest" class="wf-prod-cycle" />

                                @if ($canOrder)
                                    <a class="wf-btn wf-btn--sm"
                                        href="{{ route('products.checkout', ['category' => $product->category, 'product' => $product->slug]) }}" wire:navigate>
                                        <span class="wf-btn-ico"><x-ri-shopping-cart-2-fill /></span>{{ __('theme.order_now') }}
                                    </a>
                                @endif
                                <a class="wf-btn wf-btn--sm wf-btn--ghost"
                                    href="{{ route('products.show', ['category' => $product->category, 'product' => $product->slug]) }}" wire:navigate>
                                    {{ __('common.button.view') }}
                                </a>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="wf-empty">{{ __('theme.no_products') }}</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
