{{--
    One tab of the client profile: one list, of one thing.

    A single file with a switch rather than seven partials, because the tabs differ only in
    their columns — the empty state, the row limit, the "see all" link out to the core screen
    and the styling are the same argument seven times over, and seven files would be seven
    places to change it.

    `$rows` is whatever the page loaded for the showing tab; it is never all of them.
--}}
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
<div class="ao-ct-head">
    @switch($tab)
        @case('invoices')
            <a class="ao-mu-tab" href="{{ $urls['newInvoice'] }}">&#10010; Create Invoice</a>
            <a class="ao-mu-tab" href="{{ $urls['invoices'] }}">&#128269; Search</a>
            @break
        {{-- Quotes is not here: it has its own branch on the page, with its own button. --}}
        @case('transactions')
            <a class="ao-mu-tab" href="{{ $urls['newTransaction'] }}">&#10010; Add New Transaction</a>
            @break
        @case('tickets')
            <a class="ao-mu-tab" href="{{ $urls['newTicket'] }}">&#10010; Open New Ticket</a>
            @break
        @case('billable')
            <a class="ao-mu-tab" href="{{ $urls['billable'] }}">&#10010; Add Billable Item</a>
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

{{-- The reference's Filter Log band, above the list. --}}
@if ($tab === 'log')
    <div class="ao-anc-card ao-ct-filter">
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

@include('adminops::partials.records-band', [
    'total' => $rowTotal(), 'page' => $page, 'perPage' => $perPage,
])

<x-filament::section :heading="ucfirst(str_replace('_', ' ', $tab))">
    @if ($count === 0)
        <p class="ao-empty">
            @switch($tab)
                @case('billable') Nothing has been charged to this account outside their products. @break
                @case('transactions') No money has moved on this account yet. @break
                @case('emails') Nothing has been sent to this customer yet. @break
                @case('log') Nothing has been recorded against this account. @break
                @default Nothing here yet.
            @endswitch
        </p>
    @else
        <table class="ao-list">
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
                            <th>ID</th><th>Status</th><th class="ao-num">Total</th><th>Due</th>
                            @break
                        @case('transactions')
                            <th>Date</th><th>Method</th><th>Description</th>
                            <th class="ao-num">In</th><th class="ao-num">Fees</th><th class="ao-num">Out</th>
                            @break
                        @case('tickets')
                            <th>ID</th><th>Subject</th><th>Status</th><th>Opened</th>
                            @break
                        @case('emails')
                            <th>Date</th><th>Subject</th>
                            @break
                        @case('log')
                            <th>Date</th><th>Log Entry</th><th>User</th><th>IP Address</th>
                            @break
                    @endswitch
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @switch($tab)
                            @case('services')
                                {{-- The reference's hop: the ID opens this profile's own
                                     Products/Services editor with the service selected. --}}
                                <td><a class="ao-link" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $row->user_id, 'tab' => 'services', 'service' => $row->id]) }}">#{{ $row->id }}</a></td>
                                <td>{{ $row->product?->name ?? 'product gone' }}</td>
                                <td><span class="ao-tag">{{ $row->status }}</span></td>
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
                                <td><a class="ao-link" href="{{ $urls['invoice']($row->id) }}">#{{ $row->number ?: $row->id }}</a></td>
                                <td><span class="ao-tag">{{ $row->status }}</span></td>
                                <td class="ao-num">{{ number_format((float) $row->total, 2) }} {{ $row->currency_code }}</td>
                                <td>{{ $row->due_at?->format('j M Y') ?? '—' }}</td>
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
                                <td><span class="ao-tag">{{ $row->status }}</span></td>
                                <td>{{ $row->created_at?->format('j M Y') ?? '—' }}</td>
                                @break

                            @case('emails')
                                <td>{{ \Carbon\Carbon::parse($row->created_at)->format('d/m/Y H:i') }}</td>
                                <td>{{ $row->subject ?? $row->title ?? '—' }}</td>
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

        {{-- The reference's "see all" out to the full list, under the rows. --}}
        <p class="ao-catalogue-count">
            @switch($tab)
                @case('services') <a class="ao-link" href="{{ $urls['services'] }}">See all services</a> @break
                @case('invoices') <a class="ao-link" href="{{ $urls['invoices'] }}">See all invoices</a> @break
                @case('tickets') <a class="ao-link" href="{{ $urls['tickets'] }}">See all tickets</a> @break
            @endswitch
        </p>
    @endif
</x-filament::section>

@include('adminops::partials.records-pager', [
    'total' => $rowTotal(), 'page' => $page, 'perPage' => $perPage,
])
