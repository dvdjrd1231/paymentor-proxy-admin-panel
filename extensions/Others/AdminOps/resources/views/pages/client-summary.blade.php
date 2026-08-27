{{--
    The reference's Client Profile: one customer, one screen, in tabs — Summary first, as it
    is there.

    Only the showing tab is rendered. The obvious build renders all of them and hides the
    rest with CSS, which is fine for six rows and ruinous for a customer with four hundred
    invoices: every visit would pay for every tab.

    Everything is read-only and links out to the core page that owns each record, so this
    stays a view and never a second place to edit from.
--}}
@php
    $statusTag = fn (string $status) => match ($status) {
        'active', 'paid' => 'ao-tag-success',
        'pending', 'open' => 'ao-tag-warning',
        'suspended', 'cancelled' => 'ao-tag-danger',
        'replied' => 'ao-tag-info',
        default => '',
    };
@endphp

<x-filament-panels::page>
    {{-- The tab bar. `wire:click` rather than links: the page is a Livewire component, so
         switching costs one round trip and one query instead of a full page load. --}}
    <nav class="ao-tabs" role="tablist">
        @foreach ($tabs as $key => $label)
            <button type="button"
                class="ao-tab {{ $tab === $key ? 'ao-tab-active' : '' }}"
                role="tab"
                aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                wire:click="$set('tab', '{{ $key }}')">
                {{ $label }}
            </button>
        @endforeach
    </nav>

    <div class="ao-panel" style="display:flex;flex-direction:column;gap:1.5rem;">

    @if ($tab === 'summary')
        <x-filament::section icon="heroicon-o-identification" heading="Customer">
            <div class="ao-summary-grid">
                <div>
                    <div class="ao-field-label">Customer ID</div>
                    <div class="ao-field-value">#{{ $user->id }}</div>
                </div>
                <div>
                    <div class="ao-field-label">Email</div>
                    <div class="ao-field-value">
                        <a href="mailto:{{ $user->email }}" class="ao-link">{{ $user->email }}</a>
                        @unless ($user->email_verified_at)
                            <span class="ao-tag ao-tag-warning" style="margin-left:.35rem;">Unverified</span>
                        @endunless
                    </div>
                </div>
                <div>
                    <div class="ao-field-label">Registered</div>
                    <div class="ao-field-value">{{ $user->created_at?->format('j M Y') ?? '—' }}</div>
                </div>
                <div>
                    <div class="ao-field-label">Account credit</div>
                    <div class="ao-field-value">
                        <a href="{{ $urls['credits'] }}" class="ao-link">{{ $this->formatTotals($credits) }}</a>
                    </div>
                </div>
                <div>
                    <div class="ao-field-label">Lifetime paid</div>
                    <div class="ao-field-value">{{ $this->formatTotals($lifetime) }}</div>
                </div>
                <div>
                    <div class="ao-field-label">Outstanding</div>
                    <div class="ao-field-value">{{ $this->formatTotals($outstanding) }}</div>
                </div>

                @foreach ($properties as $label => $value)
                    <div>
                        <div class="ao-field-label">{{ $label }}</div>
                        <div class="ao-field-value">{{ $value }}</div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section
            icon="heroicon-o-server-stack"
            :heading="'Services (' . $serviceCount . ')'"
        >
            <x-slot name="afterHeader">
                <a href="{{ $urls['services'] }}" class="ao-link">See all</a>
            </x-slot>

            @if ($services->isEmpty())
                <p class="ao-empty">No services.</p>
            @else
                <table class="ao-list">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Status</th>
                            <th>Renews</th>
                            <th class="ao-num">Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($services as $service)
                            <tr>
                                <td>
                                    <a href="{{ $urls['service']($service->id) }}" class="ao-link">
                                        {{ $service->product?->name ?? 'Service #' . $service->id }}
                                    </a>
                                    @if ($service->quantity > 1)
                                        <span style="opacity:.7;">&times;{{ $service->quantity }}</span>
                                    @endif
                                </td>
                                <td><span class="ao-tag {{ $statusTag($service->status) }}">{{ ucfirst($service->status) }}</span></td>
                                <td>{{ $service->expires_at?->format('j M Y') ?? '—' }}</td>
                                <td class="ao-num">{{ $this->formatMoney((float) $service->price, $service->currency_code) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-filament::section>

        <x-filament::section
            icon="heroicon-o-document-text"
            :heading="'Invoices (' . $invoiceCount . ')'"
        >
            <x-slot name="afterHeader">
                <a href="{{ $urls['invoices'] }}" class="ao-link">See all</a>
            </x-slot>

            @if ($invoices->isEmpty())
                <p class="ao-empty">No invoices.</p>
            @else
                <table class="ao-list">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Status</th>
                            <th>Due</th>
                            <th class="ao-num">Total</th>
                            <th class="ao-num">Remaining</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td>
                                    <a href="{{ $urls['invoice']($invoice->id) }}" class="ao-link">
                                        {{ $invoice->number ?: '#' . $invoice->id }}
                                    </a>
                                </td>
                                <td><span class="ao-tag {{ $statusTag($invoice->status) }}">{{ ucfirst($invoice->status) }}</span></td>
                                <td>{{ $invoice->due_at?->format('j M Y') ?? '—' }}</td>
                                <td class="ao-num">{{ $this->formatMoney((float) $invoice->total, $invoice->currency_code) }}</td>
                                <td class="ao-num">{{ $this->formatMoney((float) $invoice->remaining, $invoice->currency_code) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-filament::section>

        <x-filament::section
            icon="heroicon-o-lifebuoy"
            :heading="'Tickets (' . $ticketCount . ')'"
        >
            <x-slot name="afterHeader">
                <a href="{{ $urls['tickets'] }}" class="ao-link">See all</a>
            </x-slot>

            @if ($tickets->isEmpty())
                <p class="ao-empty">No tickets.</p>
            @else
                <table class="ao-list">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Last activity</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tickets as $ticket)
                            <tr>
                                <td>
                                    <a href="{{ $urls['ticket']($ticket->id) }}" class="ao-link">{{ $ticket->subject }}</a>
                                </td>
                                <td><span class="ao-tag {{ $statusTag($ticket->status) }}">{{ ucfirst($ticket->status) }}</span></td>
                                <td>{{ ucfirst($ticket->priority) }}</td>
                                <td>{{ $ticket->updated_at?->diffForHumans() ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-filament::section>

    @else
        {{-- Every other tab is one list of one thing. `adminops::pages.client-tab` renders
             whichever it is, so a new tab is a case there rather than another block here. --}}
        @include('adminops::pages.client-tab', ['tab' => $tab, 'rows' => $rows, 'urls' => $urls])
    @endif

    </div>
</x-filament-panels::page>
