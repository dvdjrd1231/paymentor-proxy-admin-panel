{{--
    Manage Orders, to the reference screenshot: Search/Filter tab, records line with Jump to
    Page, the navy grid with Payment Status and the derived Status, and the red delete dot
    with its "Are you sure?" before anything happens.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        <div class="ao-tx-tabs">
            <button type="button" class="ao-mu-tab {{ $this->filter ? 'ao-on' : '' }}" wire:click="toggleFilter">
                Search/Filter
            </button>
        </div>

        @if ($this->filter)
            {{-- The reference's Search/Filter panel: two columns of striped label/field rows —
                 Order ID, Order #, Date Range, Amount | Client, Payment Status, Status,
                 IP Address — with the Search button centred underneath. Every field filters
                 for real except IP Address, which Paymenter does not record; that one is
                 honestly dead with the reason on its title. --}}
            <form class="ao-find ao-of" autocomplete="off" wire:submit.prevent="search">
                <div class="ao-of-rows">
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-of-oid">Order ID</label>
                        <span><input @nofill id="ao-of-oid" class="ao-of-sm" type="text" inputmode="numeric"
                            wire:model="oid" placeholder="ID"></span>
                        <label class="ao-of-label" for="ao-of-client">Client</label>
                        <span><input @nofill id="ao-of-client" class="ao-of-lg" type="text" list="ao-of-clients"
                            wire:model="client" placeholder="Start Typing to Search Clients"></span>
                    </div>
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-of-onum">Order #</label>
                        <span><input @nofill id="ao-of-onum" class="ao-of-md" type="text" inputmode="numeric"
                            wire:model="onum" placeholder="Order number"></span>
                        <label class="ao-of-label" for="ao-of-pay">Payment Status</label>
                        <span><select @nofill id="ao-of-pay" class="ao-of-sm" wire:model="pay">
                            <option value="">Any</option>
                            <option value="complete">Complete</option>
                            <option value="incomplete">Incomplete</option>
                        </select></span>
                    </div>
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-of-dates">Date Range</label>
                        @include('adminops::partials.datepicker', [
                            'model' => 'dates', 'range' => true, 'id' => 'ao-of-dates',
                            'placeholder' => 'MM/DD/YYYY - MM/DD/YYYY', 'class' => 'ao-of-lg',
                        ])
                        <label class="ao-of-label" for="ao-of-status">Status</label>
                        <span><select @nofill id="ao-of-status" class="ao-of-sm" wire:model="status">
                            <option value="">Any</option>
                            <option value="pending">Pending</option>
                            <option value="active">Active</option>
                            <option value="fraud">Fraud</option>
                            <option value="suspended">Suspended</option>
                            <option value="cancelled">Cancelled</option>
                        </select></span>
                    </div>
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-of-amount">Amount</label>
                        <span><input @nofill id="ao-of-amount" class="ao-of-sm" type="text" inputmode="decimal"
                            wire:model="amount" placeholder="0.00"></span>
                        <label class="ao-of-label" for="ao-of-ip">IP Address</label>
                        <span><input @nofill id="ao-of-ip" class="ao-of-md" type="text"
                            wire:model="ip" placeholder="e.g. 203.0.113.7"
                            title="Matches the IP the order's creation was audited from"></span>
                    </div>
                </div>
                <datalist id="ao-of-clients">
                    @foreach ($clientOptions as $option)
                        <option value="{{ $option }}"></option>
                    @endforeach
                </datalist>
                <button type="submit" class="ao-of-go">Search</button>
            </form>
        @endif

        <div class="ao-mu-line">
            <span>
                {{ number_format($orders->total()) }} Records Found{{ $orders->total() > 0 ? ', Showing ' . number_format($orders->firstItem()) . ' to ' . number_format($orders->lastItem()) : '' }}
            </span>
            <label class="ao-mu-jump">
                Jump to Page:
                <select wire:change="jump($event.target.value)">
                    @foreach (range(1, max(1, $orders->lastPage())) as $number)
                        <option value="{{ $number }}" @selected($number === $orders->currentPage())>{{ $number }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th class="ao-mu-check"><input type="checkbox" data-ao-check-all></th>
                    <th>ID &#9662;</th>
                    <th>Order #</th>
                    <th>Date</th>
                    <th>Client Name</th>
                    <th>Payment Method</th>
                    <th>Total</th>
                    <th>Payment Status</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    @php
                        [$statusLabel, $statusClass] = \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageOrders::statusOf($order);
                        $payment = \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageOrders::paymentOf($order);
                        $edit = \Paymenter\Extensions\Others\AdminOps\Admin\Pages\EditOrder::getUrl(['record' => $order->id]);
                        $summary = $order->user_id
                            ? \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $order->user_id])
                            : null;
                    @endphp
                    <tr data-ao-email="{{ $order->user?->email }}">
                        <td class="ao-mu-check"><input type="checkbox" data-ao-check wire:model="selected" value="{{ $order->id }}"></td>
                        <td><a href="{{ $edit }}">{{ $order->id }}</a></td>
                        <td><a href="{{ $edit }}">{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageOrders::numberOf($order) }}</a></td>
                        <td>{{ $order->created_at?->format('m/d/Y H:i') }}</td>
                        <td>
                            @if ($summary)
                                <a href="{{ $summary }}">{{ trim(($order->user->first_name ?? '') . ' ' . ($order->user->last_name ?? '')) ?: $order->user->email }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $payment['method'] }}</td>
                        <td>${{ number_format((float) $order->total, 2) }} {{ $order->currency_code }}</td>
                        <td><span class="{{ $payment['class'] }}">{{ $payment['label'] }}</span></td>
                        <td><span class="{{ $statusClass }}">{{ $statusLabel }}</span></td>
                        <td class="ao-mu-actions">
                            <button type="button" class="ao-mo-delete" title="Delete order"
                                wire:click="askDelete({{ $order->id }})">
                                <x-filament::icon icon="ri-indeterminate-circle-fill" class="ao-mu-cell-icon" />
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="ao-mu-selected">
            With Selected:
            <button type="button" class="ao-mo-accept" wire:click="acceptSelected"
                wire:confirm="Activate every pending service on the selected orders?">Accept Order</button>
            <button type="button" wire:click="cancelSelected"
                wire:confirm="Cancel every running service on the selected orders?">Cancel Order</button>
            {{-- The reference's other two bulk buttons. --}}
            <button type="button" class="ao-st-danger" wire:click="deleteSelected"
                wire:confirm="Delete the selected orders? Their services go; invoices are accounting records and stay.">Delete Order</button>
            <button type="button" data-ao-send-message>Send Message</button>
        </div>

        <nav class="ao-mu-pages">
            <button type="button" wire:click="jump({{ $orders->currentPage() - 1 }})"
                @disabled($orders->onFirstPage())>&laquo; Previous Page</button>
            <span class="ao-mu-page-now">{{ $orders->currentPage() }}</span>
            <button type="button" wire:click="jump({{ $orders->currentPage() + 1 }})"
                @disabled(!$orders->hasMorePages())>Next Page &raquo;</button>
        </nav>

        @if ($confirmingDelete)
            <div class="ao-mud-overlay" wire:click.self="$set('confirmingDelete', null)">
                <div class="ao-mud ao-mud-sm" role="alertdialog" aria-modal="true">
                    <div class="ao-mud-head">
                        Are you sure?
                        <button type="button" wire:click="$set('confirmingDelete', null)" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text">
                        <p>Are you sure you wish to delete order #{{ $confirmingDelete }}?</p>
                        <p>Its services will be removed. Invoices are accounting records and stay. Orders with active or suspended services cannot be deleted.</p>
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="$set('confirmingDelete', null)">Cancel</button>
                            <button type="button" class="ao-mud-delete" wire:click="deleteOrder">Delete</button>
                        </span>
                    </div>
                </div>
            </div>
        @endif
    </div>
    <script>
        (() => {
            const root = document.currentScript.closest('.fi-page') ?? document;
            root.addEventListener('change', (event) => {
                if (!event.target.matches('[data-ao-check-all]')) return;
                for (const box of root.querySelectorAll('[data-ao-check]')) {
                    if (box.checked !== event.target.checked) {
                        box.checked = event.target.checked;
                        // Livewire binds checkboxes on change, not input.
                        box.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            });

            // The reference's Send Message: the ticked rows' clients, addressed in the
            // admin's own mail client — the same one honest send every list here offers.
            root.addEventListener('click', (event) => {
                if (!event.target.closest('[data-ao-send-message]')) return;
                const picked = [...root.querySelectorAll('tbody tr')]
                    .filter((row) => row.querySelector('[data-ao-check]:checked'))
                    .map((row) => row.dataset.aoEmail).filter(Boolean);
                if (!picked.length) { alert('Tick at least one order first.'); return; }
                window.location.href = 'mailto:' + encodeURIComponent([...new Set(picked)].join(','));
            });
        })();
    </script>
</x-filament-panels::page>
