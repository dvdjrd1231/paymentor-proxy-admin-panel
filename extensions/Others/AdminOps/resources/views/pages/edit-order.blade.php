{{--
    The reference's order view, reached from the list's ID and Order # links: the facts
    band — Date, Order #, Client and their address, Order Placed By | Payment Method,
    Amount, Invoice #, Status, IP Address, Promotion Code, Affiliate — then Order Items
    with the Total Due row, and the whole-order buttons. Item's "Product/Service" link
    lands on the client's Products/Services tab with this service unfolded, exactly the
    reference's hop.
--}}
<x-filament-panels::page>
    <div class="ao-mu ao-eo">
        <div class="ao-find ao-of">
            <div class="ao-of-rows">
                <div class="ao-of-row">
                    <span class="ao-of-label">Date</span>
                    <span class="ao-eo-fact">{{ $order->created_at?->format('m/d/Y H:i') }}</span>
                    <span class="ao-of-label">Payment Method</span>
                    <span class="ao-eo-fact">{{ $payment['method'] }}</span>
                </div>
                <div class="ao-of-row">
                    <span class="ao-of-label">Order #</span>
                    <span class="ao-eo-fact">{{ $number }} (ID: {{ $order->id }})</span>
                    <span class="ao-of-label">Amount</span>
                    <span class="ao-eo-fact">${{ number_format((float) $order->total, 2) }} {{ $order->currency_code }}</span>
                </div>
                <div class="ao-of-row">
                    <span class="ao-of-label">Client</span>
                    <span class="ao-eo-fact">
                        <a class="ao-link" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $order->user_id]) }}">
                            {{ trim(($order->user->first_name ?? '') . ' ' . ($order->user->last_name ?? '')) ?: $order->user->email }}
                        </a>
                        @foreach ($addressLines as $line)
                            <br>{{ $line }}
                        @endforeach
                    </span>
                    <span class="ao-of-label">Invoice #</span>
                    <span class="ao-eo-fact">
                        @if ($invoice)
                            <a class="ao-link" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageInvoices::getUrl(['q' => $invoice->number ?: $invoice->id]) }}">
                                {{ $invoice->number ?: $invoice->id }}
                            </a>
                        @else
                            —
                        @endif
                    </span>
                </div>
                <div class="ao-of-row">
                    <span class="ao-of-label">Order Placed By</span>
                    <span class="ao-eo-fact">
                        User: {{ trim(($order->user->first_name ?? '') . ' ' . ($order->user->last_name ?? '')) ?: '—' }} (ID: {{ $order->user_id }})
                        <br><i>{{ $order->user->email ?? '' }}</i>
                    </span>
                    <span class="ao-of-label">Status</span>
                    <span class="ao-eo-fact">
                        {{-- Picking a state runs the matching whole-order action, the
                             reference's own behaviour for this select. --}}
                        <select class="ao-of-md" wire:change="setStatus($event.target.value)">
                            @foreach (['pending' => 'Pending', 'active' => 'Active', 'suspended' => 'Suspended', 'cancelled' => 'Terminated'] as $value => $label)
                                <option value="{{ $value }}" @selected($statusNow[0] === $label)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </span>
                </div>
                <div class="ao-of-row">
                    <span class="ao-of-label"></span>
                    <span class="ao-eo-fact"></span>
                    <span class="ao-of-label">IP Address</span>
                    <span class="ao-eo-fact">
                        @if ($ip)
                            {{ $ip }} -
                            <a class="ao-link" href="https://ipinfo.io/{{ $ip }}" target="_blank" rel="noopener">Lookup</a> |
                            <a class="ao-link" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageOrders::getUrl(['ip' => $ip]) }}">Filter</a> |
                            <span class="ao-eo-dead" title="Paymenter keeps no IP ban list, so there is nothing for this to write to">Ban</span>
                        @else
                            <span title="This order predates the audit trail, so no IP was recorded">Not recorded</span>
                        @endif
                    </span>
                </div>
                <div class="ao-of-row">
                    <span class="ao-of-label"></span>
                    <span class="ao-eo-fact"></span>
                    <span class="ao-of-label">Promotion Code</span>
                    <span class="ao-eo-fact">{{ $coupon ?? '—' }}</span>
                </div>
                <div class="ao-of-row">
                    <span class="ao-of-label"></span>
                    <span class="ao-eo-fact"></span>
                    <span class="ao-of-label">Affiliate</span>
                    <span class="ao-eo-fact">
                        {{ $affiliateName ?? 'None' }} -
                        <span class="ao-eo-dead" title="Affiliate attribution is written at order time by the referral link; there is no manual reassignment to run">Manual Assign</span>
                    </span>
                </div>
            </div>
        </div>

        <div class="ao-eo-items-head">
            <h4>Order Items</h4>
            <span class="ao-eo-dead ao-link" title="Paymenter orders carry no notes column — service and client notes live on their own screens">Add Notes</span>
        </div>

        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Description</th>
                    <th>Billing Cycle</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Payment Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($order->services as $service)
                    @php
                        [$payLabel, $payClass] = \Paymenter\Extensions\Others\AdminOps\Admin\Pages\EditOrder::linePayment($service);
                    @endphp
                    <tr>
                        <td>
                            <a class="ao-link" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $order->user_id, 'tab' => 'services', 'service' => $service->id]) }}">
                                Product/Service
                            </a>
                        </td>
                        <td class="ao-mu-left">
                            {{ trim(($service->product?->category?->name ? $service->product->category->name . ' - ' : '') . ($service->product?->name ?? '—')) }}
                        </td>
                        <td>{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ProductsServices::cycle($service) }}</td>
                        <td>${{ number_format((float) $service->price * max(1, (int) $service->quantity), 2) }} {{ $order->currency_code }}</td>
                        <td>{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ProductsServices::statusLabel((string) $service->status) }}</td>
                        <td><span class="{{ $payClass }}">{{ $payLabel }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
                <tr class="ao-eo-total">
                    <td colspan="4"></td>
                    <td class="ao-eo-total-label">Total Due:</td>
                    <td class="ao-eo-total-value">${{ number_format((float) $order->total, 2) }} {{ $order->currency_code }}</td>
                </tr>
            </tbody>
        </table>

        {{-- The reference's six buttons. Cancel & Refund and Set as Fraud are honestly
             dead: refunds are issued from the invoice screen where the gateway calls are
             wired, and Paymenter's services have no fraud status to set. --}}
        <div class="ao-eo-actions">
            <button type="button" class="ao-eo-accept" wire:click="acceptOrder"
                wire:confirm="Activate every pending service on this order?">&#10004; Accept Order</button>
            <button type="button" class="ao-eo-cancel" wire:click="cancelOrder"
                wire:confirm="Cancel every running service on this order?">Cancel Order</button>
            <button type="button" class="ao-eo-cancel ao-eo-dead-btn"
                title="Refunds are issued from the invoice screen, where the gateway refund calls are wired" disabled>Cancel &amp; Refund</button>
            <button type="button" class="ao-eo-cancel ao-eo-dead-btn"
                title="Paymenter services have no fraud status — the Fraud Orders view says so by matching nothing" disabled>Set as Fraud</button>
            <button type="button" class="ao-eo-pending" wire:click="setOrderPending"
                wire:confirm="Set every active/suspended service on this order back to pending? The service itself keeps running on its panel — this only corrects the record.">Set Back to Pending</button>
            <button type="button" class="ao-eo-delete" wire:click="deleteOrder"
                wire:confirm="Delete order #{{ $order->id }}? This cannot be undone.">Delete Order</button>
        </div>
    </div>
</x-filament-panels::page>
