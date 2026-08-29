{{--
    Invoices, to the reference screenshot: the Paid/Unpaid/Overdue totals bar, Search/Filter
    tab, records line with Jump to Page, and the navy grid with the view icon at the end.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        @if ($bar)
            <div class="ao-inv-bar">
                @foreach ($bar as $code => $sums)
                    <span class="ao-inv-bar-cur">
                        {{ $code }}
                        Paid: <b class="ao-inv-bar-paid">${{ number_format($sums['paid'] ?? 0, 2) }} {{ $code }}</b>
                        Unpaid: <b class="ao-inv-bar-unpaid">${{ number_format($sums['unpaid'] ?? 0, 2) }} {{ $code }}</b>
                        Overdue: <b class="ao-inv-bar-overdue">${{ number_format($sums['overdue'] ?? 0, 2) }} {{ $code }}</b>
                    </span>
                @endforeach
            </div>
        @endif

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
                        <span>Client Name/Email or Invoice ID</span>
                        <input type="text" wire:model="q" placeholder="Client name, email or invoice ID">
                    </label>
                    <label class="ao-find-field">
                        <span>Status</span>
                        <select wire:model="status">
                            <option value="">Any</option>
                            <option value="paid">Paid</option>
                            <option value="draft">Draft</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="overdue">Overdue</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="refunded">Refunded</option>
                            <option value="collections">Collections</option>
                            <option value="payment_pending">Payment Pending</option>
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
                {{ number_format($invoices->total()) }} Records Found{{ $invoices->total() > 0 ? ', Showing ' . number_format($invoices->firstItem()) . ' to ' . number_format($invoices->lastItem()) : '' }}
            </span>
            <label class="ao-mu-jump">
                Jump to Page:
                <select wire:change="jump($event.target.value)">
                    @foreach (range(1, max(1, $invoices->lastPage())) as $number)
                        <option value="{{ $number }}" @selected($number === $invoices->currentPage())>{{ $number }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th class="ao-mu-check"><input type="checkbox" data-ao-check-all></th>
                    <th>Invoice # &#9662;</th>
                    <th>Client Name</th>
                    <th>Invoice Date</th>
                    <th>Due Date</th>
                    <th>Last Capture Attempt</th>
                    <th>Total</th>
                    <th>Payment Method</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    @php
                        [$statusLabel, $statusClass] = \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageInvoices::statusOf($invoice);
                        $edit = \App\Admin\Resources\InvoiceResource::getUrl('edit', ['record' => $invoice->id]);
                        $summary = $invoice->user_id
                            ? \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $invoice->user_id])
                            : null;
                        $lastTry = $invoice->transactions->sortByDesc('created_at')->first();
                    @endphp
                    <tr>
                        <td class="ao-mu-check"><input type="checkbox" data-ao-check value="{{ $invoice->id }}"></td>
                        <td><a href="{{ $edit }}">{{ $invoice->number ?? $invoice->id }}</a></td>
                        <td>
                            @if ($summary)
                                <a href="{{ $summary }}">{{ trim(($invoice->user->first_name ?? '') . ' ' . ($invoice->user->last_name ?? '')) ?: ($invoice->user->email ?? '—') }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $invoice->created_at?->format('m/d/Y') }}</td>
                        <td>{{ $invoice->due_at?->format('m/d/Y') ?? '-' }}</td>
                        <td>{{ $invoice->status === 'pending' && $lastTry ? $lastTry->created_at?->format('m/d/Y') : 'N/A' }}</td>
                        <td>${{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency_code }}</td>
                        <td>{{ $lastTry?->gateway?->name ?? '—' }}</td>
                        <td><span class="{{ $statusClass }}">{{ $statusLabel }}</span></td>
                        <td class="ao-mu-actions">
                            <a href="{{ $edit }}" title="Open invoice">
                                <x-filament::icon icon="ri-file-list-2-line" class="ao-mu-cell-icon" />
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        <nav class="ao-mu-pages">
            <button type="button" wire:click="jump({{ $invoices->currentPage() - 1 }})"
                @disabled($invoices->onFirstPage())>&laquo; Previous Page</button>
            <span class="ao-mu-page-now">{{ $invoices->currentPage() }}</span>
            <button type="button" wire:click="jump({{ $invoices->currentPage() + 1 }})"
                @disabled(!$invoices->hasMorePages())>Next Page &raquo;</button>
        </nav>
    </div>
</x-filament-panels::page>
