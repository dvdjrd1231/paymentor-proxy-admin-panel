{{--
    The reference's Transactions page: three tiles across the top, then the ledger.

    Payments and refunds interleave in one list rather than sitting in two tables, because
    "paid, then half of it refunded a week later" is only obvious when the two lines are next
    to each other — which is how the reference reads too.
--}}
<x-filament-panels::page>
    @forelse ($totals as $currency => $figures)
        <div class="ao-auto-head">
            <div class="ao-auto-head-tile ao-auto-ok">
                <span class="ao-auto-head-figure">
                    {{ number_format($figures['in'], 2) }} {{ $currency }}
                </span>
                <span class="ao-auto-head-label">Total Income</span>
            </div>

            <div class="ao-auto-head-tile ao-auto-neutral">
                <span class="ao-auto-head-figure">
                    {{ number_format($figures['fee'], 2) }} {{ $currency }}
                </span>
                <span class="ao-auto-head-label">
                    Total Fees
                    {{-- Said out loud, because a zero here is not the same as "no fees were
                         charged" — it is "nothing has ever written this column". --}}
                    @unless ($feesRecorded) · not yet reported by any gateway @endunless
                </span>
            </div>

            <div class="ao-auto-head-tile ao-auto-bad">
                <span class="ao-auto-head-figure">
                    {{ number_format($figures['out'], 2) }} {{ $currency }}
                </span>
                <span class="ao-auto-head-label">Total Expenditure — refunds</span>
            </div>
        </div>
    @empty
        <p class="ao-catalogue-count">No transactions recorded yet.</p>
    @endforelse

    @if (! $feesRecorded)
        <div class="ao-auto-problems">
            <div class="ao-auto-problem">
                <strong>Fees are not being recorded</strong>
                <p>
                    <code>invoice_transactions.fee</code> has existed since the table was created and
                    nothing has ever written to it — none of the four gateways here reports its cut back
                    to Paymenter. Until one does, Total Income is gross receipts rather than revenue, and
                    the Fees column stays empty. It is shown rather than hidden so the difference is
                    visible instead of assumed.
                </p>
            </div>
        </div>
    @endif

    <div class="ao-panel">
        <table class="ao-cat-table">
            <thead>
                <tr>
                    <th>Client Name</th>
                    <th>Date</th>
                    <th>Payment Method</th>
                    <th>Description</th>
                    <th class="ao-col-stock">Amount In</th>
                    <th class="ao-col-stock">Fees</th>
                    <th class="ao-col-stock">Amount Out</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row['customer'] }}</td>
                        <td>{{ $row['at']?->toDayDateTimeString() ?? '—' }}</td>
                        <td>{{ $row['method'] }}</td>
                        <td>{{ $row['description'] }}</td>
                        <td class="ao-col-stock">
                            {{ $row['in'] > 0 ? number_format($row['in'], 2) . ' ' . $row['currency'] : '—' }}
                        </td>
                        <td class="ao-col-stock">
                            {{ $row['fee'] > 0 ? number_format($row['fee'], 2) : '—' }}
                        </td>
                        <td class="ao-col-stock ao-tx-out">
                            {{ $row['out'] > 0 ? number_format($row['out'], 2) . ' ' . $row['currency'] : '—' }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">No records found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="ao-catalogue-count">
        The most recent 200 movements. Totals above are for everything, not just this page, and are
        kept per currency — Paymenter stores no exchange rate, so one figure spanning two currencies
        would be neither of them.
    </p>
</x-filament-panels::page>
