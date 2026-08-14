{{-- Cart — WHMCS "Six" style: line items on the left, order summary + coupon on the
     right. All Livewire bindings (updateQuantity, removeProduct, coupon, tos, checkout)
     are unchanged from the default theme. --}}
<div class="wf-page">
    <div class="wf-pagehead">
        <h1>{{ __('navigation.cart') ?? 'Cart' }}</h1>
    </div>

    <div class="wf-layout wf-layout--reverse">
        {{-- ── Items ───────────────────────────────────────────────────── --}}
        <div>
            <div class="wf-panel">
                <div class="wf-panel-heading">{{ __('product.order_summary') }}</div>

                @if (Cart::items()->count() === 0)
                    <div class="wf-empty">{{ __('product.empty_cart') }}</div>
                @else
                    <div class="wf-table-wrap">
                        <table class="wf-table">
                            <thead>
                                <tr>
                                    <th>{{ __('invoices.item') }}</th>
                                    @if (Cart::items()->contains(fn ($i) => $i->product->allow_quantity == 'combined'))
                                        <th>{{ __('invoices.quantity') }}</th>
                                    @endif
                                    <th style="text-align:end">{{ __('invoices.total') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (Cart::items() as $item)
                                    <tr>
                                        <td>
                                            <span class="wf-list-title">{{ $item->product->name }}</span>
                                            @if (count($item->config_options))
                                                <span class="wf-list-sub">
                                                    @foreach ($item->config_options as $option)
                                                        {{ $option['option_name'] }}: {{ $option['value_name'] }}@if(!$loop->last) · @endif
                                                    @endforeach
                                                </span>
                                            @endif
                                        </td>

                                        @if (Cart::items()->contains(fn ($i) => $i->product->allow_quantity == 'combined'))
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
                                            <strong>{{ $item->price->format($item->price->total * $item->quantity) }}</strong>
                                            @if ($item->quantity > 1)
                                                <span class="wf-list-sub">{{ $item->price }} {{ __('product.each') ?? 'each' }}</span>
                                            @endif
                                        </td>

                                        <td style="text-align:end; white-space:nowrap">
                                            <a class="wf-btn wf-btn--ghost wf-btn--sm"
                                                href="{{ route('products.checkout', [$item->product->category, $item->product, 'edit' => $item->id]) }}" wire:navigate>
                                                {{ __('product.edit') }}
                                            </a>
                                            <button type="button" class="wf-btn wf-btn--danger wf-btn--sm"
                                                wire:click="removeProduct({{ $item->id }})">
                                                <span wire:loading.remove wire:target="removeProduct({{ $item->id }})">{{ __('product.remove') }}</span>
                                                <span wire:loading wire:target="removeProduct({{ $item->id }})">…</span>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- ── Summary ─────────────────────────────────────────────────── --}}
        <div>
            @if (Cart::items()->count() > 0)
                <div class="wf-panel wf-panel--brand wf-sticky">
                    <div class="wf-panel-heading">{{ __('product.order_summary') }}</div>
                    <div class="wf-panel-body">
                        @if(!$coupon)
                            <div class="wf-coupon">
                                <x-form.input wire:model="coupon" name="coupon" label="{{ __('product.coupon') ?? 'Coupon' }}" />
                                <button type="button" class="wf-btn wf-btn--sm" wire:click="applyCoupon" wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="applyCoupon">{{ __('product.apply') }}</span>
                                    <span wire:loading wire:target="applyCoupon">…</span>
                                </button>
                            </div>
                        @else
                            <div class="wf-total-row">
                                <span>{{ $coupon->code }}</span>
                                <button type="button" class="wf-btn wf-btn--ghost wf-btn--sm" wire:click="removeCoupon">{{ __('product.remove') }}</button>
                            </div>
                        @endif

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
                        <div class="wf-total-row wf-total-row--grand">
                            <span>{{ __('invoices.total') }}</span>
                            <span>{{ $total->format($total->total) }}</span>
                        </div>

                        @if(config('settings.tos'))
                            <div style="margin-top:.75rem">
                                <x-form.checkbox wire:model="tos" name="tos">
                                    {{ __('product.tos') }}
                                    <a href="{{ config('settings.tos') }}" target="_blank">{{ __('product.tos_link') }}</a>
                                </x-form.checkbox>
                            </div>
                        @endif

                        <button type="button" class="wf-btn wf-btn--block" style="margin-top:.9rem"
                            wire:click="checkout" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="checkout">{{ __('product.checkout') }}</span>
                            <span wire:loading wire:target="checkout">…</span>
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
