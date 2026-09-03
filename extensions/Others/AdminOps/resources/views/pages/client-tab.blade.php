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
@endphp

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
                            <th class="ao-num">In</th><th class="ao-num">Out</th>
                            @break
                        @case('tickets')
                            <th>ID</th><th>Subject</th><th>Status</th><th>Opened</th>
                            @break
                        @case('emails')
                            <th>Subject</th><th>Sent</th>
                            @break
                        @case('log')
                            <th>Event</th><th>What</th><th>When</th>
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
                                <td class="ao-num ao-tx-out">{{ $row['out'] > 0 ? number_format($row['out'], 2) : '—' }}</td>
                                @break

                            @case('tickets')
                                <td><a class="ao-link" href="{{ $urls['ticket']($row->id) }}">#{{ $row->id }}</a></td>
                                <td>{{ $row->subject }}</td>
                                <td><span class="ao-tag">{{ $row->status }}</span></td>
                                <td>{{ $row->created_at?->format('j M Y') ?? '—' }}</td>
                                @break

                            @case('emails')
                                <td>{{ $row->subject ?? $row->title ?? '—' }}</td>
                                <td>{{ \Carbon\Carbon::parse($row->created_at)->format('j M Y H:i') }}</td>
                                @break

                            @case('log')
                                <td><span class="ao-tag">{{ $row->event }}</span></td>
                                <td>{{ class_basename($row->auditable_type) }} #{{ $row->auditable_id }}</td>
                                <td>{{ \Carbon\Carbon::parse($row->created_at)->format('j M Y H:i') }}</td>
                                @break
                        @endswitch
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Said rather than paginated: these tabs answer "what has been going on", and the
             screen that answers "show me all of it" is the core list this links to. --}}
        <p class="ao-catalogue-count">
            The most recent {{ $count }}.
            @switch($tab)
                @case('services') <a class="ao-link" href="{{ $urls['services'] }}">See all services</a> @break
                @case('invoices') <a class="ao-link" href="{{ $urls['invoices'] }}">See all invoices</a> @break
                @case('tickets') <a class="ao-link" href="{{ $urls['tickets'] }}">See all tickets</a> @break
            @endswitch
        </p>
    @endif
</x-filament::section>
