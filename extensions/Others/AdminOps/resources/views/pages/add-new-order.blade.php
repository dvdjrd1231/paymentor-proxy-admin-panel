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
                    @php
                        $clientOptions = [['value' => '', 'label' => 'Start Typing to Search Clients', 'group' => false]];
                        foreach ($clients as $client) {
                            $clientOptions[] = [
                                'value' => $client->id,
                                'label' => (trim($client->first_name . ' ' . $client->last_name) ?: $client->email) . ' - #' . $client->id,
                                'group' => false,
                            ];
                        }
                    @endphp
                    @include('adminops::partials.select', ['model' => 'userId', 'live' => true, 'options' => $clientOptions, 'placeholder' => 'Start Typing to Search Clients'])
                </label>
                <label class="ao-anc-row">
                    <span>Payment Method</span>
                    @php
                        $gatewayOptions = [['value' => '', 'label' => 'Default', 'group' => false]];
                        foreach ($gateways as $gateway) {
                            $gatewayOptions[] = ['value' => $gateway->id, 'label' => $gateway->name, 'group' => false];
                        }
                    @endphp
                    @include('adminops::partials.select', ['model' => 'gatewayId', 'live' => false, 'options' => $gatewayOptions, 'placeholder' => 'Default'])
                </label>
                <div class="ao-anc-row">
                    <span>Promotion Code</span>
                    <span class="ao-anc-field">
                        @php
                            $couponOptions = [['value' => '', 'label' => 'None', 'group' => false]];
                            foreach ($coupons as $coupon) {
                                $couponOptions[] = ['value' => $coupon->id, 'label' => $coupon->code, 'group' => false];
                            }
                        @endphp
                        @include('adminops::partials.select', ['model' => 'couponId', 'live' => false, 'options' => $couponOptions, 'placeholder' => 'None'])
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
                    @include('adminops::partials.select', [
                        'model' => 'orderStatus', 'live' => false,
                        'options' => [
                            ['value' => 'pending', 'label' => 'Pending', 'group' => false],
                            ['value' => 'active', 'label' => 'Active', 'group' => false],
                        ],
                    ])
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
                            {{-- Grouped by category, the way the reference's own optgroups
                                 read — real headings in a list the page renders, not a
                                 native popup whose group styling Windows ignores. --}}
                            @php
                                $productOptions = [['value' => '', 'label' => 'None', 'group' => false]];
                                foreach ($products->groupBy(fn ($p) => $p->category?->name ?? '—') as $categoryName => $categoryProducts) {
                                    $productOptions[] = ['value' => '', 'label' => $categoryName, 'group' => true];
                                    foreach ($categoryProducts as $product) {
                                        $productOptions[] = ['value' => $product->id, 'label' => $product->name, 'group' => false];
                                    }
                                }
                            @endphp
                            @include('adminops::partials.select', [
                                'model' => "items.{$index}.productId", 'live' => true,
                                'options' => $productOptions, 'placeholder' => 'None',
                            ])
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
                        @if ($plansByItem[$index]->isEmpty())
                            {{-- Nothing to pick yet — a disabled real select reads better
                                 here than a custom one with a single dead row. --}}
                            <select disabled><option>Select a product first</option></select>
                        @else
                            @php
                                $planOptions = $plansByItem[$index]->map(fn ($plan) => [
                                    'value' => $plan->id, 'label' => $plan->name, 'group' => false,
                                ])->all();
                            @endphp
                            {{-- Keyed by the product: picking a different one changes which
                                 plans exist, and the key change is what tells Alpine's
                                 morph to actually read the new list instead of keeping
                                 the one it opened with. --}}
                            @include('adminops::partials.select', [
                                'model' => "items.{$index}.planId", 'live' => true, 'options' => $planOptions,
                                'key' => "plan-select-{$index}-{$item['productId']}",
                            ])
                        @endif
                    </label>
                    <label class="ao-anc-row">
                        <span>Quantity</span>
                        {{-- Debounced: plain .live fired a full server round-trip per
                             keystroke, which is what made this page feel slow to type in
                             (user feedback, 2026-09-04). Half a second after typing stops
                             is soon enough for the summary. --}}
                        <input type="number" min="1" wire:model.live.debounce.500ms="items.{{ $index }}.quantity" class="ao-ano-qty">
                    </label>
                    <label class="ao-anc-row">
                        <span>Price Override</span>
                        <span class="ao-anc-field">
                            <input type="text" inputmode="decimal" wire:model.live.debounce.500ms="items.{{ $index }}.priceOverride" placeholder="0.00">
                            <i>(Only enter to manually override default product pricing)</i>
                        </span>
                    </label>

                    {{-- Core's own ConfigOption tree and, when this line's product has a
                         server, that server's own checkout fields — ProxyPanel's Region
                         among them, flags included. Both come through the same
                         ExtensionHelper call the storefront's own checkout uses, so this
                         offers exactly what a customer placing the same order would see.
                         Leandro (issue #10): no "Configurable Options" heading — Paymenter
                         does not present these as WHMCS's configurable-options concept, so
                         the fields sit directly under the product they belong to. --}}
                    @if ($optionsByItem[$index]->isNotEmpty() || $checkoutFieldsByItem[$index] !== [])
                        <div class="ao-ano-configs">

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
                                            @php
                                                $childOptions = $option->children->map(fn ($child) => [
                                                    'value' => $child->id, 'label' => $child->name, 'group' => false,
                                                ])->all();
                                            @endphp
                                            @include('adminops::partials.select', [
                                                'model' => "items.{$index}.configOptions.{$option->id}",
                                                'live' => true, 'options' => $childOptions,
                                                'key' => "cfgopt-select-{$index}-{$option->id}",
                                            ])
                                    @endswitch
                                </label>
                            @endforeach

                            @foreach ($checkoutFieldsByItem[$index] as $field)
                                <label class="ao-anc-row">
                                    <span>{{ $field['label'] ?? $field['name'] }}</span>
                                    @switch($field['type'] ?? 'text')
                                        @case('select')
                                        @case('radio')
                                            @php
                                                $allDisabled = !empty($field['disabled_options'])
                                                    && count($field['disabled_options']) === count($field['options'] ?? []);
                                            @endphp
                                            @if ($allDisabled)
                                                {{-- Every option disabled (e.g. every region out of
                                                     stock) — a plain disabled select says that
                                                     plainly, matching the old control's behaviour. --}}
                                                <select disabled>
                                                    <option>{{ reset($field['options']) ?: '—' }}</option>
                                                </select>
                                            @else
                                                {{-- This is where a Windows admin was reading "us"
                                                     instead of 🇺🇸 — a native <option> popup is
                                                     OS-drawn there and cannot show the flag webfont
                                                     or the page's own font at all. Rendered as real
                                                     page content instead, both finally show. --}}
                                                @php
                                                    $fieldOptions = collect($field['options'] ?? [])->map(fn ($label, $value) => [
                                                        'value' => (string) $value, 'label' => $label, 'group' => false,
                                                        'disabled' => in_array($value, $field['disabled_options'] ?? [], true),
                                                    ])->values()->all();
                                                @endphp
                                                {{-- Keyed by the product: this is the Region
                                                     field, and a different product can mean a
                                                     different server with a different, freshly
                                                     fetched stock list. --}}
                                                @include('adminops::partials.select', [
                                                    'model' => "items.{$index}.checkoutConfig.{$field['name']}",
                                                    'live' => true, 'options' => $fieldOptions,
                                                    'key' => "checkout-select-{$index}-{$field['name']}-{$item['productId']}",
                                                ])
                                            @endif
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

        </div>

        <aside class="ao-ano-side">
            <h4>Order Summary</h4>
            <div class="ao-ano-card">
                @forelse ($summary['lines'] as $line)
                    {{-- The reference's wording: "1 x Category - Product", the cycle on its
                         own muted line beneath, the price against the first line. --}}
                    <div class="ao-ano-line">
                        <span>{{ $line['quantity'] }} x {{ $line['label'] }}{{ $line['domain'] !== '' ? ' — ' . $line['domain'] : '' }}</span>
                        <span>${{ number_format($line['total'], 2) }} {{ $summary['currency'] }}</span>
                    </div>
                    @if ($line['cycle'])
                        <div class="ao-ano-note">{{ $line['cycle'] }}</div>
                    @endif
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

            {{-- Beside the button that raised them, not at the foot of the left column a
                 screen away (user feedback, 2026-09-04). --}}
            @if ($errors->any())
                <ul class="ao-anc-errors">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif

            <button type="submit" class="ao-find-go ao-ano-submit" wire:loading.attr="disabled">
                <span wire:loading.remove>Submit Order &raquo;</span>
                <span wire:loading>Working&hellip;</span>
            </button>
        </aside>
    </form>
</x-filament-panels::page>
