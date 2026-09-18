{{-- One tab of the client profile: one list, of one thing. --}}
@php
    $count = $rows->count();

    // Names for the Log's User column, resolved once rather than per row.
    $logNames = collect($logUsers ?? [])->mapWithKeys(fn ($who) => [
        $who->id => trim($who->first_name . ' ' . $who->last_name) ?: $who->email,
    ])->all();
@endphp

{{-- The reference heads each of these tabs with its own buttons, and two of them with a
     band of four figures. Everything below is real: the counts are queries, and each
     button goes to the screen that actually does the thing. --}}
<div class="ao-ct-head @if ($tab === 'billable') ao-ct-head-bare @endif" style="padding: 5px; background: #efefef;">
    @switch($tab)
        @case('invoices')
            {{-- It pointed at the global invoice list, which carried no client and created
                 nothing (Leandro, 2026-09-17). It now raises this client's draft and opens
                 it, exactly as the Summary panel's Create Invoice already did. --}}
            <button type="button" class="ao-mu-tab ao-bt-head-btn" wire:click="toggleInvoiceSearch">
                &#128269; Search</button>
            <button type="button" class="ao-mu-tab ao-bt-head-btn ao-bt-primary" wire:click="createInvoice">
                &#10010; Create Invoice</button>
            @break
        {{-- Quotes is not here: it has its own branch on the page, with its own button. --}}
        @case('transactions')
            <button type="button" class="ao-mu-tab ao-bt-head-btn ao-bt-primary"
                wire:click="openAddTransaction">&#10010; Add New Transaction</button>
            @break
        @case('tickets')
            {{-- The reference heads this tab with a Search beside Open New Ticket. --}}
            <a class="ao-mu-tab ao-bt-head-btn" href="{{ $urls['tickets'] }}">&#128269; Search</a>
            <a class="ao-mu-tab ao-bt-head-btn ao-bt-primary" href="{{ $urls['newTicket'] }}">&#10010; Open New Ticket</a>
            @break
        @case('billable')
            {{-- The reference's pair. Time billing is this same form asked in hours:
                 a time entry is hours at a rate, which is the row the other button
                 writes as a quantity at an amount. {@see BillableItemsList::$time} --}}
            <button type="button" class="ao-mu-tab ao-bt-head-btn"
                wire:click="openTimeEntries">Add Time Billing Entries</button>
            <button type="button" class="ao-mu-tab ao-bt-head-btn ao-bt-primary"
                wire:click="openAddBillable('qty')">&#10010; Add Billable Item</button>
            @break
    @endswitch
</div>

@if ($tab === 'tickets' && $ticketStats)
    <div class="ao-ct-cards">
        @foreach ($ticketStats as $label => $value)
            <div class="ao-ct-card">
                <b>{{ $value }}</b>
                <span>{{ strtoupper($label) }}</span>
            </div>
        @endforeach
    </div>
@endif

@if ($tab === 'transactions' && $addTransaction)
    @include('adminops::partials.transaction-add')
@else

@if ($tab === 'transactions' && $totals)
    <div class="ao-ct-cards">
        {{-- The reference's four figures, in its order. Balance is what reached the
             merchant: what came in, less what the gateways took and anything refunded. --}}
        @foreach ([
            'TOTAL IN' => $totals['in'],
            'TOTAL FEES' => $totals['fees'] ?? 0,
            'TOTAL OUT' => $totals['out'],
            'BALANCE' => $totals['in'] - ($totals['fees'] ?? 0) - $totals['out'],
        ] as $label => $value)
            <div class="ao-ct-card">
                <b>{{ number_format($value, 2) }}</b>
                <span>{{ $label }}</span>
            </div>
        @endforeach
    </div>
@endif

{{-- The reference's Filter Log band, above the list, behind its own button. It was always
     open, which is a panel of four fields between you and the log every time you open the
     tab; the reference folds it away and lets you ask for it. --}}
@if ($tab === 'log')
    <div class="ao-ct-head ao-ct-head-end">
        <button type="button" class="ao-mu-tab" :class="{ 'ao-on': logFilterOpen }"
            @click="logFilterOpen = !logFilterOpen">Filter Log</button>
    </div>

    <div class="ao-anc-card ao-ct-filter" x-show="logFilterOpen" x-cloak>
        <label class="ao-anc-row">
            <span>Date</span>
            <input type="date" wire:model.live="logFilter.date">
        </label>
        <label class="ao-anc-row">
            <span>Description</span>
            <input type="text" wire:model.live.debounce.500ms="logFilter.description"
                placeholder="Event, record type or changed value">
        </label>
        <label class="ao-anc-row">
            <span>Username</span>
            <select wire:model.live="logFilter.user">
                <option value="">Any</option>
                @foreach (($logUsers ?? collect()) as $who)
                    <option value="{{ $who->id }}">{{ trim($who->first_name . ' ' . $who->last_name) ?: $who->email }}</option>
                @endforeach
            </select>
        </label>
        <label class="ao-anc-row">
            <span>IP Address</span>
            <input type="text" wire:model.live.debounce.500ms="logFilter.ip">
        </label>
    </div>
@endif

{{-- Billable Items carries its own band, above its Invoiced table where the reference
     puts it, so the shared one would be a second count over the wrong table. --}}
@if ($tab === 'invoices' && $invoiceSearch)
    @include('adminops::partials.invoice-search')
@endif

@unless ($tab === 'billable')
    @include('adminops::partials.records-band', [
        'total' => $rowTotal(), 'page' => $page, 'perPage' => $perPage,
    ])
@endunless

{{-- No section wrapper, and the panel's own grid rather than `ao-list`: the reference puts
     the navy grid straight under the records band, with no grey heading strip naming the
     tab you already clicked. Every list tab comes through here, so this is what made most
     of them read as a different table from the rest of the panel (Leandro, 2026-09-10:
     "some tabs are different with the WHMCS client profile page tabs content"). --}}
@if ($tab === 'billable')
    <div class="ao-ct-list">
        @include('adminops::partials.billable-tab')
    </div>
@else
<div class="ao-ct-list">
    @if ($count === 0)
        <table class="ao-mu-grid">
            <tbody>
                <tr><td class="ao-mu-none">
                    @switch($tab)
                        @case('billable') Nothing has been charged to this account outside their products. @break
                        @case('transactions') No money has moved on this account yet. @break
                        @case('emails') Nothing has been sent to this customer yet. @break
                        @case('log') Nothing has been recorded against this account. @break
                        @default No Records Found.
                    @endswitch
                </td></tr>
            </tbody>
        </table>
    @else
        <table class="ao-mu-grid">
            <thead>
                <tr>
                    @switch($tab)
                        @case('services')
                            <th>ID</th><th>Product</th><th>Status</th><th class="ao-num">Price</th><th>Expires</th>
                            @break
                        @case('billable')
                            <th>Description</th><th class="ao-num">Amount</th><th>Invoice</th><th>Added</th>
                            @break
                        @case('invoices')
                            <th class="ao-bt-tick"><input type="checkbox" aria-label="Select all"
                                x-on:change="$root.querySelectorAll('.ao-inv-tick').forEach(b => { b.checked = $event.target.checked; b.dispatchEvent(new Event('input')) })"></th>
                            <th>Invoice #</th><th>Invoice Date</th><th>Due Date</th><th>Date Paid</th>
                            <th class="ao-num">Total</th><th>Payment Method</th><th>Status</th>
                            @break
                        @case('transactions')
                            <th>Date</th><th>Method</th><th>Description</th>
                            <th class="ao-num">In</th><th class="ao-num">Fees</th><th class="ao-num">Out</th>
                            @break
                        @case('tickets')
                            <th>ID</th><th>Subject</th><th>Status</th><th>Opened</th>
                            @break
                        @case('emails')
                            <th>Date</th><th class="ao-mu-left">Subject</th><th class="ao-em-acts"></th>
                            @break
                        @case('log')
                            <th>Date</th><th>Log Entry</th><th>User</th><th>IP Address</th>
                            @break
                    @endswitch
                </tr>
            </thead>
            {{-- The reference colours a status rather than printing it grey: green for
                 settled, red for wanting money or attention, amber for in-between, grey
                 for closed. Every tab's status cell reads its word through this. --}}
            @php
                $statusTone = fn (?string $state): string => match (strtolower((string) $state)) {
                    'paid', 'active', 'completed', 'closed_won' => 'ao-tag-success',
                    'unpaid', 'overdue', 'failed', 'suspended', 'open' => 'ao-tag-danger',
                    'pending', 'draft', 'in_progress', 'on_hold', 'replied' => 'ao-tag-warning',
                    default => '',
                };
            @endphp
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @switch($tab)
                            @case('services')
                                {{-- The reference's hop: the ID opens this profile's own
                                     Products/Services editor with the service selected. --}}
                                <td><a class="ao-link" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $row->user_id, 'tab' => 'services', 'service' => $row->id]) }}">#{{ $row->id }}</a></td>
                                <td>{{ $row->product?->name ?? 'product gone' }}</td>
                                <td><span class="ao-tag {{ $statusTone($row->status) }}">{{ $row->status }}</span></td>
                                <td class="ao-num">{{ number_format((float) $row->price, 2) }} {{ $row->currency_code }}</td>
                                <td>{{ $row->expires_at?->format('j M Y') ?? '—' }}</td>
                                @break

                            @case('billable')
                                <td>{{ $row->description }}</td>
                                <td class="ao-num">
                                    {{ number_format((float) $row->amount * (float) $row->quantity, 2) }}
                                    {{ $row->currency_code }}
                                </td>
                                <td>
                                    @if ($row->invoice_id)
                                        <a class="ao-link" href="{{ $urls['invoice']($row->invoice_id) }}">#{{ $row->invoice_id }}</a>
                                    @else
                                        <span class="ao-tag ao-tag-warning">Uninvoiced</span>
                                    @endif
                                </td>
                                <td>{{ \Carbon\Carbon::parse($row->created_at)->format('j M Y') }}</td>
                                @break

                            @case('invoices')
                                @php
                                    // Paid date and method both come from the transaction that
                                    // settled it: an invoice row carries neither.
                                    $settled = $row->transactions
                                        ->firstWhere('status', \App\Enums\InvoiceTransactionStatus::Succeeded);
                                @endphp
                                <td class="ao-bt-tick">
                                    <input class="ao-inv-tick" type="checkbox"
                                        value="{{ $row->id }}" wire:model.live="invoiceChosen">
                                </td>
                                <td><a class="ao-link" href="{{ $urls['invoice']($row->id) }}">#{{ $row->number ?: $row->id }}</a></td>
                                <td>{{ $row->created_at?->format('d/m/Y') ?? '—' }}</td>
                                <td>{{ $row->due_at?->format('d/m/Y') ?? '—' }}</td>
                                <td>{{ $settled?->created_at?->format('d/m/Y') ?? '-' }}</td>
                                <td class="ao-num">{{ number_format((float) $row->total, 2) }} {{ $row->currency_code }}</td>
                                <td>{{ $settled?->gateway?->name ?? '—' }}</td>
                                <td><span class="ao-tag {{ $statusTone($row->status) }}">{{ $row->status }}</span></td>
                                @break

                            @case('transactions')
                                <td>{{ $row['at']?->format('j M Y H:i') ?? '—' }}</td>
                                <td>{{ $row['method'] }}</td>
                                <td>{{ $row['description'] }}</td>
                                <td class="ao-num">{{ $row['in'] > 0 ? number_format($row['in'], 2) : '—' }}</td>
                                <td class="ao-num">{{ ($row['fee'] ?? 0) > 0 ? number_format($row['fee'], 2) : '—' }}</td>
                                <td class="ao-num ao-tx-out">{{ $row['out'] > 0 ? number_format($row['out'], 2) : '—' }}</td>
                                @break

                            @case('tickets')
                                <td><a class="ao-link" href="{{ $urls['ticket']($row->id) }}">#{{ $row->id }}</a></td>
                                <td>{{ $row->subject }}</td>
                                <td><span class="ao-tag {{ $statusTone($row->status) }}">{{ $row->status }}</span></td>
                                <td>{{ $row->created_at?->format('j M Y') ?? '—' }}</td>
                                @break

                            @case('emails')
                                <td>{{ \Carbon\Carbon::parse($row->created_at)->format('d/m/Y H:i') }}</td>
                                <td>{{ $row->subject ?? $row->title ?? '—' }}</td>
                                {{-- The reference's two row actions: send it again, or drop
                                     it from the log. --}}
                                <td class="ao-em-acts">
                                    <button type="button" class="ao-em-act" title="Resend this message"
                                        wire:click="resendEmail({{ $row->id }})"
                                        wire:loading.attr="disabled" wire:target="resendEmail({{ $row->id }})">
                                        <x-filament::icon icon="ri-mail-send-fill" class="ao-mu-cell-icon" />
                                    </button>
                                    <button type="button" class="ao-em-act ao-em-act-del" title="Remove from the log"
                                        wire:click="deleteEmail({{ $row->id }})"
                                        wire:confirm="Remove this message from the log? The email itself was already delivered.">
                                        <x-filament::icon icon="ri-close-circle-fill" class="ao-mu-cell-icon" />
                                    </button>
                                </td>
                                @break


                            @case('log')
                                <td>{{ \Carbon\Carbon::parse($row->created_at)->format('d/m/Y H:i') }}</td>
                                {{-- The reference writes one sentence per entry. The audit
                                     row holds the verb and the record, so the sentence is
                                     built from those rather than stored a second time. --}}
                                <td class="ao-mu-left">
                                    {{ ucfirst($row->event) }} {{ \Illuminate\Support\Str::headline(class_basename($row->auditable_type)) }}
                                    &mdash; ID: {{ $row->auditable_id }}
                                </td>
                                <td>{{ $logNames[$row->user_id] ?? ($row->user_id ? 'User #' . $row->user_id : 'System') }}</td>
                                <td>{{ $row->ip_address ?: '—' }}</td>
                                @break
                        @endswitch
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- The reference's With Selected bar, under its invoice list. Mark Paid, Merge and
             Mass Pay are not here: the first would settle invoices no payment covers, and
             the other two need decisions from Leandro before they move money. Delete is out
             by his own instruction on the invoice screen (2026-09-16). --}}
        @if ($tab === 'invoices' && $count > 0)
            <div class="ao-bt-with ao-inv-with">
                <span>With Selected:</span>
                <button type="button" class="ao-pg-btn btn-success" wire:click="markChosenPaid"
                    wire:confirm="Mark the selected invoices paid? No transaction is recorded, so use this only for money that arrived outside the gateways.">Mark Paid</button>
                <button type="button" class="ao-pg-btn" wire:click="markChosenInvoices('pending')">Mark Unpaid</button>
                <button type="button" class="ao-pg-btn" wire:click="markChosenInvoices('cancelled')">Mark Cancelled</button>
                <button type="button" class="ao-pg-btn" wire:click="duplicateChosenInvoices">Duplicate Invoice</button>
                <button type="button" class="ao-pg-btn" wire:click="remindChosenInvoices">Send Reminder</button>
                <button type="button" class="ao-pg-btn ao-bt-del" wire:click="deleteChosenInvoices"
                    wire:confirm="Delete the selected invoices permanently? This cannot be undone. Invoices that have taken a payment are kept.">Delete</button>
            </div>
        @endif

        {{-- The reference's "see all" out to the full list, under the rows. --}}
        <p class="ao-catalogue-count">
            @switch($tab)
                @case('services') <a class="ao-link" href="{{ $urls['services'] }}">See all services</a> @break
                @case('invoices') <a class="ao-link" href="{{ $urls['invoices'] }}">See all invoices</a> @break
                @case('tickets') <a class="ao-link" href="{{ $urls['tickets'] }}">See all tickets</a> @break
            @endswitch
        </p>
    @endif
</div>

@include('adminops::partials.records-pager', [
    'total' => $rowTotal(), 'page' => $page, 'perPage' => $perPage,
])
@endif
@endif
