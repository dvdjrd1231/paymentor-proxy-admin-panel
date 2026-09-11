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
        {{-- Two independent columns, as the reference builds them — its own two tables
             side by side. Sharing one row grid meant the shorter left column had to pad
             itself with empty striped cells to reach the right column's seven rows. --}}
        <div class="ao-eo-facts">
            <div class="ao-find ao-of">
                <div class="ao-of-rows">
                    <div class="ao-of-row ao-of-row-single">
                        <span class="ao-of-label">Date</span>
                        <span class="ao-eo-fact">{{ $order->created_at?->format('m/d/Y H:i') }}</span>
                    </div>
                    <div class="ao-of-row ao-of-row-single">
                        <span class="ao-of-label">Order #</span>
                        <span class="ao-eo-fact">{{ $number }} (ID: {{ $order->id }})</span>
                    </div>
                    <div class="ao-of-row ao-of-row-single">
                        <span class="ao-of-label">Client</span>
                        <span class="ao-eo-fact">
                            <a class="ao-link" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $order->user_id]) }}">
                                {{ trim(($order->user->first_name ?? '') . ' ' . ($order->user->last_name ?? '')) ?: $order->user->email }}
                            </a>
                            @foreach ($addressLines as $line)
                                <br>{{ $line }}
                            @endforeach
                        </span>
                    </div>
                    <div class="ao-of-row ao-of-row-single">
                        <span class="ao-of-label">Order Placed By</span>
                        <span class="ao-eo-fact">
                            @if ($placedBy)
                                {{ $placedBy['role'] }}: {{ $placedBy['name'] }} (ID: {{ $placedBy['id'] }})
                                <br><i>{{ $placedBy['email'] }}</i>
                            @else
                                —
                            @endif
                        </span>
                    </div>
                </div>
            </div>

            <div class="ao-find ao-of">
                <div class="ao-of-rows">
                    <div class="ao-of-row ao-of-row-single">
                        <span class="ao-of-label">Payment Method</span>
                        <span class="ao-eo-fact">{{ $payment['method'] }}</span>
                    </div>
                    <div class="ao-of-row ao-of-row-single">
                        <span class="ao-of-label">Amount</span>
                        <span class="ao-eo-fact">${{ number_format((float) $order->total, 2) }} {{ $order->currency_code }}</span>
                    </div>
                    <div class="ao-of-row ao-of-row-single">
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
                    <div class="ao-of-row ao-of-row-single">
                        <span class="ao-of-label">Status</span>
                        <span class="ao-eo-fact">
                            {{-- Picking a state runs the matching whole-order action, the
                                 reference's own behaviour for this select. --}}
                            <select class="ao-of-sm" wire:change="setStatus($event.target.value)">
                                @foreach (['pending' => 'Pending', 'active' => 'Active', 'suspended' => 'Suspended', 'cancelled' => 'Terminated'] as $value => $label)
                                    <option value="{{ $value }}" @selected($statusNow[0] === $label)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </span>
                    </div>
                    <div class="ao-of-row ao-of-row-single">
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
                    <div class="ao-of-row ao-of-row-single">
                        <span class="ao-of-label">Promotion Code</span>
                        <span class="ao-eo-fact">{{ $coupon ?? '—' }}</span>
                    </div>
                    <div class="ao-of-row ao-of-row-single">
                        <span class="ao-of-label">Affiliate</span>
                        <span class="ao-eo-fact">
                            {{ $affiliateName ?? 'None' }} -
                            <span class="ao-eo-dead" title="Affiliate attribution is written at order time by the referral link; there is no manual reassignment to run">Manual Assign</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="ao-eo-items-head">
            <h4>Order Items</h4>
            <button type="button" class="ao-cp-link" wire:click="$toggle('notesOpen')">Add Notes</button>
        </div>

        @if ($notesOpen)
            <div class="ao-eo-notes">
                <textarea rows="3" wire:model="orderNotes" placeholder="Notes about this order — visible to staff only"></textarea>
                <span>
                    <button type="button" class="ao-find-go" wire:click="saveNotes">Save Notes</button>
                    <button type="button" class="ao-of-go" wire:click="$set('notesOpen', false)">Cancel</button>
                </span>
            </div>
        @elseif (trim($orderNotes) !== '')
            <p class="ao-eo-notes-text">{{ $orderNotes }}</p>
        @endif

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
                        {{-- The reference names the *kind* of line here — "Product/Service"
                             for a service row — and leaves the product itself to the
                             Description beside it (Leandro's own order 3770). The hop to
                             the service editor rides on it. --}}
                        <td class="ao-eo-item">
                            <a class="ao-link" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $order->user_id, 'tab' => 'services', 'service' => $service->id]) }}">
                                Product/Service
                            </a>
                        </td>
                        <td class="ao-mu-left">
                            {{ trim(($service->product?->category?->name ? $service->product->category->name . ' - ' : '') . ($service->product?->name ?? '—')) }}
                            @if ($lineDomain = $service->properties->firstWhere('key', 'domain')?->value)
                                <br>{{ $lineDomain }}
                                <br>(<a class="ao-eo-dom" href="http://{{ $lineDomain }}" target="_blank" rel="noopener">{{ $lineDomain }}</a>
                                <a class="ao-eo-tool" href="https://www.whois.com/whois/{{ $lineDomain }}" target="_blank" rel="noopener">whois</a>
                                <a class="ao-eo-tool" href="https://intodns.com/{{ $lineDomain }}" target="_blank" rel="noopener">intoDNS</a>)
                            @endif
                        </td>
                        <td>{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ProductsServices::cycle($service) }}</td>
                        <td>${{ number_format((float) $service->price * max(1, (int) $service->quantity), 2) }} {{ $order->currency_code }}</td>
                        <td>{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ProductsServices::statusLabel((string) $service->status) }}</td>
                        <td><span class="{{ $payClass }}">{{ $payLabel }}</span></td>
                    </tr>

                    {{-- The reference's provisioning row under each item: what happens when
                         the order is accepted. Only the two that decide something are live —
                         accepting currently always provisions, with no way to say "I set this
                         one up by hand", which is what Run Module Create is for. --}}
                    @if ($service->status === 'pending')
                        @php
                            $panelUser = $service->properties->firstWhere('key', 'proxy_username')?->value;
                            $panelPass = $service->properties->firstWhere('key', 'proxy_password')?->value;
                        @endphp
                        <tr class="ao-eo-provision">
                            <td colspan="6">
                                <div class="ao-eo-prov-line">
                                    <span class="ao-eo-prov-inert" title="{{ $panelUser ? 'The panel account this service runs as' : 'Credentials are issued by the panel when it creates the account, not chosen here' }}">
                                        Username: <input type="text" readonly value="{{ $panelUser }}">
                                        Password: <input type="text" readonly value="{{ $panelPass }}">
                                    </span>
                                    {{-- No Server box: the reference's own order carries none
                                         (Leandro's 3770), and a product names its server, so
                                         there was never a choice to make here. The server is
                                         on the service editor for anyone who needs it. --}}
                                    <label class="ao-check">
                                        <input type="checkbox" wire:model="runModuleCreate.{{ $service->id }}"
                                            @disabled(!$service->product?->server)>
                                        <span title="{{ $service->product?->server ? 'Unticking accepts this item without asking the panel to create anything' : 'This product has no server, so there is nothing to run' }}">Run Module Create</span>
                                    </label>
                                    <label class="ao-check">
                                        <input type="checkbox" wire:model="sendWelcome.{{ $service->id }}">
                                        <span title="When the module runs it sends this itself; unticking only takes effect when Run Module Create is off">Send Welcome Email</span>
                                    </label>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="6" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
                <tr class="ao-eo-total">
                    <td colspan="2"></td>
                    <td class="ao-eo-total-label">Total Due:</td>
                    <td class="ao-eo-total-value">${{ number_format((float) $order->total, 2) }} {{ $order->currency_code }}</td>
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>

        {{-- The reference's six buttons. Cancel & Refund and Set as Fraud are honestly
             dead: refunds are issued from the invoice screen where the gateway calls are
             wired, and Paymenter's services have no fraud status to set. --}}
        @php
            // The reference greys what this order's state leaves nothing to do: Accept once
            // nothing is pending, Set Back to Pending while everything already is.
            $statuses = $order->services->pluck('status');
            $hasPending = $statuses->contains('pending');
            $hasSuspended = $statuses->contains('suspended');
            $hasLive = $statuses->contains('active') || $hasSuspended;
            $canActivate = $hasPending || $hasSuspended;
        @endphp

        <div class="ao-eo-actions">
            <button type="button" class="ao-eo-accept" wire:click="acceptOrder" @disabled(!$canActivate)
                title="{{ $canActivate ? 'Activate every pending service on this order, and resume any suspended one' : 'Nothing on this order is pending or suspended' }}"
                wire:confirm="Activate every pending service on this order?">&#10004; Accept Order</button>
            <button type="button" class="ao-eo-cancel" wire:click="cancelOrder" @disabled(!$hasPending && !$hasLive)
                title="{{ $hasPending || $hasLive ? 'Cancel every running service on this order' : 'Nothing on this order is running' }}"
                wire:confirm="Cancel every running service on this order?">Cancel Order</button>
            <button type="button" class="ao-eo-cancel ao-eo-dead-btn"
                title="Refunds are issued from the invoice screen, where the gateway refund calls are wired" disabled>Cancel &amp; Refund</button>
            <button type="button" class="ao-eo-cancel ao-eo-dead-btn"
                title="Paymenter services have no fraud status — the Fraud Orders view says so by matching nothing" disabled>Set as Fraud</button>
            <button type="button" class="ao-eo-pending" wire:click="setOrderPending" @disabled($statuses->every(fn ($s) => $s === 'pending'))
                title="{{ $hasLive ? 'Put the running services back to pending' : 'This order is already pending' }}"
                wire:confirm="Set every active/suspended service on this order back to pending? The service itself keeps running on its panel — this only corrects the record.">Set Back to Pending</button>
            <button type="button" class="ao-eo-delete" wire:click="deleteOrder"
                wire:confirm="Delete order #{{ $order->id }}? This cannot be undone.">Delete Order</button>
        </div>
    </div>
</x-filament-panels::page>
