{{--
    Cart — the reference portal's "Review & Checkout" page.

    Layout, in the reference's order: store rail on the left; a brand-headed line-item
    table; Empty Cart; the promo-code tab; and a grey-headed Order Summary on the right
    carrying the totals and the Checkout call to action.

    Paymenter takes payment on the invoice that checkout() creates, so this page ends at
    "Checkout →" — the reference's separate cart.php?a=checkout step (Choose Account /
    Payment Details / credit / Complete Order) is that invoice page.

    Every Livewire binding (updateQuantity, removeProduct, coupon, applyCoupon,
    removeCoupon, tos, checkout) is unchanged from the default theme.
--}}
@php
    $items = Cart::items();
    $hasQuantity = $items->contains(fn ($i) => $i->product->allow_quantity == 'combined');
@endphp

<div class="wf-page">
    <div class="wf-title">
        <h1>{{ __('theme.review_checkout') }}</h1>
    </div>
    <hr class="wf-title-rule">

    <div class="wf-layout">
        <x-store-rail />

        <div>
            @if ($items->count() === 0)
                <div class="wf-layout wf-layout--reverse" style="align-items:start">
                    <div>
                        <div class="wf-table-wrap">
                            <table class="wf-table wf-table--cart">
                                <thead>
                                    <tr>
                                        <th>{{ __('theme.product_options') }}</th>
                                        <th style="text-align:end">{{ __('theme.price_cycle') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td colspan="3"><div class="wf-empty">{{ __('product.empty_cart') }}</div></td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="wf-promo">
                            <div class="wf-promo-tab">{{ __('theme.apply_promo_code') }}</div>
                            <div class="wf-promo-body">
                                <input type="text" class="wf-input" wire:model="coupon"
                                    placeholder="{{ __('theme.promo_placeholder') }}">
                                <button type="button" class="wf-btn" wire:click="applyCoupon" wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="applyCoupon">{{ __('theme.validate_code') }}</span>
                                    <span wire:loading wire:target="applyCoupon">…</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="wf-summary wf-sticky">
                            <div class="wf-summary-head">{{ __('product.order_summary') }}</div>
                            <div class="wf-summary-body">
                                <div class="wf-total-row"><span>{{ __('invoices.subtotal') }}</span><span>{{ $total->format(0) }}</span></div>
                                <div class="wf-total-row" style="border-top:1px solid var(--wf-border)"><span>{{ __('theme.totals') }}</span><span>{{ $total->format(0) }}</span></div>
                                <div class="wf-summary-total"><strong>{{ $total->format(0) }}</strong><span>{{ __('theme.total_due_today') }}</span></div>
                                <button type="button" class="wf-btn wf-btn--checkout" disabled>{{ __('product.checkout') }} &rarr;</button>
                            </div>
                            <div class="wf-summary-foot"><a href="{{ route('home') }}" wire:navigate>{{ __('theme.continue_shopping') }}</a></div>
                        </div>
                    </div>
                </div>
            @else
                <div class="wf-layout wf-layout--reverse" style="align-items:start">
                    {{-- ── Line items ─────────────────────────────────────── --}}
                    <div>
                        <div class="wf-table-wrap">
                            <table class="wf-table wf-table--cart">
                                <thead>
                                    <tr>
                                        <th>{{ __('theme.product_options') }}</th>
                                        @if ($hasQuantity)
                                            <th>{{ __('invoices.quantity') }}</th>
                                        @endif
                                        <th style="text-align:end">{{ __('theme.price_cycle') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($items as $item)
                                        <tr>
                                            <td>
                                                <span class="wf-cart-name">{{ $item->product->name }}</span>
                                                <a class="wf-cart-edit"
                                                    href="{{ route('products.checkout', [$item->product->category, $item->product, 'edit' => $item->id]) }}"
                                                    wire:navigate>✎ {{ __('product.edit') }}</a>
                                                <span class="wf-cart-cat">{{ $item->product->category->name ?? '' }}</span>
                                                {{-- Configured options read as "» Region: United Kingdom - Birmingham",
                                                     one per line, exactly as the reference lists them. --}}
                                                @foreach ($item->config_options as $option)
                                                    <span class="wf-cart-opt">&raquo; {{ $option['option_name'] }}: {{ $option['value_name'] }}</span>
                                                @endforeach
                                            </td>

                                            @if ($hasQuantity)
                                                <td>
                                                    @if ($item->product->allow_quantity == 'combined')
                                                        <div class="wf-qty">
                                                            <button type="button" class="wf-btn wf-btn--ghost wf-btn--sm"
                                                                wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity - 1 }})">−</button>
                                                            <span class="wf-qty-value">{{ $item->quantity }}</span>
                                                            <button type="button" class="wf-btn wf-btn--ghost wf-btn--sm"
                                                                wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity + 1 }})">+</button>
                                                        </div>
                                                    @else
                                                        {{ $item->quantity }}
                                                    @endif
                                                </td>
                                            @endif

                                            <td style="text-align:end">
                                                <span class="wf-price">{{ $item->price->format($item->price->total * $item->quantity) }}</span>
                                                <x-cycle :plan="$item->plan" class="wf-cart-cycle" />
                                                @if ($item->quantity > 1)
                                                    <span class="wf-cart-cycle">{{ $item->price }} {{ __('theme.each') }}</span>
                                                @endif
                                            </td>

                                            <td style="text-align:end; width:1%">
                                                {{-- The reference removes a line with a bare × at the row's end. --}}
                                                <button type="button" class="wf-cart-remove"
                                                    title="{{ __('product.remove') }}" aria-label="{{ __('product.remove') }}"
                                                    wire:click="removeProduct({{ $item->id }})">
                                                    <span wire:loading.remove wire:target="removeProduct({{ $item->id }})">&times;</span>
                                                    <span wire:loading wire:target="removeProduct({{ $item->id }})">…</span>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="wf-cart-bar">
                            {{-- A form, not wire:click: wire:click calls one method, and core's
                                 Cart component only exposes removeProduct($index), so a Livewire
                                 binding here would drop a single line while claiming to empty the
                                 cart. Posts to the PortalBehavior route, which calls Cart::clear().
                                 Guarded by Route::has() so a disabled extension hides the button
                                 rather than breaking the page with "route not defined". --}}
                            @if (Route::has('extensions.others.portal.cart.empty'))
                                <form method="POST" action="{{ route('extensions.others.portal.cart.empty') }}">
                                    @csrf
                                    <button type="submit" class="wf-btn wf-btn--danger wf-btn--sm">
                                        🗑 {{ __('theme.empty_cart_button') }}
                                    </button>
                                </form>
                            @endif
                        </div>

                        {{-- ── Promo code ─────────────────────────────────── --}}
                        <div class="wf-promo">
                            <div class="wf-promo-tab">{{ __('theme.apply_promo_code') }}</div>
                            <div class="wf-promo-body">
                                @if (!$coupon)
                                    <input type="text" class="wf-input" wire:model="coupon"
                                        placeholder="{{ __('theme.promo_placeholder') }}">
                                    <button type="button" class="wf-btn" wire:click="applyCoupon" wire:loading.attr="disabled">
                                        <span wire:loading.remove wire:target="applyCoupon">{{ __('theme.validate_code') }}</span>
                                        <span wire:loading wire:target="applyCoupon">…</span>
                                    </button>
                                @else
                                    <div class="wf-total-row" style="padding-top:0">
                                        <span>{{ $coupon->code }}</span>
                                        <button type="button" class="wf-btn wf-btn--ghost wf-btn--sm"
                                            wire:click="removeCoupon">{{ __('product.remove') }}</button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- ── Order Summary ──────────────────────────────────── --}}
                    <div>
                        <div class="wf-summary wf-sticky">
                            <div class="wf-summary-head">{{ __('product.order_summary') }}</div>
                            <div class="wf-summary-body">
                                <div class="wf-total-row">
                                    <span>{{ __('invoices.subtotal') }}</span>
                                    <span>{{ $total->format($total->subtotal) }}</span>
                                </div>
                                @if ($total->tax > 0)
                                    <div class="wf-total-row">
                                        <span>{{ \App\Classes\Settings::tax()->name }} ({{ \App\Classes\Settings::tax()->rate }}%)</span>
                                        <span>{{ $total->format($total->tax) }}</span>
                                    </div>
                                @endif

                                <div class="wf-total-row" style="border-top:1px solid var(--wf-border)">
                                    <span>{{ __('theme.totals') }}</span>
                                </div>

                                <div class="wf-summary-total">
                                    <strong>{{ $total->format($total->total) }}</strong>
                                    <span>{{ __('theme.total_due_today') }}</span>
                                </div>

                                @if (config('settings.tos'))
                                    <label class="wf-check" style="margin-bottom:.75rem">
                                        <input type="checkbox" wire:model="tos">
                                        <span>
                                            {{ __('product.tos') }}
                                            <a href="{{ config('settings.tos') }}" target="_blank">{{ __('product.tos_link') }}</a>
                                        </span>
                                    </label>
                                @endif

                                <button type="button" class="wf-btn wf-btn--checkout"
                                    wire:click="checkout" wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="checkout">{{ __('product.checkout') }} &rarr;</span>
                                    <span wire:loading wire:target="checkout">…</span>
                                </button>
                            </div>
                            <div class="wf-summary-foot">
                                <a href="{{ route('home') }}" wire:navigate>{{ __('theme.continue_shopping') }}</a>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
