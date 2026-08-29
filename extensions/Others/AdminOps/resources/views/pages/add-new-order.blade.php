{{--
    Add New Order, to the reference screenshot: the striped form on the left — client,
    payment method, promotion code, order status, then a Product/Service block per line
    with "+ Add Another Product" — and the Order Summary card with Submit Order right.
--}}
<x-filament-panels::page>
    <form class="ao-mu ao-ano" wire:submit.prevent="create">
        <div class="ao-ano-main">
            <div class="ao-anc-card">
                <label class="ao-anc-row">
                    <span>Client</span>
                    <select wire:model="userId" required>
                        <option value="">Start Typing to Search Clients</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}">
                                {{ trim($client->first_name . ' ' . $client->last_name) ?: $client->email }} - #{{ $client->id }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label class="ao-anc-row">
                    <span>Payment Method</span>
                    <select wire:model="gatewayId">
                        <option value="">Default</option>
                        @foreach ($gateways as $gateway)
                            <option value="{{ $gateway->id }}">{{ $gateway->name }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="ao-anc-row">
                    <span>Promotion Code</span>
                    <span class="ao-anc-field">
                        <select wire:model="couponId">
                            <option value="">None</option>
                            @foreach ($coupons as $coupon)
                                <option value="{{ $coupon->id }}">{{ $coupon->code }}</option>
                            @endforeach
                        </select>
                        @php
                            $promoUrl = null;
                            try {
                                $promoUrl = \App\Admin\Resources\CouponResource::getUrl('create');
                            } catch (\Throwable $e) {
                                try {
                                    $promoUrl = \App\Admin\Resources\CouponResource::getUrl('index');
                                } catch (\Throwable $e) {
                                }
                            }
                        @endphp
                        @if ($promoUrl)
                            <a class="ao-ano-promo" href="{{ $promoUrl }}">+ Create Custom Promo</a>
                        @endif
                    </span>
                </div>
                <label class="ao-anc-row">
                    <span>Order Status</span>
                    <select><option>Pending</option></select>
                </label>
                <div class="ao-anc-row">
                    <span></span>
                    <span class="ao-ano-checks">
                        <label><input type="checkbox" checked disabled> Order Confirmation</label>
                        <label><input type="checkbox" wire:model.live="generateInvoice"> Generate Invoice</label>
                        <label><input type="checkbox" wire:model.live="sendEmail"> Send Email</label>
                    </span>
                </div>
            </div>

            <h4 class="ao-ano-heading">Product/Service</h4>
            @foreach ($items as $index => $item)
                <div class="ao-anc-card ao-ano-item">
                    <label class="ao-anc-row">
                        <span>Product/Service</span>
                        <span class="ao-anc-field">
                            <select wire:model.live="items.{{ $index }}.productId" @required($index === 0)>
                                <option value="">None</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}">
                                        {{ $product->category?->name }} - {{ $product->name }}
                                    </option>
                                @endforeach
                            </select>
                            @if (count($items) > 1)
                                <button type="button" class="ao-ano-remove" title="Remove this product"
                                    wire:click="removeItem({{ $index }})">&times;</button>
                            @endif
                        </span>
                    </label>
                    <label class="ao-anc-row">
                        <span>Domain</span>
                        <input type="text" wire:model="items.{{ $index }}.domain" placeholder="example.com">
                    </label>
                    <label class="ao-anc-row">
                        <span>Billing Cycle</span>
                        <select wire:model.live="items.{{ $index }}.planId" @disabled($plansByItem[$index]->isEmpty())>
                            @forelse ($plansByItem[$index] as $plan)
                                <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                            @empty
                                <option value="">Select a product first</option>
                            @endforelse
                        </select>
                    </label>
                    <label class="ao-anc-row">
                        <span>Quantity</span>
                        <input type="number" min="1" wire:model.live="items.{{ $index }}.quantity" class="ao-ano-qty">
                    </label>
                    <label class="ao-anc-row">
                        <span>Price Override</span>
                        <span class="ao-anc-field">
                            <input type="text" inputmode="decimal" wire:model.live="items.{{ $index }}.priceOverride" placeholder="0.00">
                            <i>(Only enter to manually override default product pricing)</i>
                        </span>
                    </label>
                </div>
            @endforeach

            {{-- The reference's green add link, doing the reference's thing. --}}
            <button type="button" class="ao-ano-add" wire:click="addItem">
                <span aria-hidden="true">&#10133;</span> Add Another Product
            </button>

            @if ($errors->any())
                <ul class="ao-anc-errors">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <aside class="ao-ano-side">
            <h4>Order Summary</h4>
            <div class="ao-ano-card">
                @forelse ($summary['lines'] as $line)
                    <div class="ao-ano-line">
                        <span>{{ $line['label'] }} &times; {{ $line['quantity'] }}{{ $line['domain'] !== '' ? ' — ' . $line['domain'] : '' }}</span>
                        <span>${{ number_format($line['total'], 2) }} {{ $summary['currency'] }}</span>
                    </div>
                @empty
                    <div class="ao-ano-none">No Items Selected</div>
                @endforelse
                <div class="ao-ano-sub">
                    <span>Sub Total</span>
                    <span>${{ number_format($summary['total'], 2) }} {{ $summary['currency'] }}</span>
                </div>
                <div class="ao-ano-total">
                    <span>Total</span>
                    <span>${{ number_format($summary['total'], 2) }} {{ $summary['currency'] }}</span>
                </div>
            </div>
            <button type="submit" class="ao-find-go ao-ano-submit">Submit Order &raquo;</button>
        </aside>
    </form>
</x-filament-panels::page>
