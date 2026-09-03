{{--
    The reference's Transactions page: the 30-day net revenue chart with the three stat
    tiles beside it, Gateway Balances underneath, then the ledger — Amount In, Fees,
    Amount Out — payments and refunds interleaved so "paid, then refunded" reads as one
    story.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        <div class="ao-tx-tabs">
            <button type="button" class="ao-mu-tab {{ $filter ? 'ao-on' : '' }}" wire:click="toggleFilter">Search/Filter</button>
            <button type="button" class="ao-mu-tab {{ $adding ? 'ao-on' : '' }}" wire:click="toggleAdding">Add Transaction</button>
        </div>

        @if ($filter)
            {{-- The reference's Search/Filter panel, field for field: Show, Description,
                 Transaction ID on the left; Date Range, Amount, Payment Method on the
                 right; the Search/Filter button centred below. --}}
            <form class="ao-find ao-of" autocomplete="off" wire:submit.prevent="$refresh">
                <div class="ao-of-rows">
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-tx-show">Show</label>
                        <span><select @nofill id="ao-tx-show" class="ao-of-md" wire:model="show">
                            <option value="">All Activity</option>
                            <option value="in">Payments</option>
                            <option value="out">Refunds</option>
                            <option value="credit">Account Credit</option>
                        </select></span>
                        <label class="ao-of-label" for="ao-tx-dates">Date Range</label>
                        @include('adminops::partials.datepicker', [
                            'model' => 'dates', 'range' => true, 'id' => 'ao-tx-dates',
                            'placeholder' => 'MM/DD/YYYY - MM/DD/YYYY', 'class' => 'ao-of-lg',
                        ])
                    </div>
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-tx-q">Description</label>
                        <span><input @nofill id="ao-tx-q" class="ao-of-lg" type="text"
                            wire:model="q" placeholder="Words from the description or client"></span>
                        <label class="ao-of-label" for="ao-tx-amount">Amount</label>
                        <span><input @nofill id="ao-tx-amount" class="ao-of-sm" type="text" inputmode="decimal"
                            wire:model="amount" placeholder="0.00"></span>
                    </div>
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-tx-tid">Transaction ID</label>
                        <span><input @nofill id="ao-tx-tid" class="ao-of-lg" type="text"
                            wire:model="tid" placeholder="Gateway transaction ID"></span>
                        <label class="ao-of-label" for="ao-tx-method">Payment Method</label>
                        <span><select @nofill id="ao-tx-method" class="ao-of-md" wire:model="method">
                            <option value="">Any</option>
                            @foreach ($gateways as $gateway)
                                <option value="{{ $gateway->name }}">{{ $gateway->name }}</option>
                            @endforeach
                            <option value="Account credit">Account credit</option>
                        </select></span>
                    </div>
                </div>
                <button type="submit" class="ao-of-go">Search/Filter</button>
            </form>
        @endif

        @if ($adding)
            {{-- The reference's Add Transaction form. Money in goes through core's own
                 idempotent payment path against one invoice; ticking Credit tops up the
                 client's real credit balance; an Amount Out records an offline refund. --}}
            <form class="ao-find ao-of" autocomplete="off" wire:submit.prevent="addTransaction">
                <div class="ao-of-rows">
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-tx-date">Date</label>
                        @include('adminops::partials.datepicker', [
                            'model' => 'txDate', 'range' => false, 'id' => 'ao-tx-date',
                            'placeholder' => 'MM/DD/YYYY', 'class' => 'ao-of-md',
                        ])
                        <label class="ao-of-label" for="ao-tx-currency">Currency</label>
                        <span class="ao-of-inline">
                            <select @nofill id="ao-tx-currency" class="ao-of-sm" wire:model="txCurrency">
                                @foreach ($currencies as $code)
                                    <option value="{{ $code }}">{{ $code }}</option>
                                @endforeach
                            </select>
                            (Non Client Only)
                        </span>
                    </div>
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-tx-client">Related Client</label>
                        <span><select @nofill id="ao-tx-client" class="ao-of-lg" wire:model="txClient">
                            <option value="">Start Typing to Search Clients</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}">
                                    {{ trim($client->first_name . ' ' . $client->last_name) ?: $client->email }} - #{{ $client->id }}
                                </option>
                            @endforeach
                        </select></span>
                        <label class="ao-of-label" for="ao-tx-in">Amount In</label>
                        <span><input @nofill id="ao-tx-in" class="ao-of-sm" type="text" inputmode="decimal"
                            wire:model="txIn" placeholder="0.00"></span>
                    </div>
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-tx-desc">Description</label>
                        {{-- The reference's own field: real, blank, editable. Core's
                             transaction row has no column for it — kept on the side in
                             this extension's own table (issue: Add Transaction). --}}
                        <span><input @nofill id="ao-tx-desc" class="ao-of-lg" type="text"
                            wire:model="txDescription"></span>
                        <label class="ao-of-label" for="ao-tx-fees">Fees</label>
                        <span><input @nofill id="ao-tx-fees" class="ao-of-sm" type="text" inputmode="decimal"
                            wire:model="txFees" placeholder="0.00"></span>
                    </div>
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-tx-txid">Transaction ID</label>
                        <span><input @nofill id="ao-tx-txid" class="ao-of-lg" type="text"
                            wire:model="txId" placeholder="Optional — repeats are refused, not double-paid"></span>
                        <label class="ao-of-label" for="ao-tx-out">Amount Out</label>
                        <span><input @nofill id="ao-tx-out" class="ao-of-sm" type="text" inputmode="decimal"
                            wire:model="txOut" placeholder="0.00"></span>
                    </div>
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-tx-invoice">Invoice ID(s)</label>
                        <span class="ao-of-inline">
                            <input @nofill id="ao-tx-invoice" class="ao-of-md" type="text" inputmode="numeric"
                                wire:model="txInvoice" placeholder="e.g. 214">
                            Comma Separated
                        </span>
                        <label class="ao-of-label">Credit</label>
                        <label class="ao-of-check">
                            <input type="checkbox" wire:model="txCredit">
                            Add to Client's Credit Balance
                        </label>
                    </div>
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-tx-gateway">Payment Method</label>
                        {{-- Values are the gateway's extension key — the identifier core's
                             addPayment() resolves — with the display name as the label. --}}
                        <span><select @nofill id="ao-tx-gateway" class="ao-of-md" wire:model="txMethod">
                            <option value="">None</option>
                            @foreach ($gateways as $gateway)
                                <option value="{{ $gateway->extension }}">{{ $gateway->name }}</option>
                            @endforeach
                        </select></span>
                    </div>
                </div>
                <div class="ao-of-buttons">
                    <button type="submit" class="ao-find-go">Add Transaction</button>
                </div>
            </form>

            @if ($errors->any())
                <ul class="ao-anc-errors">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
        @endif

        {{-- The chart and the tiles, as the reference lays them out. --}}
        <div class="ao-tx-top">
            <div class="ao-tx-chart">
                @php
                    $days = $chart['days'];
                    // A rounded-up axis top, so the y labels read 0 / half / a whole number
                    // the way the reference's do — not the raw maximum.
                    $peak = max(1, collect($days)->max('net'));
                    $mag = 10 ** max(0, strlen((string) (int) $peak) - 1);
                    $max = (int) (ceil($peak / $mag) * $mag);
                    $w = 600; $h = 150; $pad = 8; $left = 40;
                    $step = ($w - $left - $pad) / max(1, count($days) - 1);
                    $points = collect($days)->values()->map(fn ($d, $i) => [
                        round($left + $i * $step, 1),
                        round($h - $pad - ($d['net'] / $max) * ($h - 2 * $pad), 1),
                    ]);
                    $line = $points->map(fn ($p) => $p[0] . ',' . $p[1])->implode(' ');
                    $area = $left . ',' . ($h - $pad) . ' ' . $line . ' ' . ($w - $pad) . ',' . ($h - $pad);
                @endphp
                <span class="ao-tx-axis">Net Revenue ({{ $chart['currency'] }})</span>
                <svg viewBox="0 0 {{ $w }} {{ $h + 30 }}" preserveAspectRatio="none" role="img"
                    aria-label="Net revenue per day, last 30 days">
                    @foreach ([0, 0.5, 1] as $tick)
                        @php $y = round($h - $pad - $tick * ($h - 2 * $pad), 1); @endphp
                        <line x1="{{ $left }}" y1="{{ $y }}" x2="{{ $w - $pad }}" y2="{{ $y }}"
                            stroke="#e5e5e5" stroke-width="1" />
                        <text x="{{ $left - 5 }}" y="{{ $y + 3 }}" font-size="9" fill="#6b6b6b"
                            text-anchor="end">{{ number_format($max * $tick) }}</text>
                    @endforeach
                    <polygon points="{{ $area }}" fill="rgba(122, 193, 67, 0.35)" />
                    <polyline points="{{ $line }}" fill="none" stroke="#7ac143" stroke-width="2" />
                    @foreach ($days as $i => $day)
                        @if ($i % 4 === 0)
                            <text x="{{ round($left + $i * $step, 1) }}" y="{{ $h + 22 }}"
                                font-size="9" fill="#6b6b6b" text-anchor="end"
                                transform="rotate(-40 {{ round($left + $i * $step, 1) }} {{ $h + 22 }})">{{ $day['date'] }}</text>
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
                            @php $dIn = $delta['in'] ?? 0; @endphp
                            <i class="{{ $dIn >= 0 ? 'ao-tx-up' : 'ao-tx-down' }}">
                                {{ $dIn >= 0 ? '↑' : '↓' }} {{ $dIn }}% from last 30 days
                            </i>
                        </span>
                    </div>
                    <div class="ao-tx-tile">
                        <span class="ao-tx-tile-ic ao-tx-ic-fees" aria-hidden="true">
                            <x-filament::icon icon="ri-money-dollar-circle-line" />
                        </span>
                        <span class="ao-tx-tile-body">
                            <span class="ao-tx-tile-label">Total Fees</span>
                            <b>${{ number_format($figures['fee'], 2) }} {{ $currency }}</b>
                            @php $dFee = $delta['fee'] ?? 0; @endphp
                            <i class="{{ $dFee <= 0 ? 'ao-tx-up' : 'ao-tx-down' }}">
                                {{ $dFee >= 0 ? '↑' : '↓' }} {{ abs($dFee) }}% from last 30 days
                            </i>
                        </span>
                    </div>
                    <div class="ao-tx-tile">
                        <span class="ao-tx-tile-ic ao-tx-ic-out" aria-hidden="true">
                            <x-filament::icon icon="ri-calculator-line" />
                        </span>
                        <span class="ao-tx-tile-body">
                            <span class="ao-tx-tile-label">Total Expenditure</span>
                            <b>${{ number_format($figures['out'], 2) }} {{ $currency }}</b>
                            <i class="ao-tx-up">↑ 0% from last 30 days</i>
                        </span>
                    </div>
                @empty
                    <p class="ao-catalogue-count">No transactions recorded yet.</p>
                @endforelse
            </div>
        </div>

        @if ($balances['tiles'] ?? [])
            <h4 class="ao-tx-heading">Gateway Balances</h4>
            <div class="ao-tx-bal-line">
                <button type="button" class="ao-tx-refresh" wire:click="refreshBalances">
                    &#8635; Refresh
                </button>
                <span>Last Updated: {{ \Carbon\Carbon::parse($balances['at'])->diffForHumans() }}</span>
            </div>
            <div class="ao-tx-balances">
                @foreach ($balances['tiles'] as $tile)
                    <div class="ao-tx-balance">
                        <span>{{ $tile['gateway'] }}</span>
                        {{-- Issue #11, from Leandro's boxed screenshot: the reference draws
                             Available green and Pending teal — colour by the label. --}}
                        <b class="{{ $tile['label'] === 'Pending' ? 'ao-tx-pending' : 'ao-tx-up' }}">{{ $tile['amount'] }}</b>
                        <i>{{ $tile['label'] }}</i>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="ao-mu-line">
            <span>{{ number_format(count($rows)) }} Records Found, Page 1 of 1</span>
            {{-- The reference's Jump to Page control, present even on a one-page list. --}}
            <label class="ao-mu-jump">
                Jump to Page:
                <select disabled><option>1</option></select>
            </label>
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
                        <td class="ao-mu-left ao-tx-desc">
                            {{ $row['description'] }}
                            @if ($row['trans'])
                                <br><span class="ao-tx-trans">Trans ID: {{ $row['trans'] }}</span>
                            @endif
                        </td>
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
