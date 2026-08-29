{{--
    Manage Orders, to the reference screenshot: Search/Filter tab, records line with Jump to
    Page, the navy grid with Payment Status and the derived Status, and the red delete dot
    with its "Are you sure?" before anything happens.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        <button type="button" class="ao-mu-tab {{ $this->filter ? 'ao-on' : '' }}" wire:click="toggleFilter">
            Search/Filter
        </button>

        @if ($this->filter)
            <form class="ao-find" wire:submit.prevent="search">
                <span class="ao-find-glass" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" width="18" height="18">
                        <circle cx="9" cy="9" r="5.5" /><path d="M13.5 13.5 17 17" />
                    </svg>
                </span>
                <div class="ao-find-fields">
                    <label class="ao-find-field ao-find-wide">
                        <span>Client Name/Email or Order ID</span>
                        <input type="text" wire:model="q" placeholder="Client name, email or order ID">
                    </label>
                    <label class="ao-find-field">
                        <span>Status</span>
                        <select wire:model="status">
                            <option value="">Any</option>
                            <option value="pending">Pending</option>
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </label>
                </div>
                <button type="submit" class="ao-find-go">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" width="13" height="13" aria-hidden="true">
                        <circle cx="9" cy="9" r="5.5" /><path d="M13.5 13.5 17 17" />
                    </svg>
                    Search
                </button>
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
                        $edit = \App\Admin\Resources\OrderResource::getUrl('edit', ['record' => $order->id]);
                        $summary = $order->user_id
                            ? \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $order->user_id])
                            : null;
                    @endphp
                    <tr>
                        <td class="ao-mu-check"><input type="checkbox" data-ao-check value="{{ $order->id }}"></td>
                        <td><a href="{{ $edit }}">{{ $order->id }}</a></td>
                        <td><a href="{{ $edit }}">{{ $payment['number'] ?? '—' }}</a></td>
                        <td>{{ $order->created_at?->format('m/d/Y H:i') }}</td>
                        <td>
                            @if ($summary)
                                <a href="{{ $summary }}">{{ trim(($order->user->first_name ?? '') . ' ' . ($order->user->last_name ?? '')) ?: $order->user->email }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $payment['method'] }}</td>
                        <td>{{ $order->formattedTotal }}</td>
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
</x-filament-panels::page>
