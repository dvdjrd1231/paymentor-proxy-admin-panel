{{--
    Edit Order, in Add New Order's clothes: the same striped cards, one filled in — client
    and date on top, a product line per service, Save Changes. Removing a line only removes
    services that never ran; the page refuses the rest with its reasons.
--}}
<x-filament-panels::page>
    <form class="ao-mu ao-ano" wire:submit.prevent="save">
        <div class="ao-ano-main">
            <div class="ao-anc-card">
                <label class="ao-anc-row">
                    <span>Client</span>
                    <span class="ao-anc-field">
                        <a class="ao-link" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $order->user_id]) }}">
                            {{ trim(($order->user->first_name ?? '') . ' ' . ($order->user->last_name ?? '')) ?: $order->user->email }} - #{{ $order->user_id }}
                        </a>
                    </span>
                </label>
                <label class="ao-anc-row">
                    <span>Order Number</span>
                    <span class="ao-anc-field">{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageOrders::numberOf($order) }}</span>
                </label>
                <label class="ao-anc-row">
                    <span>Order Date</span>
                    <span class="ao-anc-field">{{ $order->created_at?->format('m/d/Y H:i') }}</span>
                </label>
                <label class="ao-anc-row">
                    <span>Currency</span>
                    <span class="ao-anc-field">{{ $order->currency_code }}</span>
                </label>
            </div>

            <h4 class="ao-ano-heading">Product/Service</h4>
            @foreach ($items as $index => $item)
                <div class="ao-anc-card ao-ano-item">
                    <label class="ao-anc-row">
                        <span>Product/Service</span>
                        <span class="ao-anc-field">
                            <select wire:model.live="items.{{ $index }}.productId">
                                <option value="">None</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}">
                                        {{ $product->category?->name }} - {{ $product->name }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="button" class="ao-ano-remove" title="Remove this line"
                                wire:click="removeItem({{ $index }})">&times;</button>
                        </span>
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
                        <input type="number" min="1" wire:model="items.{{ $index }}.quantity" class="ao-ano-qty">
                    </label>
                    <label class="ao-anc-row">
                        <span>Price</span>
                        <input type="text" inputmode="decimal" wire:model="items.{{ $index }}.price" placeholder="0.00">
                    </label>
                    <label class="ao-anc-row">
                        <span>Status</span>
                        <select wire:model="items.{{ $index }}.status">
                            <option value="pending">Pending</option>
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </label>
                </div>
            @endforeach

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
                @php $total = 0; @endphp
                @foreach ($items as $item)
                    @if ($item['productId'])
                        @php
                            $line = (float) ($item['price'] ?: 0) * max(1, (int) $item['quantity']);
                            $total += $line;
                        @endphp
                        <div class="ao-ano-line">
                            <span>{{ $products->firstWhere('id', (int) $item['productId'])?->name ?? '—' }} &times; {{ max(1, (int) $item['quantity']) }}</span>
                            <span>${{ number_format($line, 2) }} {{ $order->currency_code }}</span>
                        </div>
                    @endif
                @endforeach
                <div class="ao-ano-sub">
                    <span>Sub Total</span>
                    <span>${{ number_format($total, 2) }} {{ $order->currency_code }}</span>
                </div>
                <div class="ao-ano-total">
                    <span>Total</span>
                    <span>${{ number_format($total, 2) }} {{ $order->currency_code }}</span>
                </div>
            </div>
            <button type="submit" class="ao-find-go ao-ano-submit">Save Changes</button>
        </aside>
    </form>
</x-filament-panels::page>
