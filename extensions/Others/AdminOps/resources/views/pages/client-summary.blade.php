{{-- The reference's Client Profile: one customer, one screen, in tabs — Summary first, as it
     is there. --}}
@php
    $statusTag = fn (string $status) => match ($status) {
        'active', 'paid' => 'ao-tag-success',
        'pending', 'open' => 'ao-tag-warning',
        'suspended', 'cancelled' => 'ao-tag-danger',
        'replied' => 'ao-tag-info',
        default => '',
    };
@endphp

{{-- logFilterOpen lives here rather than on the Log tab itself: the button that toggles it
     sits above the panel it opens, so both need the same Alpine scope. --}}
<x-filament-panels::page x-data="{ logFilterOpen: false }">
    {{-- The reference's client switcher sits above the tab bar, on every tab: pick any
         client and land on their profile. Named its way: "name (company) - #id". --}}
    <div class="ao-cs-switch">
        <select onchange="if (this.value) window.location = '{{ url('/admin/client-summary') }}/' + this.value;">
            @foreach ($clientsList as $client)
                @php $switchCompany = $client->properties->first()?->value; @endphp
                <option value="{{ $client->id }}" @selected($client->id === $user->id)>
                    {{ trim($client->first_name . ' ' . $client->last_name) ?: $client->email }}{{ $switchCompany ? ' (' . $switchCompany . ')' : '' }} - #{{ $client->id }}
                </option>
            @endforeach
        </select>
    </div>

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
        {{-- The reference's Summary: four columns of panels, then the banded tables. --}}
        @php
            $flag = fn (string $key, bool $invert = false) => (($user->properties->firstWhere('key', $key)?->value ?? '') === '1') !== $invert;
        @endphp
        {{-- The reference's banner after an add-funds invoice is raised, with the invoice
             it made as a link. --}}
        @if ($fundsInvoice)
            <div class="ao-cs-banner">
                <x-filament::icon icon="ri-information-line" class="ao-cs-banner-ic" />
                <div>
                    <b>Create Add Funds Invoice</b>
                    <div>The add funds invoice was created successfully! -
                        <a class="ao-link" href="{{ \App\Admin\Resources\InvoiceResource::getUrl('edit', ['record' => $fundsInvoice]) }}">Invoice #{{ $fundsInvoice }}</a>
                    </div>
                </div>
            </div>
        @endif

        <div class="ao-cs-head">
            <h2>#{{ $user->id }} - {{ trim($user->first_name . ' ' . $user->last_name) ?: $user->email }}</h2>
            {{-- The reference's flags strip — read from the client's real setting_* rows. --}}
            <div class="ao-cs-flags">
                <span>Exempt from Tax: <b class="{{ $flag('setting_tax_exempt') ? 'ao-cs-yes' : 'ao-cs-no' }}">{{ $flag('setting_tax_exempt') ? 'Yes' : 'No' }}</b></span>
                <span>Auto CC Processing: <b class="{{ $flag('setting_disable_cc', true) ? 'ao-cs-yes' : 'ao-cs-no' }}">{{ $flag('setting_disable_cc', true) ? 'Yes' : 'No' }}</b></span>
                <span>Send Overdue Reminders: <b class="{{ $flag('setting_overdue_notices') ? 'ao-cs-yes' : 'ao-cs-no' }}">{{ $flag('setting_overdue_notices') ? 'Yes' : 'No' }}</b></span>
                <span>Apply Late Fees: <b class="{{ $flag('setting_late_fees') ? 'ao-cs-yes' : 'ao-cs-no' }}">{{ $flag('setting_late_fees') ? 'Yes' : 'No' }}</b></span>
            </div>
        </div>

        <div class="ao-cs-grid">
            <div class="ao-cs-col">
                <div class="ao-cp">
                    <h3>Clients Information</h3>
                    <div class="ao-cp-body">
                        <table class="ao-cp-kv">
                            <tr><td>First Name</td><td>{{ $user->first_name ?? '—' }}</td></tr>
                            <tr><td>Last Name</td><td>{{ $user->last_name ?? '—' }}</td></tr>
                            <tr><td>Email Address</td><td>{{ $user->email }}</td></tr>
                            @foreach ($properties as $label => $value)
                                <tr><td>{{ $label }}</td><td>{{ $value }}</td></tr>
                            @endforeach
                        </table>
                        {{-- The reference's Login as Owner — same impersonation the header
                             action runs, from the panel where WHMCS puts it. --}}
                        <button type="button" class="ao-cp-link" wire:click="mountAction('impersonate')">
                            <x-filament::icon icon="ri-login-circle-line" class="ao-cp-ic" /> Login as Owner
                        </button>
                    </div>
                </div>

                <div class="ao-cp">
                    <h3>Contacts</h3>
                    <div class="ao-cp-body">
                        {{-- This panel used to say contacts were not part of Paymenter and
                             drew a dead Add Contact. They are now — ClientTools backs the
                             Contacts tab — so it shows the real ones and goes there. --}}
                        @forelse ($summaryContacts as $contact)
                            <div class="ao-cp-kv-line">
                                <a class="ao-link" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $user->id, 'tab' => 'contacts', 'contact' => $contact->id]) }}">{{ $contact->name }}</a>
                                <span class="ao-cpg-muted">{{ $contact->email }}</span>
                            </div>
                        @empty
                            <div class="ao-cp-empty">No additional contacts setup</div>
                        @endforelse
                        <a class="ao-cp-link" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $user->id, 'tab' => 'contacts']) }}">
                            <x-filament::icon icon="ri-user-add-line" class="ao-cp-ic" /> Add Contact
                        </a>
                    </div>
                </div>

                <div class="ao-cp">
                    <h3>Pay Methods</h3>
                    <div class="ao-cp-body">
                        <div class="ao-cp-empty">No Pay Methods</div>
                        {{-- No Add Credit Card: Paymenter stores no card details and has no
                             vault to put them in, and a link that pretends otherwise is
                             worse than no link. Cards are entered at the gateway, which is
                             where they stay. --}}
                    </div>
                </div>
            </div>

            <div class="ao-cs-col">
                <div class="ao-cp">
                    <h3>Invoices/Billing</h3>
                    <div class="ao-cp-body">
                        <table class="ao-cp-kv">
                            @foreach (['paid' => 'Paid', 'draft' => 'Draft', 'unpaid' => 'Unpaid/Due', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded'] as $key => $label)
                                <tr><td>{{ $label }}</td><td>{{ $invoiceStats[$key]['count'] }} (${{ number_format($invoiceStats[$key]['total'], 2) }} {{ $invoiceStats[$key]['code'] }})</td></tr>
                            @endforeach
                            {{-- No collections process exists, so zero is the truth — the
                                 row is here because the reference has it. --}}
                            <tr><td>Collections</td><td>0 ($0.00 {{ $invoiceStats['paid']['code'] }})</td></tr>
                            <tr class="ao-cp-kv-band"><td colspan="2">Income</td></tr>
                            <tr><td>Gross Revenue</td><td>{{ $this->formatTotals($lifetime) }}</td></tr>
                            <tr><td>Client Expenses</td><td>$0.00 USD</td></tr>
                            <tr><td>Net Income</td><td>{{ $this->formatTotals($lifetime) }}</td></tr>
                            <tr><td>Credit Balance</td><td><a class="ao-link" href="{{ $urls['credits'] }}">{{ $this->formatTotals($credits) }}</a></td></tr>
                        </table>
                        <a class="ao-cp-link" href="{{ \App\Admin\Resources\InvoiceResource::getUrl('create') }}">
                            <x-filament::icon icon="ri-bill-line" class="ao-cp-ic" /> Create Invoice
                        </a>
                        <button type="button" class="ao-cp-link" wire:click="openMoney('funds')"
                            title="Raise an invoice the client can pay to put money on their balance">
                            <x-filament::icon icon="ri-money-dollar-circle-line" class="ao-cp-ic" /> Create Add Funds Invoice
                        </button>
                        <button type="button" class="ao-cp-link" wire:click="generateDueInvoices"
                            wire:confirm="Raise invoices now for every active service of this client falling due soon?"
                            title="Runs the daily cron's own rule against this client alone">
                            <x-filament::icon icon="ri-refresh-line" class="ao-cp-ic" /> Generate Due Invoices
                        </button>
                        <button type="button" class="ao-cp-link" wire:click="openMoney('credits')"
                            title="Move this account's balance by hand, up or down">
                            <x-filament::icon icon="ri-coins-line" class="ao-cp-ic" /> Manage Credits
                        </button>
                        {{-- Both used to point at core's own resource — Billable Items at
                             its bare list-plus-modal, Quotes at an index with no create
                             route at all (the button did not even open a form). Both now
                             lead to the pages the rest of the menu already leads to for the
                             same records. --}}
                        @if (class_exists(\Paymenter\Extensions\Others\BillableItems\Models\BillableItem::class))
                            <a class="ao-cp-link" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\BillableItemsList::getUrl() }}">
                                <x-filament::icon icon="ri-price-tag-3-line" class="ao-cp-ic" /> Add Billable Item
                            </a>
                        @endif
                        @if (class_exists(\Paymenter\Extensions\Others\Quotes\Models\Quote::class))
                            <a class="ao-cp-link" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\CreateQuote::getUrl() }}">
                                <x-filament::icon icon="ri-draft-line" class="ao-cp-ic" /> Create New Quote
                            </a>
                        @endif
                    </div>
                </div>

                <div class="ao-cp">
                    <h3>Other Information</h3>
                    <div class="ao-cp-body">
                        <table class="ao-cp-kv">
                            <tr><td>Status</td><td>{{ $isActive ? 'Active' : 'Inactive' }}</td></tr>
                            <tr><td>Client Group</td><td>{{ $clientGroup?->name ?? 'None' }}</td></tr>
                            <tr><td>Signup Date</td><td>{{ $user->created_at?->format('m/d/Y') }}</td></tr>
                            <tr><td>Client For</td><td>{{ $user->created_at?->diffForHumans(null, true) }}</td></tr>
                            <tr>
                                <td>Last Login</td>
                                <td>
                                    @if ($lastSeen)
                                        Date: {{ $lastSeen->last_activity?->format('m/d/Y H:i') }}<br>
                                        IP Address: {{ $lastSeen->ip_address }}
                                    @else
                                        Never
                                    @endif
                                </td>
                            </tr>
                            <tr><td>Owner Email Verified</td><td>{{ $user->email_verified_at ? 'Yes' : 'No' }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="ao-cs-col">
                <div class="ao-cp">
                    <h3>Products/Services</h3>
                    <div class="ao-cp-body">
                        <table class="ao-cp-kv">
                            @forelse ($categoryCounts as $name => $counts)
                                <tr><td>{{ $name }}</td><td>{{ $counts['open'] }} ({{ $counts['total'] }} Total)</td></tr>
                            @empty
                                <tr><td colspan="2" class="ao-cp-empty">No services yet</td></tr>
                            @endforelse
                            <tr><td>Accepted Quotes</td><td>{{ $acceptedQuotes }} ({{ $acceptedQuotes }} Total)</td></tr>
                            <tr><td>Support Tickets</td><td>{{ $ticketCount ?? $user->tickets()->count() }} ({{ $ticketCount ?? $user->tickets()->count() }} Total)</td></tr>
                            <tr><td>Affiliate Signups</td><td>{{ $affiliateSignups }}</td></tr>
                        </table>
                        {{-- View Orders used to be every order in the store, not this
                             client's — core's OrderResource has no client filter of its
                             own, and neither of the reference's two words told you that.
                             Manage Orders' free-text search takes the client's email just
                             as well as a dedicated filter would. --}}
                        <a class="ao-cp-link" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageOrders::getUrl(['q' => $user->email]) }}">
                            <x-filament::icon icon="ri-shopping-basket-2-line" class="ao-cp-ic" /> View Orders
                        </a>
                        <a class="ao-cp-link" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\AddNewOrder::getUrl() }}">
                            <x-filament::icon icon="ri-add-box-line" class="ao-cp-ic" /> Add New Order
                        </a>
                    </div>
                </div>

                <div class="ao-cp">
                    <h3>Files</h3>
                    <div class="ao-cp-body">
                        @forelse ($clientFiles as $file)
                            <div class="ao-cs-file">
                                <button type="button" class="ao-link ao-cs-file-name" wire:click="downloadClientFile({{ $file->id }})"
                                    title="{{ $file->readableSize() }} — uploaded {{ $file->created_at?->format('m/d/Y') }}">{{ $file->filename }}</button>
                                <button type="button" class="ao-cs-file-x" wire:click="deleteClientFile({{ $file->id }})"
                                    wire:confirm="Remove {{ $file->filename }}?" aria-label="Remove">&times;</button>
                            </div>
                        @empty
                            <div class="ao-cp-empty">No files uploaded</div>
                        @endforelse
                        {{-- The reference's Add File, made real: core keeps nothing per
                             client, so these live in AdminOps' own table and off the
                             public disk. --}}
                        <label class="ao-cp-link ao-cs-file-add">
                            <x-filament::icon icon="ri-add-circle-line" class="ao-cp-ic" /> Add File
                            <input type="file" wire:model="clientFile" hidden>
                        </label>
                        <div wire:loading wire:target="clientFile" class="ao-cp-note">Uploading…</div>
                    </div>
                </div>

                <div class="ao-cp">
                    <h3>Recent Emails</h3>
                    <div class="ao-cp-body">
                        @forelse ($recentEmails as $mail)
                            <div class="ao-cp-mail">
                                <span>{{ \Carbon\Carbon::parse($mail->created_at)->format('m/d/Y H:i') }}</span> -
                                <button type="button" class="ao-link" wire:click="$set('tab', 'emails')">{{ str($mail->title)->limit(34) }}</button>
                            </div>
                        @empty
                            <div class="ao-cp-empty">No emails sent</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="ao-cs-col">
                <div class="ao-cp">
                    <h3>Other Actions</h3>
                    <div class="ao-cp-body">
                        <button type="button" class="ao-cp-link" wire:click="$set('tab', 'transactions')">
                            <x-filament::icon icon="ri-file-list-3-line" class="ao-cp-ic" /> View Account Statement
                        </button>
                        {{-- AdminOps' own form with this client already chosen, rather than
                             core's bare create page — the reference opens its ticket form
                             on the client you are looking at. --}}
                        <a class="ao-cp-link" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\OpenNewTicket::getUrl(['client' => $user->id]) }}">
                            <x-filament::icon icon="ri-mail-add-line" class="ao-cp-ic" /> Open New Support Ticket
                        </a>
                        <button type="button" class="ao-cp-link" wire:click="$set('tab', 'tickets')">
                            <x-filament::icon icon="ri-customer-service-line" class="ao-cp-ic" /> View all Support Tickets
                        </button>
                        @if (class_exists(\Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageAffiliates::class))
                            <a class="ao-cp-link" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageAffiliates::getUrl() }}">
                                <x-filament::icon icon="ri-share-forward-line" class="ao-cp-ic" /> Manage Affiliate
                            </a>
                        @endif
                        <a class="ao-cp-link" href="{{ \App\Admin\Resources\UserResource::getUrl('edit', ['record' => $user->id]) }}">
                            <x-filament::icon icon="ri-user-settings-line" class="ao-cp-ic" /> Edit Client
                        </a>
                        <button type="button" class="ao-cp-link" wire:click="$set('money', 'merge')"
                            title="Move everything this account holds to another, and close this one">
                            <x-filament::icon icon="ri-git-merge-line" class="ao-cp-ic" /> Merge Clients Accounts
                        </button>
                        {{-- The reference's Close Client Account, made real. Paymenter has
                             no status column on a user, so closing is this extension's own
                             mark plus the thing closing actually means: every running
                             service cancelled. Reopening lifts the mark; it does not
                             resurrect the services, which is the reference's behaviour. --}}
                        @if ($isClosed)
                            <button type="button" class="ao-cp-link" wire:click="reopenAccount"
                                wire:confirm="Reopen this account? Its cancelled services stay cancelled."
                                title="Closed {{ $closedAt }} — the client cannot sign in">
                                <x-filament::icon icon="ri-lock-unlock-line" class="ao-cp-ic" /> Reopen Clients Account
                            </button>
                        @else
                            <button type="button" class="ao-cp-link" wire:click="closeAccount"
                                wire:confirm="Close this account? Every active or suspended service is cancelled and the client can no longer sign in."
                                title="Cancels every running service and stops the client signing in">
                                <x-filament::icon icon="ri-forbid-line" class="ao-cp-ic" /> Close Clients Account
                            </button>
                        @endif
                        {{-- Red, like the reference's: deletion itself lives on core's user
                             edit page, behind its own confirmation. --}}
                        <a class="ao-cp-link ao-cp-danger" href="{{ \App\Admin\Resources\UserResource::getUrl('edit', ['record' => $user->id]) }}">
                            <x-filament::icon icon="ri-close-circle-line" class="ao-cp-ic" /> Delete Clients Account
                        </a>
                        {{-- The reference's last action, and a real one: everything this
                             account holds, as a file, for a subject-access request. --}}
                        <button type="button" class="ao-cp-link" wire:click="exportClientData"
                            title="Download everything held on this account as JSON">
                            <x-filament::icon icon="ri-download-2-line" class="ao-cp-ic" /> Export Client Data
                        </button>
                    </div>
                </div>

                <div class="ao-cp">
                    <h3>Send Email</h3>
                    <div class="ao-cp-body">
                        <a class="ao-find-go ao-cp-send" href="mailto:{{ $user->email }}">New Message</a>
                    </div>
                </div>

                <div class="ao-cp">
                    <h3>Admin Notes</h3>
                    <div class="ao-cp-body">
                        <textarea class="ao-cp-notes" rows="5" wire:model="adminNotes" placeholder="Notes for staff only — the client never sees these"></textarea>
                        <button type="button" class="ao-find-adv ao-cp-notes-save" wire:click="saveNotes">Submit</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="ao-cs-filter-tag">Status Filter: Off</div>

        {{-- The reference's banded tables under the panels. Addons and Domains render with
             no records, which is exactly what the reference shows for a store without them. --}}
        @php
            $bands = [
                ['title' => 'Products/Services', 'head' => ['ID', 'Product/Service', 'Amount', 'Billing Cycle', 'Signup Date', 'Next Due Date', 'Status'], 'rows' => $services],
                ['title' => 'Addons', 'head' => ['ID', 'Name', 'Amount', 'Billing Cycle', 'Signup Date', 'Next Due Date', 'Status'], 'rows' => collect()],
                ['title' => 'Current Quotes', 'head' => ['ID', 'Subject', 'Date', 'Total', 'Valid Until Date', 'Status'], 'rows' => $quoteRows],
            ];
        @endphp

        @foreach ($bands as $band)
            {{-- The band's own pager. Show-entries, Previous and Next were drawn disabled
                 with nothing behind them, which is what Leandro read as the profile having
                 things on it that do not work (2026-09-10). Every row is already on the
                 page, so this pages them here rather than asking the server for a slice —
                 the buttons respond immediately and the counts are the real ones. --}}
            <div class="ao-cs-band" x-data="{
                per: 50,
                page: 1,
                rows: [],
                init() {
                    // this.$el, not $el: inside an x-data method the magics are only on
                    // `this`, and a bare $el is a ReferenceError that kills the component.
                    this.rows = [...this.$el.querySelectorAll('tbody > tr')]
                        .filter(row => !row.querySelector('.ao-mu-none'));
                },
                get total() { return this.rows.length },
                get pages() { return Math.max(1, Math.ceil(this.total / this.per)) },
                get first() { return this.total ? (this.page - 1) * this.per + 1 : 0 },
                get last() { return Math.min(this.page * this.per, this.total) },
                show() {
                    if (this.page > this.pages) this.page = this.pages;
                    this.rows.forEach((row, i) => {
                        row.style.display = (i >= this.first - 1 && i < this.last) ? '' : 'none';
                    });
                },
            }" x-effect="show()">
                <h4>{{ $band['title'] }}</h4>
                @php $tickable = $band['title'] === 'Products/Services'; @endphp
                <table class="ao-mu-grid">
                    <thead>
                        <tr>
                            {{-- The reference leads every one of these tables with a tick
                                 column. Only Products/Services can act on it here — the
                                 other three have no rows to act on. --}}
                            <th class="ao-cs-tick">
                                @if ($tickable)
                                    <input type="checkbox" wire:click="toggleAll($event.target.checked)"
                                        aria-label="Select all services">
                                @endif
                            </th>
                            @foreach ($band['head'] as $column)
                                <th>{{ $column }}</th>
                            @endforeach
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($band['title'] === 'Products/Services')
                            @forelse ($band['rows'] as $service)
                                <tr>
                                    <td class="ao-cs-tick">
                                        <input type="checkbox" wire:model.live="picked.{{ $service->id }}"
                                            aria-label="Select service {{ $service->id }}">
                                    </td>
                                    <td>{{ $service->id }}</td>
                                    <td class="ao-mu-left"><a href="{{ $urls['service']($service->id) }}">{{ $service->product?->name ?? '—' }} - (No Domain)</a></td>
                                    <td>${{ number_format((float) $service->price, 2) }} {{ $service->currency_code }}</td>
                                    <td>{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ProductsServices::cycle($service) }}</td>
                                    <td>{{ $service->created_at?->format('m/d/Y') }}</td>
                                    <td>{{ $service->expires_at?->format('m/d/Y') ?? '-' }}</td>
                                    <td><span class="ao-mu-status ao-mu-st-{{ $service->status }}">{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ProductsServices::statusLabel($service->status) }}</span></td>
                                    {{-- The same button the Products/Services page uses, and the
                                         same kind of action: it opens the service here rather
                                         than loading another screen (Leandro, 2026-09-07 — "+
                                         button should ... work as same as the Products/Services
                                         page"). --}}
                                    <td class="ao-mu-actions">
                                        <button type="button" class="ao-ps-plus"
                                            title="Open this service"
                                            wire:click="openService({{ $service->id }})">+</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="ao-mu-none">No records found</td></tr>
                            @endforelse
                        @elseif ($band['title'] === 'Current Quotes')
                            @forelse ($band['rows'] as $quote)
                                <tr>
                                    <td class="ao-cs-tick"></td>
                                    <td>{{ $quote->id }}</td>
                                    <td class="ao-mu-left">{{ $quote->subject }}</td>
                                    <td>{{ \Carbon\Carbon::parse($quote->created_at)->format('m/d/Y') }}</td>
                                    <td>—</td>
                                    <td>{{ $quote->valid_until ? \Carbon\Carbon::parse($quote->valid_until)->format('m/d/Y') : '-' }}</td>
                                    <td>{{ ucfirst($quote->status) }}</td>
                                    <td></td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="ao-mu-none">No records found</td></tr>
                            @endforelse
                        @else
                            <tr><td colspan="9" class="ao-mu-none">No records found</td></tr>
                        @endif
                    </tbody>
                </table>

                <div class="ao-cs-band-foot">
                    <span>Show
                        <select x-model.number="per"><option>10</option><option>25</option><option>50</option><option>100</option></select>
                        entries
                    </span>
                    <span x-text="`Showing ${first} to ${last} of ${total} entries`"></span>
                    <span class="ao-cs-band-pages">
                        <button type="button" @click="page = Math.max(1, page - 1)"
                            :disabled="page <= 1">Previous</button>
                        <i x-text="page"></i>
                        <button type="button" @click="page = Math.min(pages, page + 1)"
                            :disabled="page >= pages">Next</button>
                    </span>
                </div>
            </div>
        @endforeach

        {{-- The reference's two closing rows, both live since 2026-09-07. They act on the
             ticked services only, and every action re-reads them from the database scoped
             to this customer — a tick is client-side and cannot be trusted with an id. --}}
        <div class="ao-cs-selected">
            With Selected:
            <button type="button" wire:click="askBulk('invoice')"
                title="Raise one invoice carrying a line per ticked service">&#8635; Invoice Selected Items</button>
            <button type="button" class="ao-cs-danger" wire:click="askBulk('delete')"
                title="Delete the ticked service records — live services must be terminated first">&#128465; Delete Selected Items</button>
        </div>

        <div class="ao-cs-bulk">
            <span class="ao-cs-bulk-label">Bulk Actions</span>
            <select wire:model="bulkStatus" aria-label="Set status">
                <option value="">- Set Status -</option>
                <option value="pending">Pending</option>
                <option value="active">Active</option>
                <option value="suspended">Suspended</option>
                <option value="cancelled">Terminated</option>
            </select>
            {{-- No Set Payment Method: a service has no payment-method column here — it
                 is read back from the gateway of its last transaction — so the reference's
                 bulk select had nothing to write to. A disabled control that can never be
                 enabled is worse than none (Leandro, 2026-09-12). --}}
            <label class="ao-cs-bulk-hold">
                <input type="checkbox" wire:model.live="bulkHold"> Do not suspend until
            </label>
            @include('adminops::partials.datepicker', [
                'model' => 'bulkHoldUntil', 'range' => false, 'id' => 'ao-cs-bulk-hold',
                'placeholder' => 'MM/DD/YYYY', 'class' => 'ao-of-md',
            ])
            <span class="ao-cs-bulk-right">
                <button type="button" class="ao-find-go" wire:click="askBulk('apply')">Apply</button>
            </span>
        </div>

        @if ($confirmingBulk)
            <div class="ao-mud-overlay" wire:click.self="$set('confirmingBulk', null)">
                <div class="ao-mud ao-mud-sm" role="alertdialog" aria-modal="true">
                    <div class="ao-mud-head">
                        Are you sure?
                        <button type="button" wire:click="$set('confirmingBulk', null)" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text">
                        @if ($confirmingBulk === 'invoice')
                            <p>Raise one invoice for the ticked service(s)?</p>
                            <p>It is created unpaid and due in seven days.</p>
                        @elseif ($confirmingBulk === 'delete')
                            <p>Delete the ticked service record(s)?</p>
                            <p>Anything still live must be terminated first — this removes the record, not the provisioned service.</p>
                        @else
                            <p>Apply these changes to the ticked service(s)?</p>
                        @endif
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="$set('confirmingBulk', null)">Cancel</button>
                            <button type="button" class="{{ $confirmingBulk === 'delete' ? 'ao-mud-delete' : 'ao-find-go' }}" wire:click="runBulk">OK</button>
                        </span>
                    </div>
                </div>
            </div>
        @endif

    @elseif ($tab === 'profile')
        {{-- The reference's Profile tab is the client's *edit form*, prefilled — the same
             two-column zebra the Add New Client page uses, saving back to the same user
             columns and properties. --}}
        <form class="ao-mu ao-anc" wire:submit.prevent="saveProfile">
            <div class="ao-anc-card">
                <div class="ao-anc-cols">
                    <div class="ao-anc-col">
                        <label class="ao-anc-row"><span>First Name</span><input type="text" wire:model="pf.first_name" placeholder="John" required></label>
                        <label class="ao-anc-row"><span>Last Name</span><input type="text" wire:model="pf.last_name" placeholder="Doe" required></label>
                        <label class="ao-anc-row">
                            <span>Company Name</span>
                            <span class="ao-anc-field"><input type="text" wire:model="pf.company_name" placeholder="Acme Technologies, Inc."><i>(Optional)</i></span>
                        </label>
                        <label class="ao-anc-row"><span>Email Address</span><input type="email" wire:model="pf.email" placeholder="user@example.com" required></label>
                        <label class="ao-anc-row"><span>Language</span><select><option>Default</option></select></label>
                        <label class="ao-anc-row"><span>Status</span><select><option>{{ $user->services()->whereIn('status', ['pending', 'active', 'suspended'])->exists() ? 'Active' : 'Inactive' }}</option></select></label>
                        {{-- Real since 2026-09-07: the group carries a discount, a
                             suspend exemption and the separate-invoice rule. --}}
                        <label class="ao-anc-row">
                            <span>Client Group</span>
                            <select class="ao-w-40" wire:model="pfGroup">
                                <option value="">None</option>
                                @foreach ($clientGroups as $group)
                                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <div class="ao-anc-col">
                        <label class="ao-anc-row"><span>Address 1</span><input type="text" wire:model="pf.address" placeholder="123 Market Street"></label>
                        <label class="ao-anc-row">
                            <span>Address 2</span>
                            <span class="ao-anc-field"><input type="text" wire:model="pf.address2" placeholder="Suite 400"><i>(Optional)</i></span>
                        </label>
                        <label class="ao-anc-row"><span>City</span><input type="text" wire:model="pf.city" placeholder="San Francisco"></label>
                        {{-- Country and its subdivisions are picked, not typed — the
                             reference's own behaviour, and the profile stores the country's
                             name so the select's values are names too. --}}
                        @php $pfRegions = \Paymenter\Extensions\Others\AdminOps\Support\Regions::for($pf['country'] ?? ''); @endphp
                        <label class="ao-anc-row">
                            <span>State/Region</span>
                            @if ($pfRegions)
                                <select wire:model="pf.state" wire:key="pf-state-{{ $pf['country'] ?? '' }}">
                                    <option value="">—</option>
                                    @foreach ($pfRegions as $region)
                                        <option value="{{ $region }}">{{ $region }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input type="text" wire:model="pf.state" placeholder="—">
                            @endif
                        </label>
                        <label class="ao-anc-row"><span>Postcode</span><input type="text" wire:model="pf.zip" placeholder="94105"></label>
                        <label class="ao-anc-row">
                            <span>Country</span>
                            <select wire:model.live="pf.country">
                                <option value="">Select a country</option>
                                @foreach ($countries as $name)
                                    <option value="{{ $name }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="ao-anc-row"><span>Phone Number</span><input type="text" wire:model="pf.phone" placeholder="+1 201-555-0123"></label>
                        <label class="ao-anc-row"><span>Currency</span><input type="text" wire:model="pf.currency" placeholder="USD"></label>
                    </div>
                </div>

                <div class="ao-anc-row ao-anc-row-wide ao-anc-grey">
                    <span>Email Notifications</span>
                    <div class="ao-anc-checks">
                        @foreach ([
                            'general' => 'General Emails - All account related emails',
                            'invoice' => 'Invoice Emails - New Invoices, Reminders, & Overdue Notices',
                            'support' => 'Support Emails - Receive a copy of all Support Ticket Communications',
                            'product' => 'Product Emails - Welcome Emails, Suspensions & Other Lifecycle Notifications',
                            'affiliate' => 'Affiliate Emails - Receive Affiliate Notifications',
                        ] as $key => $label)
                            <label><input type="checkbox" wire:model="pfPrefs.{{ $key }}"> {{ $label }}</label>
                        @endforeach
                        {{-- The reference's Check All, under the list. --}}
                        <button type="button" class="ao-link ao-cc-all"
                            x-data
                            @click="$el.closest('.ao-anc-checks').querySelectorAll('input[type=checkbox]').forEach(c => { if (!c.checked) c.click(); })">
                            Check All
                        </button>
                    </div>
                </div>

                <div class="ao-anc-row ao-anc-row-wide ao-anc-grey">
                    <span>Settings</span>
                    <div class="ao-anc-toggles">
                        @foreach ([
                            'late_fees' => 'Late Fees',
                            'overdue_notices' => 'Overdue Notices',
                            'tax_exempt' => 'Tax Exempt',
                            'separate_invoices' => 'Separate Invoices',
                            'disable_cc' => 'Disable CC Processing',
                            'marketing_optin' => 'Marketing Emails Opt-in',
                            'status_update' => 'Status Update',
                            'single_sign_on' => 'Allow Single Sign-On',
                        ] as $key => $label)
                            <label class="ao-anc-switch">
                                <input type="checkbox" wire:model="pfSettings.{{ $key }}">
                                <i aria-hidden="true"></i>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- The reference closes Profile with Admin Notes, the same note the Summary
                     panel edits — it was only on Summary, so someone working down this form
                     had to leave it to add one. --}}
                <div class="ao-anc-row ao-anc-row-wide">
                    <span>Admin Notes</span>
                    <div>
                        <textarea class="ao-cp-notes" rows="6" wire:model="adminNotes"
                            placeholder="Notes for staff only — the client never sees these"></textarea>
                    </div>
                </div>
            </div>

            @if ($errors->any())
                <div class="ao-anc-errors">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="ao-anc-submit">
                <button type="submit" class="ao-find-go">Save Changes</button>
                <a class="ao-pg-btn" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $user->id, 'tab' => 'profile']) }}">Cancel Changes</a>
            </div>
        </form>

    @elseif ($tab === 'quotes')
        <div class="ao-ct-head">
            <a class="ao-mu-tab" href="{{ $urls['newQuote'] }}">&#10010; Create New Quote</a>
        </div>

        @include('adminops::partials.records-band', [
            'total' => $rowTotal(), 'page' => $page, 'perPage' => $perPage,
        ])

        <div class="ao-cs-band">
            <h4>Quotes</h4>
            <table class="ao-mu-grid">
                <thead>
                    <tr>
                        <th>Quote #</th><th>Subject</th><th>Create Date</th>
                        <th>Valid Until</th><th class="ao-num">Total</th><th>Stage</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $quote)
                        <tr>
                            <td><a class="ao-link" href="{{ $urls['quote']($quote->id) }}">#{{ $quote->id }}</a></td>
                            <td class="ao-mu-left">{{ $quote->subject }}</td>
                            <td>{{ \Carbon\Carbon::parse($quote->created_at)->format('j M Y') }}</td>
                            <td>{{ $quote->valid_until ? \Carbon\Carbon::parse($quote->valid_until)->format('j M Y') : '—' }}</td>
                            <td class="ao-num">{{ number_format((float) ($quote->total ?? 0), 2) }}</td>
                            <td><span class="ao-tag">{{ ucfirst($quote->status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="ao-mu-none">No Records Found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('adminops::partials.records-pager', [
            'total' => $rowTotal(), 'page' => $page, 'perPage' => $perPage,
        ])

    @elseif ($tab === 'notes')
        {{-- The reference's Notes tab: a list of dated notes, each by a named admin. This
             was one shared textarea, which the Summary panel still shows — see
             client-notes for why a log needed rows. --}}
        @include('adminops::pages.client-notes')

    @elseif ($tab === 'services' && ($svcModel ?? null))
        {{-- The reference's per-service editor inside the profile, reached from the order
             view's "Product/Service" link and the services list's ID column. Every field
             is the real column or property; the few WHMCS controls with no home here are
             dead with the reason on their titles. --}}
        <div class="ao-cs-pickrow">
            <select class="ao-of-lg" wire:change="goService($event.target.value)">
                @foreach ($rows as $row)
                    <option value="{{ $row->id }}" @selected($row->id === $svcModel->id)>
                        #{{ $row->id }} · {{ $row->product?->name }}
                    </option>
                @endforeach
            </select>
            <button type="button" class="ao-of-go" wire:click="$refresh">Go</button>
            {{-- At the row's right, as the reference sets it (user feedback, 2026-09-04). --}}
            <span class="ao-cs-pickrow-right">
                <button type="button" class="ao-find-go {{ $addingAddon ? 'ao-on' : '' }}" wire:click="toggleAddingAddon">
                    &#10010; New Addon
                </button>
            </span>
        </div>

        @if ($addingAddon)
            {{-- The reference replaces the editor with this screen; everything else hides
                 until Save or Cancel (user feedback, 2026-09-04). Two columns, the
                 reference's own field order. --}}
            <h4 class="ao-ano-heading">Add New Addon</h4>
            <form class="ao-find ao-of ao-of-even ao-cs-addon" autocomplete="off" wire:submit.prevent="saveAddon">
                <div class="ao-of-rows">
                    <div class="ao-of-row">
                        <span class="ao-of-label">Parent Product/Service</span>
                        <span class="ao-eo-fact">#{{ $svcModel->id }} · {{ $svcModel->product?->name }}</span>
                        <label class="ao-of-label" for="ao-cs-ad-qty">Quantity</label>
                        <span><input id="ao-cs-ad-qty" class="ao-of-sm" type="number" min="1" wire:model="addon.quantity"></span>
                    </div>
                    <div class="ao-of-row">
                        <span class="ao-of-label">Registration Date</span>
                        <span class="ao-eo-fact">{{ now()->format('m/d/Y') }}</span>
                        <label class="ao-of-label" for="ao-cs-ad-fee">Setup Fee</label>
                        <span><input id="ao-cs-ad-fee" class="ao-of-sm" type="text" inputmode="decimal" wire:model="addon.setupFee" placeholder="0.00"
                            title="Charged once, as its own line on the generated invoice"></span>
                    </div>
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-cs-ad-product">Predefined Addon</label>
                        <span><select id="ao-cs-ad-product" class="ao-of-md" wire:model.live="addon.productId">
                            <option value="">None</option>
                            @foreach ($addonCatalogue as $addonProduct)
                                <option value="{{ $addonProduct->id }}">{{ $addonProduct->name }}</option>
                            @endforeach
                        </select></span>
                        <label class="ao-of-label" for="ao-cs-ad-price">Recurring</label>
                        <span><input id="ao-cs-ad-price" class="ao-of-sm" type="text" inputmode="decimal" wire:model="addon.price"
                            title="Prefilled from the addon's own plan; renews with the parent" placeholder="0.00"></span>
                    </div>
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-cs-ad-name">Custom Name</label>
                        <span><input id="ao-cs-ad-name" class="ao-of-md" type="text" wire:model="addon.name"></span>
                        <span class="ao-of-label">Billing Cycle</span>
                        <span class="ao-eo-fact">Renews with its parent service</span>
                    </div>
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-cs-ad-status">Status</label>
                        <span><select id="ao-cs-ad-status" class="ao-of-sm" wire:model="addon.status">
                            <option value="pending">Pending</option>
                            <option value="active">Active</option>
                        </select></span>
                        <span class="ao-of-label">Next Due Date</span>
                        <span class="ao-eo-fact">{{ $svcModel->expires_at?->format('m/d/Y') ?? 'N/A' }}</span>
                    </div>
                    <div class="ao-of-row">
                        <span class="ao-of-label">Payment Method</span>
                        <span class="ao-eo-fact">{{ $svcPayment }}</span>
                        <label class="ao-of-label" for="ao-cs-ad-term">Termination Date</label>
                        @include('adminops::partials.datepicker', [
                            'model' => 'addon.terminationDate', 'range' => false, 'id' => 'ao-cs-ad-term',
                            'placeholder' => 'MM/DD/YYYY', 'class' => 'ao-of-md',
                        ])
                    </div>
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-cs-ad-sub">Subscription ID</label>
                        <span><input id="ao-cs-ad-sub" class="ao-of-md" type="text" wire:model="addon.subscriptionId"></span>
                        <span class="ao-of-label">Tax Addon</span>
                        <span class="ao-of-check" title="This store computes no tax; totals are final">
                            <input type="checkbox" disabled>
                        </span>
                    </div>
                    <div class="ao-of-row ao-of-row-single">
                        <label class="ao-of-label" for="ao-cs-ad-notes">Admin Notes</label>
                        <span><textarea id="ao-cs-ad-notes" rows="4" wire:model="addon.notes"></textarea></span>
                    </div>
                </div>
                @if ($addonCatalogue->isEmpty())
                    <p class="ao-gs-empty">The Service Addons catalogue category has no products yet — add one there first.</p>
                @endif
                <div class="ao-of-buttons">
                    <label class="ao-of-check ao-cs-ad-geninv">
                        <input type="checkbox" wire:model="addon.invoice"> Generate Invoice after Adding
                    </label>
                </div>
                <div class="ao-of-buttons">
                    <button type="submit" class="ao-find-go">Save Changes</button>
                    <button type="button" class="ao-of-go" wire:click="toggleAddingAddon">Cancel</button>
                </div>
            </form>
        @else

        <form class="ao-find ao-of ao-of-even ao-cs-service" autocomplete="off" wire:submit.prevent="saveService">
            <div class="ao-of-rows">
                <div class="ao-of-row">
                    <span class="ao-of-label">Order #</span>
                    <span class="ao-eo-fact">
                        {{ $svcModel->order_id }} -
                        <a class="ao-link" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\EditOrder::getUrl(['record' => $svcModel->order_id]) }}">View Order</a>
                    </span>
                    <label class="ao-of-label" for="ao-cs-reg">Registration Date</label>
                    @include('adminops::partials.datepicker', [
                        'model' => 'svc.regDate', 'range' => false, 'id' => 'ao-cs-reg',
                        'placeholder' => 'MM/DD/YYYY', 'class' => 'ao-of-md',
                    ])
                </div>
                <div class="ao-of-row">
                    <label class="ao-of-label" for="ao-cs-product">Product/Service</label>
                    <span><select id="ao-cs-product" class="ao-of-lg" wire:model.live="svc.productId">
                        @foreach ($svcProducts as $product)
                            <option value="{{ $product->id }}">{{ $product->category?->name }} - {{ $product->name }}</option>
                        @endforeach
                    </select></span>
                    <label class="ao-of-label" for="ao-cs-qty">Quantity</label>
                    <span><input id="ao-cs-qty" class="ao-of-sm" type="number" min="1" wire:model="svc.quantity"></span>
                </div>
                <div class="ao-of-row">
                    {{-- The server this service runs on. It is the product's, and core
                         resolves it from `product->server` on every lifecycle call, so it
                         is stated rather than offered as a choice that cannot be made —
                         the product's own editor is where it changes. --}}
                    <span class="ao-of-label">Server</span>
                    <span class="ao-of-plain">
                        {{ $svcModel->product?->server?->name ?? 'None' }}
                        @if ($svcModel->product)
                            <a class="ao-link ao-of-plain-go"
                                href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\EditProduct::getUrl(['record' => $svcModel->product->id]) }}"
                                title="Change the server on the product">change</a>
                        @endif
                    </span>
                    <label class="ao-of-label" for="ao-cs-price">First Payment Amount</label>
                    <span><input id="ao-cs-price" class="ao-of-sm" type="text" inputmode="decimal" wire:model="svc.price"></span>
                </div>
                <div class="ao-of-row">
                    <span class="ao-of-label"></span>
                    <span></span>
                    {{-- The reference's Recurring Amount and its Recalculate on Save. One
                         figure is charged each cycle here, so this shows what the next
                         renewal takes; ticking Recalculate re-reads the plan's price on
                         save instead of keeping what this service was sold at. --}}
                    <span class="ao-of-label">Recurring Amount</span>
                    <span class="ao-of-inline">
                        <input class="ao-of-sm" type="text" readonly
                            value="{{ number_format((float) $svcModel->price * max(1, (int) $svcModel->quantity), 2) }}"
                            title="The renewal charge — set it through First Payment Amount, or tick Recalculate on Save to take the plan's current price">
                        <label class="ao-check ao-cs-recalc">
                            <input type="checkbox" wire:model="svc.recalculate">
                            <span>Recalculate on Save</span>
                        </label>
                    </span>
                </div>
                <div class="ao-of-row">
                    <label class="ao-of-label" for="ao-cs-dip">Dedicated IP</label>
                    <span><input id="ao-cs-dip" class="ao-of-md" type="text" wire:model="svc.dedicatedIp" placeholder=""></span>
                    <label class="ao-of-label" for="ao-cs-due">Next Due Date</label>
                    @include('adminops::partials.datepicker', [
                        'model' => 'svc.nextDue', 'range' => false, 'id' => 'ao-cs-due',
                        'placeholder' => 'MM/DD/YYYY', 'class' => 'ao-of-md',
                    ])
                </div>
                <div class="ao-of-row">
                    <label class="ao-of-label" for="ao-cs-user">Username</label>
                    <span><input id="ao-cs-user" class="ao-of-md" type="text" wire:model="svc.username"></span>
                    <label class="ao-of-label" for="ao-cs-term">Termination Date</label>
                    @include('adminops::partials.datepicker', [
                        'model' => 'svc.terminationDate', 'range' => false, 'id' => 'ao-cs-term',
                        'placeholder' => 'MM/DD/YYYY', 'class' => 'ao-of-md',
                    ])
                </div>
                <div class="ao-of-row">
                    <label class="ao-of-label" for="ao-cs-pass">Password</label>
                    <span><input id="ao-cs-pass" class="ao-of-md" type="password" wire:model="svc.password"
                        title="Masked on screen (the standing credentials convention); the value itself edits and saves"></span>
                    <label class="ao-of-label" for="ao-cs-plan">Billing Cycle</label>
                    <span><select id="ao-cs-plan" class="ao-of-md" wire:model="svc.planId">
                        @forelse ($svcPlans as $plan)
                            <option value="{{ $plan->id }}">{{ \Paymenter\Extensions\Others\AdminOps\Support\ProductConfig::cycleLabel($plan) }}</option>
                        @empty
                            <option value="">No plans on this product</option>
                        @endforelse
                    </select></span>
                </div>
                <div class="ao-of-row">
                    <label class="ao-of-label" for="ao-cs-status">Status</label>
                    <span><select id="ao-cs-status" class="ao-of-sm" wire:model="svc.status">
                        <option value="pending">Pending</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                        <option value="cancelled">Terminated</option>
                    </select></span>
                    {{-- The gateway that took payment on this service's invoices: a fact
                         about those transactions, not a setting, so it is stated rather
                         than offered as a choice that cannot be made. --}}
                    <span class="ao-of-label">Payment Method</span>
                    <span class="ao-of-plain" title="Whichever gateway the client paid this service's invoices with — chosen at checkout">{{ $svcPayment }}</span>
                </div>
                <div class="ao-of-row">
                    <span class="ao-of-label"></span>
                    <span></span>
                    <label class="ao-of-label" for="ao-cs-coupon">Promotion Code</label>
                    <span><select id="ao-cs-coupon" class="ao-of-md" wire:model="svc.couponId">
                        <option value="">None</option>
                        @foreach ($svcCoupons as $coupon)
                            <option value="{{ $coupon->id }}">{{ $coupon->code }}</option>
                        @endforeach
                    </select></span>
                </div>
                {{-- The reference's Region row (and any other configurable option): a real
                     select over that option's own values, saving with the form. --}}
                @foreach ($svcConfigChoices as $optionId => $choice)
                    <div class="ao-of-row ao-of-row-single">
                        <label class="ao-of-label" for="ao-cs-cfg-{{ $optionId }}">{{ $choice['label'] }}</label>
                        <span><select id="ao-cs-cfg-{{ $optionId }}" class="ao-of-lg" wire:model="svc.configs.{{ $optionId }}">
                            @foreach ($choice['values'] as $value)
                                <option value="{{ $value->id }}">{{ $value->name }}</option>
                            @endforeach
                        </select></span>
                    </div>
                @endforeach
                {{-- Each command applies to some states and not others, and a button that
                     takes the click then answers "nothing to do" reads as broken (Leandro,
                     issue #53). What cannot run is drawn inert with the reason on it. --}}
                @php
                    $svcState = (string) $svcModel->status;
                    $hasServer = (bool) $svcModel->product?->server;
                    $commands = [
                        ['create', 'Create', ['pending'], 'Provision this service on its panel now?'],
                        ['suspend', 'Suspend', ['active'], 'Suspend this service on its panel?'],
                        ['unsuspend', 'Unsuspend', ['suspended'], 'Unsuspend this service on its panel?'],
                        ['terminate', 'Terminate', ['active', 'suspended'], 'Terminate this service on its panel? This deprovisions it.'],
                        ['change_package', 'Change Package', ['active'], 'Push the saved product and plan to the panel now? (Save Changes first if you just picked a different one.)'],
                        ['change_password', 'Change Password', ['active', 'suspended'], 'Generate a new proxy password on the panel? The current one stops working immediately.'],
                    ];
                @endphp
                <div class="ao-of-row ao-of-row-single">
                    <span class="ao-of-label">Module Commands</span>
                    <span class="ao-of-inline ao-cs-cmds">
                        @foreach ($commands as [$cmd, $label, $states, $confirm])
                            @php $can = $hasServer && in_array($svcState, $states, true); @endphp
                            <button type="button" class="ao-of-go" @disabled(!$can)
                                title="{{ $can
                                    ? ($cmd === 'change_package'
                                        ? 'Pushes the saved Product/Service and Billing Cycle to the panel — pick them above and Save Changes first'
                                        : $label . ' this service on its panel')
                                    : (!$hasServer
                                        ? 'This product has no server module, so there is nothing to command'
                                        : $label . ' applies to a ' . implode(' or ', $states) . ' service — this one is ' . $svcState) }}"
                                @if ($can) wire:click="runModule('{{ $cmd }}')" wire:confirm="{{ $confirm }}" @endif>{{ $label }}</button>
                        @endforeach
                    </span>
                </div>
                <div class="ao-of-row ao-of-row-single">
                    <span class="ao-of-label">Addons</span>
                    <span>
                        <table class="ao-mu-grid ao-cs-addons">
                            <thead>
                                <tr><th>Reg Date</th><th>Name</th><th>Pricing</th><th>Status</th><th>Next Due Date</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($svcAddons as $addon)
                                    <tr>
                                        <td>{{ $addon->service?->created_at?->format('m/d/Y') }}</td>
                                        {{-- The Custom Name the Add New Addon form takes, which
                                             this column used to throw away by naming the product
                                             instead — so an addon you had deliberately named came
                                             back indistinguishable from any other of that product.
                                             `label` falls back to the product name on its own. --}}
                                        <td class="ao-mu-left">{{ $addon->service?->label }}</td>
                                        <td>${{ number_format((float) $addon->service?->price, 2) }} {{ $addon->service?->currency_code }}</td>
                                        <td>{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ProductsServices::statusLabel((string) $addon->service?->status) }}</td>
                                        <td>{{ $addon->service?->expires_at?->format('m/d/Y') ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="ao-mu-none ao-mu-left">No Records Found</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </span>
                </div>
                {{-- The reference's custom fields — Proxies, Service ID, api-key and
                     whatever else the module stored — editable in place. --}}
                @foreach ($svc['props'] ?? [] as $index => $row)
                    <div class="ao-of-row ao-of-row-single">
                        <label class="ao-of-label" for="ao-cs-prop-{{ $index }}">{{ $row['name'] }}</label>
                        <span>
                            @if (str_contains(strtolower($row['key']), 'proxies') || strlen($row['value']) > 90)
                                <textarea id="ao-cs-prop-{{ $index }}" rows="3" wire:model="svc.props.{{ $index }}.value"></textarea>
                            @else
                                <input id="ao-cs-prop-{{ $index }}" class="ao-of-xl" type="text" wire:model="svc.props.{{ $index }}.value">
                            @endif
                        </span>
                    </div>
                @endforeach
                {{-- After the module's custom fields, as the reference's circled screenshot
                     orders it: Proxies, Service ID, api-key, then Subscription ID. Always
                     rendered — on an unprovisioned service they are simply empty, not
                     missing (user feedback, 2026-09-04). --}}
                <div class="ao-of-row ao-of-row-single">
                    <label class="ao-of-label" for="ao-cs-svcid">Service ID</label>
                    <span><input id="ao-cs-svcid" class="ao-of-xl" type="text" wire:model="svc.serviceId"
                        placeholder="Set by the panel when the service provisions"></span>
                </div>
                <div class="ao-of-row ao-of-row-single">
                    <label class="ao-of-label" for="ao-cs-apikey">api-key</label>
                    <span><input id="ao-cs-apikey" class="ao-of-xl" type="text" wire:model="svc.apiKey"
                        placeholder="Set by the panel when the service provisions"></span>
                </div>
                <div class="ao-of-row ao-of-row-single">
                    <label class="ao-of-label" for="ao-cs-sub">Subscription ID</label>
                    <span><input id="ao-cs-sub" class="ao-of-md" type="text" wire:model="svc.subscriptionId"></span>
                </div>
                <div class="ao-of-row ao-of-row-single">
                    <span class="ao-of-label">Override Auto-Suspend</span>
                    <span class="ao-of-check" title="While the date is ahead, the overdue ladder's suspension is undone by the hourly overrides sweep">
                        <input type="checkbox" wire:model.live="svc.noSuspend"> Do not suspend until
                        @include('adminops::partials.datepicker', [
                            'model' => 'svc.noSuspendUntil', 'range' => false, 'id' => 'ao-cs-nosus',
                            'placeholder' => 'MM/DD/YYYY', 'class' => 'ao-of-md',
                        ])
                    </span>
                </div>
                <div class="ao-of-row ao-of-row-single">
                    <span class="ao-of-label">Auto-Terminate End of Cycle</span>
                    <span class="ao-of-check" title="Saved as a real end-of-period cancellation: the next invoice is skipped and the service terminates when its period ends">
                        <input type="checkbox" wire:model.live="svc.autoTerminate"> Reason
                        <input class="ao-of-lg" type="text" wire:model="svc.autoTerminateReason"
                            placeholder="Why this service ends with its period">
                    </span>
                </div>
                <div class="ao-of-row ao-of-row-single">
                    <label class="ao-of-label" for="ao-cs-notes">Admin Notes</label>
                    <span><textarea id="ao-cs-notes" rows="3" wire:model="svc.svcNotes"
                        placeholder="Notes for staff only"></textarea></span>
                </div>
            </div>
            <div class="ao-of-buttons">
                <button type="submit" class="ao-find-go">Save Changes</button>
                <button type="button" class="ao-of-go" wire:click="goService({{ $svcModel->id }})">Cancel Changes</button>
            </div>
        </form>
        @endif

    @elseif ($tab === 'contacts')
        @include('adminops::pages.client-contacts')

    @elseif ($tab === 'users')
        @include('adminops::pages.client-users')

    @else
        {{-- Every other tab is one list of one thing. `adminops::pages.client-tab` renders
             whichever it is, so a new tab is a case there rather than another block here. --}}
        @include('adminops::pages.client-tab', [
            'tab' => $tab, 'rows' => $rows, 'urls' => $urls,
            'ticketStats' => $ticketStats ?? null, 'totals' => $totals ?? null,
            'logUsers' => $logUsers ?? null,
        ])
    @endif

    {{-- The reference's two money dialogs: Create Add Funds Invoice with its explanation
         and one Amount box, and Manage Credits, which moves the balance by hand. --}}
    @if ($money)
        <div class="ao-mud-overlay" wire:click.self="$set('money', null)">
            <div class="ao-mud ao-mud-money" role="dialog" aria-modal="true">
                <div class="ao-mud-head">
                    {{ ['funds' => 'Create Add Funds Invoice', 'credits' => 'Manage Credits', 'merge' => 'Merge Clients Accounts'][$money] }}
                    <button type="button" wire:click="$set('money', null)" aria-label="Close">&times;</button>
                </div>
                <div class="ao-mud-body">
                    @if ($money === 'merge')
                        <p class="ao-mud-text">Everything this account holds — services, invoices, orders, tickets,
                            transactions and credit — moves to the account you pick, and this one is closed.
                            It cannot be undone.</p>
                        <div class="ao-mud-row">
                            <label for="ao-merge">Merge into:</label>
                            <select id="ao-merge" class="ao-mud-wide" wire:model="mergeInto">
                                <option value="">Choose an account</option>
                                @foreach ($mergeCandidates as $candidate)
                                    <option value="{{ $candidate->id }}">{{ trim($candidate->first_name . ' ' . $candidate->last_name) ?: $candidate->email }} - #{{ $candidate->id }}</option>
                                @endforeach
                            </select>
                        </div>
                        @error('mergeInto') <p class="ao-anc-errors">{{ $message }}</p> @enderror
                    @elseif ($money === 'funds')
                        <p class="ao-mud-text">You can create invoices in this way to allow a client to deposit funds to their account.</p>
                        <div class="ao-mud-row">
                            <label for="ao-funds">Amount:</label>
                            <input type="text" id="ao-funds" wire:model="fundsAmount">
                        </div>
                        @error('fundsAmount') <p class="ao-anc-errors">{{ $message }}</p> @enderror
                    @else
                        <p class="ao-mud-text">Add to or take from this account's balance. A positive amount credits it, a negative one debits it.</p>
                        <div class="ao-mud-row">
                            <label>Current Balance:</label>
                            <b>{{ $this->formatTotals($credits) }}</b>
                        </div>
                        <div class="ao-mud-row">
                            <label for="ao-credit">Amount:</label>
                            <input type="text" id="ao-credit" wire:model="creditAmount">
                        </div>
                        @error('creditAmount') <p class="ao-anc-errors">{{ $message }}</p> @enderror
                    @endif
                </div>
                <div class="ao-mud-foot ao-mud-foot-only-right">
                    <span class="ao-mud-foot-right">
                        <button type="button" class="ao-find-go"
                            @if ($money === 'merge') wire:confirm="Move everything to the chosen account and close this one?" @endif
                            wire:click="{{ ['funds' => 'createAddFundsInvoice', 'credits' => 'applyCredit', 'merge' => 'mergeAccounts'][$money] }}">Submit</button>
                        <button type="button" class="ao-mud-close" wire:click="$set('money', null)">Cancel</button>
                    </span>
                </div>
            </div>
        </div>
    @endif

    </div>
</x-filament-panels::page>
