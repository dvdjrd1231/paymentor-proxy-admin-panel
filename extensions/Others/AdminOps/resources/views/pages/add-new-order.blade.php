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
                    {{-- .live: the Order Summary's credit-balance offer is this client's own
                         balance, and has to refresh the moment a different one is picked. --}}
                    <select wire:model.live="userId" required>
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
                    {{-- Active provisions immediately and skips Pending — for an order whose
                         payment was already collected outside the system. --}}
                    <select wire:model="orderStatus">
                        <option value="pending">Pending</option>
                        <option value="active">Active</option>
                    </select>
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

                    {{-- Configurable Options: core's own ConfigOption tree (admin-managed
                         under Configuration → Configurable Options) and, when this line's
                         product has a server, that server's own checkout fields —
                         ProxyPanel's Region among them, flags included. Not a special case:
                         both come through the same ExtensionHelper call the storefront's own
                         checkout uses, so this offers exactly what a customer placing the
                         same order would see. --}}
                    @if ($optionsByItem[$index]->isNotEmpty() || $checkoutFieldsByItem[$index] !== [])
                        <div class="ao-ano-configs">
                            <div class="ao-ano-configs-head">Configurable Options</div>

                            @foreach ($optionsByItem[$index] as $option)
                                <label class="ao-anc-row">
                                    <span>{{ $option->name }}</span>
                                    @switch($option->type)
                                        @case('checkbox')
                                            <span class="ao-anc-field">
                                                <input type="checkbox" wire:model.live="items.{{ $index }}.configOptions.{{ $option->id }}">
                                            </span>
                                            @break

                                        @case('text')
                                        @case('number')
                                            <input type="{{ $option->type === 'number' ? 'number' : 'text' }}"
                                                wire:model.live="items.{{ $index }}.configOptions.{{ $option->id }}">
                                            @break

                                        @case('radio')
                                            <span class="ao-anc-field ao-ano-radios">
                                                @foreach ($option->children as $child)
                                                    <label>
                                                        <input type="radio" name="ao-cfg-{{ $index }}-{{ $option->id }}" value="{{ $child->id }}"
                                                            wire:model.live="items.{{ $index }}.configOptions.{{ $option->id }}">
                                                        {{ $child->name }}
                                                    </label>
                                                @endforeach
                                            </span>
                                            @break

                                        @default {{-- select, slider --}}
                                            <select wire:model.live="items.{{ $index }}.configOptions.{{ $option->id }}">
                                                @foreach ($option->children as $child)
                                                    <option value="{{ $child->id }}">{{ $child->name }}</option>
                                                @endforeach
                                            </select>
                                    @endswitch
                                </label>
                            @endforeach

                            @foreach ($checkoutFieldsByItem[$index] as $field)
                                <label class="ao-anc-row">
                                    <span>{{ $field['label'] ?? $field['name'] }}</span>
                                    @switch($field['type'] ?? 'text')
                                        @case('select')
                                        @case('radio')
                                            <select wire:model.live="items.{{ $index }}.checkoutConfig.{{ $field['name'] }}"
                                                @disabled(!empty($field['disabled_options']) && count($field['disabled_options']) === count($field['options'] ?? []))>
                                                @foreach ($field['options'] ?? [] as $value => $label)
                                                    <option value="{{ $value }}" @selected(($item['checkoutConfig'][$field['name']] ?? '') === (string) $value)
                                                        {{ in_array($value, $field['disabled_options'] ?? [], true) ? 'disabled' : '' }}>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @break

                                        @case('checkbox')
                                            <span class="ao-anc-field">
                                                <input type="checkbox" wire:model.live="items.{{ $index }}.checkoutConfig.{{ $field['name'] }}">
                                            </span>
                                            @break

                                        @default
                                            <input type="{{ $field['type'] === 'number' ? 'number' : 'text' }}"
                                                wire:model.live="items.{{ $index }}.checkoutConfig.{{ $field['name'] }}"
                                                @if (!empty($field['description'])) title="{{ $field['description'] }}" @endif>
                                    @endswitch
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach

            {{-- The reference's green add link, doing the reference's thing. --}}
            <button type="button" class="ao-ano-add" wire:click="addItem">
                <span aria-hidden="true">&#10133;</span> Add Another Product
            </button>

            {{-- The reference's Domain Registration block. No registrar is connected, so
                 None is the one honest choice — the others say why they are off. --}}
            <h4 class="ao-ano-heading">Domain Registration</h4>
            <div class="ao-anc-card">
                <div class="ao-anc-row">
                    <span>Registration Type</span>
                    <span class="ao-ano-checks" title="No domain registrar is connected to this store">
                        <label><input type="radio" name="ao-regtype" checked> None</label>
                        <label class="ao-ano-off"><input type="radio" name="ao-regtype" disabled> Registration</label>
                        <label class="ao-ano-off"><input type="radio" name="ao-regtype" disabled> Transfer</label>
                    </span>
                </div>
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
                @forelse ($summary['lines'] as $line)
                    <div class="ao-ano-line">
                        <span>{{ $line['label'] }} &times; {{ $line['quantity'] }}{{ $line['domain'] !== '' ? ' — ' . $line['domain'] : '' }}</span>
                        <span>${{ number_format($line['total'], 2) }} {{ $summary['currency'] }}</span>
                    </div>
                    {{-- The reference's "» Region: …" annotation under a line — one per
                         option that has a value worth showing. --}}
                    @foreach ($line['notes'] as $note)
                        <div class="ao-ano-note">&raquo; {{ $note }}</div>
                    @endforeach
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
                {{-- The reference's pink "Recurring" banner: what keeps billing after this
                     invoice. One row per cycle, in case a line's plan is a one-off (the
                     add-on charged once) beside another that renews. --}}
                @foreach ($summary['recurring'] as $cycle => $amount)
                    <div class="ao-ano-recurring">
                        <span>Recurring</span>
                        <span>${{ number_format($amount, 2) }} {{ $summary['currency'] }} {{ $cycle }}</span>
                    </div>
                @endforeach
            </div>

            {{-- The reference's credit-balance choice. Offered only when the client's own
                 balance fully covers this order — a partial one is never silently part-
                 applied, so there is nothing here to misread as "this order is paid" when
                 it would not quite be. --}}
            @if ($summary['creditEligible'])
                <div class="ao-ano-credit">
                    <p>Clients available credit balance is ${{ number_format($summary['creditBalance'], 2) }} {{ $summary['currency'] }}.</p>
                    <label class="ao-ano-credit-opt">
                        <input type="radio" name="ao-ano-credit" value="1" wire:model="applyCredit">
                        Apply <strong>${{ number_format($summary['total'], 2) }} {{ $summary['currency'] }}</strong> from clients credit balance to this order. No further payment will be due.
                    </label>
                    <label class="ao-ano-credit-opt">
                        <input type="radio" name="ao-ano-credit" value="0" wire:model="applyCredit">
                        Do not apply any credit from clients credit balance to this order. Client will pay for it using the selected payment method.
                    </label>
                </div>
            @endif

            <button type="submit" class="ao-find-go ao-ano-submit">Submit Order &raquo;</button>
        </aside>
    </form>
</x-filament-panels::page>
