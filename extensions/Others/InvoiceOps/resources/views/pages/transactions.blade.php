{{--
    The reference's Transactions page: the 30-day net revenue chart with the three stat
    tiles beside it, Gateway Balances underneath, then the ledger — Amount In, Fees,
    Amount Out — payments and refunds interleaved so "paid, then refunded" reads as one
    story.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        <div class="ao-tx-tabs">
            <span class="ao-mu-tab ao-tx-tab-dead" title="Use the filters on the invoice pages">Search/Filter</span>
            @php
                $addUrl = null;
                try {
                    $addUrl = \App\Admin\Resources\InvoiceTransactions\InvoiceTransactionResource::getUrl('index');
                } catch (\Throwable $e) {
                }
            @endphp
            @if ($addUrl)
                <a class="ao-mu-tab" href="{{ $addUrl }}">Add Transaction</a>
            @endif
        </div>

        {{-- The chart and the tiles, as the reference lays them out. --}}
        <div class="ao-tx-top">
            <div class="ao-tx-chart">
                @php
                    $days = $chart['days'];
                    $max = max(1, collect($days)->max('net'));
                    $w = 600; $h = 150; $pad = 8;
                    $step = ($w - 2 * $pad) / max(1, count($days) - 1);
                    $points = collect($days)->values()->map(fn ($d, $i) => [
                        round($pad + $i * $step, 1),
                        round($h - $pad - ($d['net'] / $max) * ($h - 2 * $pad), 1),
                    ]);
                    $line = $points->map(fn ($p) => $p[0] . ',' . $p[1])->implode(' ');
                    $area = $pad . ',' . ($h - $pad) . ' ' . $line . ' ' . ($w - $pad) . ',' . ($h - $pad);
                @endphp
                <span class="ao-tx-axis">Net Revenue ({{ $chart['currency'] }})</span>
                <svg viewBox="0 0 {{ $w }} {{ $h + 30 }}" preserveAspectRatio="none" role="img"
                    aria-label="Net revenue per day, last 30 days">
                    <polygon points="{{ $area }}" fill="rgba(122, 193, 67, 0.35)" />
                    <polyline points="{{ $line }}" fill="none" stroke="#7ac143" stroke-width="2" />
                    @foreach ($days as $i => $day)
                        @if ($i % 4 === 0)
                            <text x="{{ round($pad + $i * $step, 1) }}" y="{{ $h + 22 }}"
                                font-size="9" fill="#6b6b6b" text-anchor="end"
                                transform="rotate(-40 {{ round($pad + $i * $step, 1) }} {{ $h + 22 }})">{{ $day['date'] }}</text>
                        @endif
                    @endforeach
                </svg>
            </div>

            <div class="ao-tx-tiles">
                @forelse ($totals as $currency => $figures)
                    @php $delta = $deltas[$currency] ?? ['in' => null, 'fee' => null, 'out' => null]; @endphp
                    <div class="ao-tx-tile">
                        <span class="ao-tx-tile-ic ao-tx-ic-income" aria-hidden="true">
                            <x-filament::icon icon="ri-database-2-line" />
                        </span>
                        <span class="ao-tx-tile-body">
                            <span class="ao-tx-tile-label">Total Income</span>
                            <b>${{ number_format($figures['in'], 2) }} {{ $currency }}</b>
                            @if ($delta['in'] !== null)
                                <i class="{{ $delta['in'] >= 0 ? 'ao-tx-up' : 'ao-tx-down' }}">
                                    {{ $delta['in'] >= 0 ? '↑' : '↓' }} {{ $delta['in'] }}% from last 30 days
                                </i>
                            @endif
                        </span>
                    </div>
                    <div class="ao-tx-tile">
                        <span class="ao-tx-tile-ic ao-tx-ic-fees" aria-hidden="true">
                            <x-filament::icon icon="ri-money-dollar-circle-line" />
                        </span>
                        <span class="ao-tx-tile-body">
                            <span class="ao-tx-tile-label">Total Fees</span>
                            <b>${{ number_format($figures['fee'], 2) }} {{ $currency }}</b>
                            @if ($delta['fee'] !== null)
                                <i class="{{ $delta['fee'] <= 0 ? 'ao-tx-up' : 'ao-tx-down' }}">
                                    {{ $delta['fee'] >= 0 ? '↑' : '↓' }} {{ abs($delta['fee']) }}% from last 30 days
                                </i>
                            @elseif (! $feesRecorded)
                                <i class="ao-tx-note">not yet reported by any gateway</i>
                            @endif
                        </span>
                    </div>
                    <div class="ao-tx-tile">
                        <span class="ao-tx-tile-ic ao-tx-ic-out" aria-hidden="true">
                            <x-filament::icon icon="ri-calculator-line" />
                        </span>
                        <span class="ao-tx-tile-body">
                            <span class="ao-tx-tile-label">Total Expenditure</span>
                            <b>${{ number_format($figures['out'], 2) }} {{ $currency }}</b>
                            <i class="ao-tx-note">refunds</i>
                        </span>
                    </div>
                @empty
                    <p class="ao-catalogue-count">No transactions recorded yet.</p>
                @endforelse
            </div>
        </div>

        @if ($balances)
            <h4 class="ao-tx-heading">Gateway Balances</h4>
            <div class="ao-tx-balances">
                @foreach ($balances as $tile)
                    <div class="ao-tx-balance">
                        <span>{{ $tile['gateway'] }}</span>
                        <b class="{{ $tile['label'] === 'Available' ? 'ao-tx-up' : 'ao-tx-note' }}">{{ $tile['amount'] }}</b>
                        <i>{{ $tile['label'] }}</i>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="ao-mu-line">
            <span>{{ number_format(count($rows)) }} Records Found, Page 1 of 1</span>
        </div>

        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th>Client Name</th>
                    <th>Date</th>
                    <th>Payment Method</th>
                    <th>Description</th>
                    <th>Amount In</th>
                    <th>Fees</th>
                    <th>Amount Out</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="ao-mu-left">{{ $row['customer'] }}</td>
                        <td>{{ $row['at']?->format('m/d/Y H:i') ?? '—' }}</td>
                        <td>{{ $row['method'] }}</td>
                        <td class="ao-mu-left ao-tx-desc">{{ $row['description'] }}</td>
                        <td>{{ $row['in'] > 0 ? '$' . number_format($row['in'], 2) . ' ' . $row['currency'] : '$0.00 ' . $row['currency'] }}</td>
                        <td>{{ '$' . number_format($row['fee'], 2) . ' ' . $row['currency'] }}</td>
                        <td class="{{ $row['out'] > 0 ? 'ao-tx-out' : '' }}">
                            {{ '$' . number_format($row['out'], 2) . ' ' . $row['currency'] }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        <p class="ao-catalogue-count">
            The most recent 200 movements. Totals above are for everything, not just this page, and
            are kept per currency — Paymenter stores no exchange rate, so one figure spanning two
            currencies would be neither of them.
            @unless ($feesRecorded)
                Fees read zero because no connected gateway reports its cut back yet — gross
                receipts, not revenue, until one does.
            @endunless
        </p>
    </div>
</x-filament-panels::page>
