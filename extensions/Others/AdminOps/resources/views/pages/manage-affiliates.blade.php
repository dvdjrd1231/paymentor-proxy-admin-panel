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
                            <span>Commission Type</span>
                            <span class="ao-af-radios">
                                <label><input type="radio" value="default" wire:model.live="edit.commissionType"> Use Default</label>
                                <label><input type="radio" value="percentage" wire:model.live="edit.commissionType"> Percentage</label>
                                <label class="ao-ano-off" title="This store only computes percentage-based commissions — nothing reads a fixed-amount rate">
                                    <input type="radio" disabled> Fixed Amount
                                </label>
                            </span>
                        </div>
                        <div class="ao-af-row">
                            <span>Commission Amount</span>
                            <span class="ao-af-field">
                                <input type="text" inputmode="decimal" wire:model="edit.reward"
                                    @disabled($edit['commissionType'] !== 'percentage')
                                    placeholder="{{ $edit['commissionType'] === 'default' ? 'store default' : '' }}"> %
                                <label class="ao-af-onetime">
                                    <input type="checkbox" wire:model="edit.oneTimeOnly"> Pay One Time Only
                                </label>
                            </span>
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
                        <div class="ao-af-row" title="Paymenter credits a referral the moment its invoice is paid — there is no pending state to hold it in">
                            <span>Pending Commissions</span><b>$0.00 USD</b>
                        </div>
                        <div class="ao-af-row">
                            <span>Available to Withdraw Balance</span>
                            <b>{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageAffiliates::availableToWithdraw($current) }}</b>
                        </div>
                        {{-- Fed by AdminOps's own payout ledger (issue #6) — recorded on
                             the Withdrawals History tab below. --}}
                        <div class="ao-af-row"><span>Withdrawn Amount</span><b>{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageAffiliates::withdrawn($current) }}</b></div>
                        <div class="ao-af-row"><span>Conversion Rate</span><b>{{ $conversion }}%</b></div>
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
                {{-- The reference's Referred Signups: the records strip, its exact columns,
                     and the pager. Rows are the clients this affiliate's referrals brought
                     in — one per referred order, which is the record this store keeps. --}}
                <div class="ao-af-records">
                    <span>{{ number_format($referred->count()) }} Records Found, Page 1 of 1</span>
                    <span>Jump to Page: <select disabled title="All rows fit one page"><option>1</option></select></span>
                </div>
                <table class="ao-mu-grid">
                    <thead>
                        <tr><th>ID</th><th>Signup Date</th><th>Client Name</th><th>Product/Service</th><th>Commission</th><th>Last Paid</th><th>Product Status</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($referred as $row)
                            @php
                                $sums = array_filter($row->earnings);
                                $lastPaid = $row->order->invoices->where('status', 'paid')->sortByDesc('id')->first();
                            @endphp
                            <tr>
                                <td>{{ $row->order_id }}</td>
                                <td>{{ $row->order->created_at?->format('m/d/Y') }}</td>
                                <td class="ao-mu-left">
                                    {{ trim(($row->order->user->first_name ?? '') . ' ' . ($row->order->user->last_name ?? '')) ?: ($row->order->user->email ?? '—') }}
                                </td>
                                <td class="ao-mu-left">{{ $row->order->services->first()?->product?->name ?? '—' }}</td>
                                <td>{{ $sums === [] ? '—' : implode(' · ', array_map(fn ($t, $c) => '$' . number_format((float) $t, 2) . ' ' . $c, $sums, array_keys($sums))) }}</td>
                                <td>{{ $lastPaid?->updated_at?->format('m/d/Y') ?? '—' }}</td>
                                <td>{{ ucfirst((string) ($row->order->services->first()?->status ?? '—')) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="ao-mu-none ao-mu-left">No Records Found</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="ao-mu-pager">
                    <button type="button" disabled>&laquo; Previous Page</button>
                    <button type="button" disabled>Next Page &raquo;</button>
                </div>
            @elseif ($dtab === 'pending')
                {{-- The reference's columns; honestly empty — this store credits a
                     commission the moment its invoice is paid, so nothing waits here. --}}
                <table class="ao-mu-grid">
                    <thead>
                        <tr><th>Referral ID</th><th>Client Name</th><th>Product/Service</th><th>Product Status</th><th>Invoice #</th><th>Amount</th><th>Clearing Date</th></tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="7" class="ao-mu-none ao-mu-left"
                            title="Earnings are credited from referred orders as they are paid; no separate pending ledger exists">No Records Found</td></tr>
                    </tbody>
                </table>
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
                    {{-- The reference's two columns exactly; the note travels on the
                         Amount cell's title so it is still one hover away. --}}
                    <table class="ao-mu-grid">
                        <thead>
                            <tr><th>Date</th><th>Amount</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($ledger as $row)
                                <tr>
                                    <td>{{ $row->created_at?->format('m/d/Y') }}</td>
                                    <td title="{{ $row->note }}">${{ number_format((float) $row->amount, 2) }} {{ $row->currency_code }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="ao-mu-none ao-mu-left">No Records Found</td></tr>
                            @endforelse
                        </tbody>
                    </table>

                    {{-- The reference's Make Withdrawal Payout, field for field. Payout
                         Type's client-credit option is real money on their balance. --}}
                    <h4 class="ao-ano-heading">Make Withdrawal Payout</h4>
                    <form class="ao-anc-card" wire:submit.prevent="recordWithdrawal">
                        <label class="ao-anc-row">
                            <span>Amount:</span>
                            <span class="ao-anc-field">
                                <input type="text" class="ao-w-25" inputmode="decimal" placeholder="0.00" wire:model="withdraw.amount">
                                <input type="text" class="ao-af-cur" maxlength="3" wire:model="withdraw.currency" aria-label="Currency">
                            </span>
                        </label>
                        <label class="ao-anc-row">
                            <span>Payout Type:</span>
                            <select class="ao-w-40" wire:model="withdraw.type">
                                <option value="external">Record External Payout (bank, PIX…)</option>
                                <option value="credit">Add to Client's Credit Balance</option>
                            </select>
                        </label>
                        <label class="ao-anc-row">
                            <span>Transaction ID:</span>
                            <span class="ao-anc-field">
                                <input type="text" class="ao-w-40" wire:model="withdraw.txid">
                                <i>(Optional — the bank or gateway reference)</i>
                            </span>
                        </label>
                        <label class="ao-anc-row">
                            <span>Note:</span>
                            <input type="text" class="ao-w-40" wire:model="withdraw.note" placeholder="Optional">
                        </label>
                        <div class="ao-pr-center"><button type="submit" class="ao-find-go">Submit</button></div>
                    </form>
                @endif
            @elseif ($dtab === 'commissions')
                @php
                    $earnedRows = $referred->filter(fn ($row) => array_filter($row->earnings) !== []);
                    $manualRows = \Paymenter\Extensions\Others\AdminOps\Models\AffiliateManualCommission::query()
                        ->where('affiliate_id', $current->id)->latest()->get();
                @endphp
                {{-- The reference's columns: Date, Referral ID, Client Name,
                     Product/Service, Product Status, Description, Invoice #, Amount. --}}
                <table class="ao-mu-grid">
                    <thead>
                        <tr><th>Date</th><th>Referral ID</th><th>Client Name</th><th>Product/Service</th><th>Product Status</th><th>Description</th><th>Invoice #</th><th>Amount</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($earnedRows as $row)
                            @php $paidInvoice = $row->order->invoices->where('status', 'paid')->sortByDesc('id')->first(); @endphp
                            <tr>
                                <td>{{ $row->order->created_at?->format('m/d/Y') }}</td>
                                <td>{{ $row->order_id }}</td>
                                <td class="ao-mu-left">
                                    {{ trim(($row->order->user->first_name ?? '') . ' ' . ($row->order->user->last_name ?? '')) ?: ($row->order->user->email ?? '—') }}
                                </td>
                                <td class="ao-mu-left">{{ $row->order->services->first()?->product?->name ?? '—' }}</td>
                                <td>{{ ucfirst((string) ($row->order->services->first()?->status ?? '—')) }}</td>
                                <td class="ao-mu-left">Commission on referred order</td>
                                <td>{{ $paidInvoice?->number ?? $paidInvoice?->id ?? '—' }}</td>
                                <td>
                                    @php $sums = array_filter($row->earnings); @endphp
                                    {{ implode(' · ', array_map(fn ($t, $c) => '$' . number_format((float) $t, 2) . ' ' . $c, $sums, array_keys($sums))) }}
                                </td>
                            </tr>
                        @empty
                        @endforelse
                        @foreach ($manualRows as $row)
                            <tr>
                                <td>{{ $row->created_at?->format('m/d/Y') }}</td>
                                <td>{{ $row->order_id ?: '—' }}</td>
                                <td class="ao-mu-left">Manual entry</td>
                                <td class="ao-mu-left">—</td>
                                <td>—</td>
                                <td class="ao-mu-left">{{ $row->description ?: '—' }}</td>
                                <td>—</td>
                                <td>${{ number_format((float) $row->amount, 2) }} {{ $row->currency_code }}</td>
                            </tr>
                        @endforeach
                        @if ($earnedRows->isEmpty() && $manualRows->isEmpty())
                            <tr><td colspan="8" class="ao-mu-none ao-mu-left">No Records Found</td></tr>
                        @endif
                    </tbody>
                </table>

                {{-- The reference's Add Manual Commission Entry, its own field layout —
                     a real ledger row, counted into Available to Withdraw Balance. --}}
                <h4 class="ao-ano-heading">Add Manual Commission Entry</h4>
                <form class="ao-anc-card" wire:submit.prevent="addManualCommission">
                    <div class="ao-anc-row">
                        <span>Date:</span>
                        <span class="ao-eo-fact">{{ now()->format('m/d/Y') }}</span>
                    </div>
                    <label class="ao-anc-row">
                        <span>Related Referral:</span>
                        <select class="ao-w-40" wire:model="manual.orderId">
                            <option value="">None</option>
                            @foreach ($referred as $row)
                                <option value="{{ $row->order_id }}">
                                    #{{ $row->order_id }} — {{ trim(($row->order->user->first_name ?? '') . ' ' . ($row->order->user->last_name ?? '')) ?: ($row->order->user->email ?? '—') }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label class="ao-anc-row">
                        <span>Description:</span>
                        <span class="ao-anc-field">
                            <input type="text" class="ao-w-40" wire:model="manual.description">
                            <i>(Optional)</i>
                        </span>
                    </label>
                    <label class="ao-anc-row">
                        <span>Amount:</span>
                        <input type="text" class="ao-w-25" inputmode="decimal" placeholder="0.00" wire:model="manual.amount">
                    </label>
                    @error('manual.amount') <p class="ao-anc-errors">{{ $message }}</p> @enderror
                    <div class="ao-pr-center"><button type="submit" class="ao-find-go">Submit</button></div>
                </form>
            @else
                {{-- The reference's Referrals tab: the Time Period strip and the
                     Referrer URL / Number of Hits table. This store counts visitors in
                     total (the Visitors Referred field above) and keeps no per-URL,
                     per-day hit log, so the table is honestly empty. --}}
                <div class="ao-af-records ao-af-period">
                    <span></span>
                    <span>Time Period
                        @foreach (['30', '60', '90', '180'] as $days)
                            <button type="button" class="ao-mu-tab {{ $period === $days ? 'ao-on' : '' }}"
                                wire:click="$set('period', '{{ $days }}')">{{ $days }} Days</button>
                        @endforeach
                    </span>
                </div>
                <table class="ao-mu-grid">
                    <thead>
                        <tr><th>Referrer URL</th><th>Number of Hits</th></tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="2" class="ao-mu-none ao-mu-left"
                            title="This store records total visitors, not per-URL hits — the count lives in Visitors Referred above">No Records Found</td></tr>
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
                        // No longer a raw AffiliateResource::getUrl('edit') link here — the
                        // row's edit icon opens this page's own detail screen instead.
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
                            {{-- The panel's standard pair: blue edit-box opens the reference's
                                 own Commission Type/Amount/tabs screen (openAffiliate — the
                                 same detail view {@see \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageAffiliates}
                                 already builds), red circle-minus deletes for real. --}}
                            <button type="button" title="Edit affiliate" wire:click="openAffiliate({{ $affiliate->id }})">
                                <x-filament::icon icon="ri-edit-box-line" class="ao-mu-cell-icon" />
                            </button>
                            <button type="button" title="Delete affiliate" wire:click="confirmDelete({{ $affiliate->id }})">
                                <x-filament::icon icon="ri-indeterminate-circle-fill" class="ao-mu-cell-icon ao-mu-icon-red" />
                            </button>
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

        @if ($confirmingDelete)
            <div class="ao-mud-overlay" wire:click.self="$set('confirmingDelete', null)">
                <div class="ao-mud ao-mud-sm" role="alertdialog" aria-modal="true">
                    <div class="ao-mud-head">
                        Are you sure?
                        <button type="button" wire:click="$set('confirmingDelete', null)" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text">
                        <p>Are you sure you wish to delete this affiliate?</p>
                        <p>An affiliate with referred orders is kept — deleting it would delete their earnings history too.</p>
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="$set('confirmingDelete', null)">Cancel</button>
                            <button type="button" class="ao-mud-delete" wire:click="runDelete">Delete</button>
                        </span>
                    </div>
                </div>
            </div>
        @endif
        @endif
    </div>
</x-filament-panels::page>
