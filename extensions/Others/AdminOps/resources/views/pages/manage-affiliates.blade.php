{{--
    Affiliates, to issue #6's reference screenshots: the navy list, and the per-affiliate
    detail screen its blue icon opens — the framed two-column summary with the editable
    commission fields, Save/Cancel, and the Referrals / Commissions / Withdrawals tabs.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        @if ($current)
            @php
                $name = trim(($current->user->first_name ?? '') . ' ' . ($current->user->last_name ?? '')) ?: ($current->user->email ?? '—');
                $referred = $current->orders->filter(fn ($row) => $row->order !== null);
                $earned = \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageAffiliates::balance($current);
                $conversion = (int) $edit['visitors'] > 0 ? round($referred->count() / max(1, (int) $edit['visitors']) * 100) : 0;
            @endphp

            <div class="ao-tx-tabs">
                <button type="button" class="ao-mu-tab" wire:click="$set('affiliate', null)">&laquo; Back to List</button>
            </div>

            <div class="ao-af-frame">
                <div class="ao-af-cols">
                    <div>
                        <div class="ao-af-row"><span>Affiliate ID</span><b>{{ $current->id }}</b></div>
                        <div class="ao-af-row"><span>Client Name</span><b>{{ $name }}</b></div>
                        <div class="ao-af-row">
                            <span>Commission</span>
                            <span class="ao-af-field"><input type="text" inputmode="decimal" wire:model="edit.reward"> % of each referred order</span>
                        </div>
                        <div class="ao-af-row">
                            <span>Referral Discount</span>
                            <span class="ao-af-field"><input type="text" inputmode="decimal" wire:model="edit.discount"> % off for the referred client</span>
                        </div>
                        <div class="ao-af-row">
                            <span>Visitors Referred</span>
                            <span class="ao-af-field"><input type="number" min="0" wire:model="edit.visitors"></span>
                        </div>
                        <div class="ao-af-row"><span>Referral Code</span><b>{{ $current->code }}</b></div>
                    </div>
                    <div>
                        <div class="ao-af-row"><span>Signup Date</span><b>{{ $current->created_at?->format('m/d/Y') }}</b></div>
                        <div class="ao-af-row"><span>Total Earned</span><b>{{ $earned }}</b></div>
                        <div class="ao-af-row"><span>Orders Referred</span><b>{{ number_format($referred->count()) }}</b></div>
                        <div class="ao-af-row"><span>Conversion Rate</span><b>{{ $conversion }}%</b></div>
                        {{-- Fed by AdminOps's own payout ledger (issue #6) — recorded on
                             the Withdrawals History tab below. --}}
                        <div class="ao-af-row"><span>Withdrawn Amount</span><b>{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageAffiliates::withdrawn($current) }}</b></div>
                    </div>
                </div>

                @if ($errors->any())
                    <ul class="ao-anc-errors">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif

                <div class="ao-gs-actions">
                    <button type="button" class="ao-find-go" wire:click="saveAffiliate">Save Changes</button>
                    <button type="button" class="ao-gs-cancel" wire:click="openAffiliate({{ $current->id }})">Cancel Changes</button>
                </div>
            </div>

            <div class="ao-tx-tabs ao-af-tabs">
                @foreach ([
                    'referrals' => 'Referrals',
                    'signups' => 'Referred Signups',
                    'pending' => 'Pending Commissions (0)',
                    'commissions' => 'Commissions History',
                    'withdrawals' => 'Withdrawals History',
                ] as $key => $label)
                    <button type="button" class="ao-mu-tab {{ $dtab === $key ? 'ao-on' : '' }}" wire:click="$set('dtab', '{{ $key }}')">{{ $label }}</button>
                @endforeach
            </div>

            @if ($dtab === 'signups')
                <p class="ao-gs-empty">
                    {{ number_format($current->signups) }} {{ Str::plural('signup', $current->signups) }}
                    referred by this affiliate's code. The extension keeps the count, not the
                    individual accounts — the clients who went on to order appear under Referrals.
                </p>
            @elseif ($dtab === 'pending')
                <p class="ao-gs-empty" title="Earnings are computed from referred orders as they are paid; no separate pending ledger exists">
                    No pending commissions — earnings are computed from referred orders as they
                    are paid, and every earned amount is already in Commissions History.
                </p>
            @elseif ($dtab === 'withdrawals')
                @if (!\Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageAffiliates::withdrawalsReady())
                    <p class="ao-gs-empty">
                        The withdrawals ledger has not been migrated on this install yet.
                    </p>
                @else
                    @php
                        $ledger = \Paymenter\Extensions\Others\AdminOps\Models\AffiliateWithdrawal::query()
                            ->where('affiliate_id', $current->id)->latest()->get();
                    @endphp
                    <table class="ao-mu-grid">
                        <thead>
                            <tr><th>Date</th><th>Amount</th><th>Note</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($ledger as $row)
                                <tr>
                                    <td>{{ $row->created_at?->format('m/d/Y') }}</td>
                                    <td>${{ number_format((float) $row->amount, 2) }} {{ $row->currency_code }}</td>
                                    <td class="ao-mu-left">{{ $row->note ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="ao-mu-none">No Records Found</td></tr>
                            @endforelse
                        </tbody>
                    </table>

                    {{-- The payout itself happens outside the panel — bank, PIX, credit —
                         and is recorded here, which is how the reference does it too. --}}
                    <form class="ao-af-withdraw" wire:submit.prevent="recordWithdrawal">
                        <b>Record Withdrawal</b>
                        <input type="text" inputmode="decimal" placeholder="Amount" wire:model="withdraw.amount" aria-label="Amount">
                        <input type="text" maxlength="3" placeholder="USD" wire:model="withdraw.currency" aria-label="Currency">
                        <input type="text" placeholder="Note — how it was paid (optional)" wire:model="withdraw.note" aria-label="Note">
                        <button type="submit" class="ao-find-go">Record</button>
                    </form>
                @endif
            @else
                <table class="ao-mu-grid">
                    <thead>
                        <tr><th>Order</th><th>Date</th><th>Client</th><th>Product/Service</th><th>Earned</th></tr>
                    </thead>
                    <tbody>
                        @php
                            $rows = $dtab === 'commissions'
                                ? $referred->filter(fn ($row) => array_filter($row->earnings) !== [])
                                : $referred;
                        @endphp
                        @forelse ($rows as $row)
                            <tr>
                                <td>#{{ $row->order_id }}</td>
                                <td>{{ $row->order->created_at?->format('m/d/Y') }}</td>
                                <td class="ao-mu-left">
                                    {{ trim(($row->order->user->first_name ?? '') . ' ' . ($row->order->user->last_name ?? '')) ?: ($row->order->user->email ?? '—') }}
                                </td>
                                <td class="ao-mu-left">{{ $row->order->services->first()?->product?->name ?? '—' }}</td>
                                <td>
                                    @php $sums = array_filter($row->earnings); @endphp
                                    {{ $sums === [] ? '—' : implode(' · ', array_map(fn ($t, $c) => '$' . number_format((float) $t, 2) . ' ' . $c, $sums, array_keys($sums))) }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="ao-mu-none">No Records Found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        @else
        <div class="ao-tx-tabs">
            <button type="button" class="ao-mu-tab {{ $this->filter ? 'ao-on' : '' }}" wire:click="toggleFilter">
                Search/Filter
            </button>
        </div>

        @if ($this->filter)
            {{-- The reference's Search/Filter panel, field for field: Client Name and
                 Visitors Referred on the left, Balance and Withdrawn on the right, each
                 comparator a Greater/Less Than select with its number. --}}
            <form class="ao-find ao-of" autocomplete="off" wire:submit.prevent="search">
                <div class="ao-of-rows">
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-ma-q">Client Name</label>
                        <span><input @nofill id="ao-ma-q" class="ao-of-lg" type="text"
                            wire:model="q" placeholder="Client name or email"></span>
                        <label class="ao-of-label" for="ao-ma-bval">Balance</label>
                        <span class="ao-of-inline">
                            <select @nofill class="ao-of-sm" wire:model="bop" aria-label="Balance comparison">
                                <option value="gt">Greater Than</option>
                                <option value="lt">Less Than</option>
                            </select>
                            <input @nofill id="ao-ma-bval" class="ao-of-sm" type="text" inputmode="decimal"
                                wire:model="bval" placeholder="0.00">
                        </span>
                    </div>
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-ma-vval">Visitors Referred</label>
                        <span class="ao-of-inline">
                            <select @nofill class="ao-of-sm" wire:model="vop" aria-label="Visitors comparison">
                                <option value="gt">Greater Than</option>
                                <option value="lt">Less Than</option>
                            </select>
                            <input @nofill id="ao-ma-vval" class="ao-of-sm" type="text" inputmode="numeric"
                                wire:model="vval" placeholder="0">
                        </span>
                        <label class="ao-of-label" for="ao-ma-wval">Withdrawn</label>
                        <span class="ao-of-inline">
                            <select @nofill class="ao-of-sm" wire:model="wop" aria-label="Withdrawn comparison">
                                <option value="gt">Greater Than</option>
                                <option value="lt">Less Than</option>
                            </select>
                            <input @nofill id="ao-ma-wval" class="ao-of-sm" type="text" inputmode="decimal"
                                wire:model="wval" placeholder="0.00">
                        </span>
                    </div>
                </div>
                <button type="submit" class="ao-of-go">Search</button>
            </form>
        @endif

        <div class="ao-mu-line">
            <span>{{ number_format($affiliates->total()) }} Records Found, Page {{ $affiliates->currentPage() }} of {{ max(1, $affiliates->lastPage()) }}</span>
            <label class="ao-mu-jump">
                Jump to Page:
                <select wire:change="jump($event.target.value)">
                    @foreach (range(1, max(1, $affiliates->lastPage())) as $number)
                        <option value="{{ $number }}" @selected($number === $affiliates->currentPage())>{{ $number }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th class="ao-mu-check"><input type="checkbox" data-ao-check-all></th>
                    <th>ID</th>
                    <th>Signup Date</th>
                    <th>Client Name &#9650;</th>
                    <th>Visitors Referred</th>
                    <th>Signups</th>
                    <th>Balance</th>
                    <th>Withdrawn</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($affiliates as $affiliate)
                    @php
                        $edit = \Paymenter\Extensions\Others\Affiliates\Admin\Resources\AffiliateResource::getUrl('edit', ['record' => $affiliate->id]);
                        $name = trim(($affiliate->user->first_name ?? '') . ' ' . ($affiliate->user->last_name ?? '')) ?: ($affiliate->user->email ?? '—');
                    @endphp
                    <tr>
                        <td class="ao-mu-check"><input type="checkbox" data-ao-check value="{{ $affiliate->user?->email }}"></td>
                        <td>{{ $affiliate->id }}</td>
                        <td>{{ $affiliate->created_at?->format('m/d/Y') }}</td>
                        <td class="ao-mu-left">
                            <button type="button" class="ao-cp-link" wire:click="openAffiliate({{ $affiliate->id }})">{{ $name }}</button>
                        </td>
                        <td>{{ number_format($affiliate->visitors) }}</td>
                        <td>{{ number_format($affiliate->signups) }}</td>
                        <td>{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageAffiliates::balance($affiliate) }}</td>
                        <td>{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageAffiliates::withdrawn($affiliate) }}</td>
                        <td class="ao-mu-actions ao-mu-iconpair">
                            {{-- The reference's pair, in the panel's standard icons: the
                                 blue open-record glyph every other list uses (Invoices'
                                 own), then the red one for the raw record where disabling
                                 and deleting live. --}}
                            <button type="button" title="Open affiliate detail" wire:click="openAffiliate({{ $affiliate->id }})">
                                <x-filament::icon icon="ri-file-list-2-line" class="ao-mu-cell-icon" />
                            </button>
                            <a href="{{ $edit }}" title="Manage affiliate record">
                                <x-filament::icon icon="ri-indeterminate-circle-fill" class="ao-mu-cell-icon ao-mu-icon-red" />
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        <nav class="ao-mu-pages">
            <button type="button" wire:click="jump({{ $affiliates->currentPage() - 1 }})"
                @disabled($affiliates->onFirstPage())>&laquo; Previous Page</button>
            <span class="ao-mu-page-now">{{ $affiliates->currentPage() }}</span>
            <button type="button" wire:click="jump({{ $affiliates->currentPage() + 1 }})"
                @disabled(!$affiliates->hasMorePages())>Next Page &raquo;</button>
        </nav>
        @endif
    </div>
</x-filament-panels::page>
