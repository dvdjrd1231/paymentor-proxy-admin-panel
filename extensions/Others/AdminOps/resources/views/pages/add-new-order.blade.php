{{--
    Add New Order, to the reference screenshot: the striped form on the left — client,
    payment method, promotion code, order status, then the Product/Service block — and the
    Order Summary card with Submit Order on the right.
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
                <label class="ao-anc-row">
                    <span>Promotion Code</span>
                    <select wire:model="couponId">
                        <option value="">None</option>
                        @foreach ($coupons as $coupon)
                            <option value="{{ $coupon->id }}">{{ $coupon->code }}</option>
                        @endforeach
                    </select>
                </label>
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
            <div class="ao-anc-card">
                <label class="ao-anc-row">
                    <span>Product/Service</span>
                    <select wire:model.live="productId" required>
                        <option value="">None</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}">
                                {{ $product->category?->name }} - {{ $product->name }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label class="ao-anc-row">
                    <span>Domain</span>
                    <input type="text" wire:model="domain" placeholder="example.com">
                </label>
                <label class="ao-anc-row">
                    <span>Billing Cycle</span>
                    <select wire:model.live="planId" @disabled($plans->isEmpty())>
                        @forelse ($plans as $plan)
                            <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                        @empty
                            <option value="">Select a product first</option>
                        @endforelse
                    </select>
                </label>
                <label class="ao-anc-row">
                    <span>Quantity</span>
                    <input type="number" min="1" wire:model.live="quantity" class="ao-ano-qty">
                </label>
                <label class="ao-anc-row">
                    <span>Price Override</span>
                    <span class="ao-anc-field">
                        <input type="text" inputmode="decimal" wire:model.live="priceOverride" placeholder="0.00">
                        <i>(Only enter to manually override default product pricing)</i>
                    </span>
                </label>
            </div>

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
                @if ($summary['label'])
                    <div class="ao-ano-line">
                        <span>{{ $summary['label'] }} &times; {{ $summary['quantity'] }}</span>
                        <span>${{ number_format($summary['unit'] * $summary['quantity'], 2) }} {{ $summary['currency'] }}</span>
                    </div>
                @else
                    <div class="ao-ano-none">No Items Selected</div>
                @endif
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
