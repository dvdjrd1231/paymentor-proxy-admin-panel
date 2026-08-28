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
                        <div class="ao-cp-empty">No additional contacts setup</div>
                        <span class="ao-cp-link ao-cp-dead" title="Contacts are not part of Paymenter">
                            <x-filament::icon icon="ri-user-add-line" class="ao-cp-ic" /> Add Contact
                        </span>
                    </div>
                </div>

                <div class="ao-cp">
                    <h3>Pay Methods</h3>
                    <div class="ao-cp-body">
                        <div class="ao-cp-empty">No Pay Methods</div>
                        <span class="ao-cp-link ao-cp-dead" title="Stored cards are not part of Paymenter">
                            <x-filament::icon icon="ri-bank-card-line" class="ao-cp-ic" /> Add Credit Card
                        </span>
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
                        <a class="ao-cp-link" href="{{ $urls['credits'] }}">
                            <x-filament::icon icon="ri-money-dollar-circle-line" class="ao-cp-ic" /> Create Add Funds Invoice
                        </a>
                        <span class="ao-cp-link ao-cp-dead" title="Invoices are generated by the daily cron">
                            <x-filament::icon icon="ri-refresh-line" class="ao-cp-ic" /> Generate Due Invoices
                        </span>
                        <a class="ao-cp-link" href="{{ $urls['credits'] }}">
                            <x-filament::icon icon="ri-coins-line" class="ao-cp-ic" /> Manage Credits
                        </a>
                        @php
                            // The resource creates via modal, so a 'create' route may not
                            // exist — resolved defensively, exactly like the nav does.
                            $billableUrl = null;
                            try {
                                if (class_exists(\Paymenter\Extensions\Others\BillableItems\Admin\Resources\BillableItemResource::class)) {
                                    $billableUrl = \Paymenter\Extensions\Others\BillableItems\Admin\Resources\BillableItemResource::getUrl('index');
                                }
                            } catch (\Throwable $e) {
                            }
                        @endphp
                        @if ($billableUrl)
                            <a class="ao-cp-link" href="{{ $billableUrl }}">
                                <x-filament::icon icon="ri-price-tag-3-line" class="ao-cp-ic" /> Add Billable Item
                            </a>
                        @endif
                        @if (class_exists(\Paymenter\Extensions\Others\Quotes\Admin\Resources\QuoteResource::class))
                            <a class="ao-cp-link" href="{{ \Paymenter\Extensions\Others\Quotes\Admin\Resources\QuoteResource::getUrl('index') }}">
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
                            <tr><td>Client Group</td><td>None</td></tr>
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
                        <a class="ao-cp-link" href="{{ \App\Admin\Resources\OrderResource::getUrl('index') }}">
                            <x-filament::icon icon="ri-shopping-basket-2-line" class="ao-cp-ic" /> View Orders
                        </a>
                        <a class="ao-cp-link" href="{{ \App\Admin\Resources\OrderResource::getUrl('create') }}">
                            <x-filament::icon icon="ri-add-box-line" class="ao-cp-ic" /> Add New Order
                        </a>
                    </div>
                </div>

                <div class="ao-cp">
                    <h3>Files</h3>
                    <div class="ao-cp-body">
                        <div class="ao-cp-empty">No files uploaded</div>
                        <span class="ao-cp-link ao-cp-dead" title="Paymenter has no per-client file storage">
                            <x-filament::icon icon="ri-add-circle-line" class="ao-cp-ic" /> Add File
                        </span>
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
                        <a class="ao-cp-link" href="{{ \App\Admin\Resources\TicketResource::getUrl('create') }}">
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
                        <span class="ao-cp-link ao-cp-dead" title="Paymenter has no account merging">
                            <x-filament::icon icon="ri-git-merge-line" class="ao-cp-ic" /> Merge Clients Accounts
                        </span>
                        <span class="ao-cp-link ao-cp-dead" title="Paymenter has no closed status — cancel the client's services instead">
                            <x-filament::icon icon="ri-forbid-line" class="ao-cp-ic" /> Close Clients Account
                        </span>
                        {{-- Red, like the reference's: deletion itself lives on core's user
                             edit page, behind its own confirmation. --}}
                        <a class="ao-cp-link ao-cp-danger" href="{{ \App\Admin\Resources\UserResource::getUrl('edit', ['record' => $user->id]) }}">
                            <x-filament::icon icon="ri-close-circle-line" class="ao-cp-ic" /> Delete Clients Account
                        </a>
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
                ['title' => 'Domains', 'head' => ['ID', 'Domain', 'Registrar', 'Registration Date', 'Next Due Date', 'Expiry Date', 'Status'], 'rows' => collect()],
                ['title' => 'Current Quotes', 'head' => ['ID', 'Subject', 'Date', 'Total', 'Valid Until Date', 'Status'], 'rows' => $quoteRows],
            ];
        @endphp

        @foreach ($bands as $band)
            <div class="ao-cs-band">
                <h4>{{ $band['title'] }}</h4>
                <table class="ao-mu-grid">
                    <thead>
                        <tr>
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
                                    <td>{{ $service->id }}</td>
                                    <td class="ao-mu-left"><a href="{{ $urls['service']($service->id) }}">{{ $service->product?->name ?? '—' }} - (No Domain)</a></td>
                                    <td>${{ number_format((float) $service->price, 2) }} {{ $service->currency_code }}</td>
                                    <td>{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ProductsServices::cycle($service) }}</td>
                                    <td>{{ $service->created_at?->format('m/d/Y') }}</td>
                                    <td>{{ $service->expires_at?->format('m/d/Y') ?? '-' }}</td>
                                    <td><span class="ao-mu-status ao-mu-st-{{ $service->status }}">{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ProductsServices::statusLabel($service->status) }}</span></td>
                                    <td class="ao-mu-actions"><a href="{{ $urls['service']($service->id) }}" title="Open">+</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="ao-mu-none">No records found</td></tr>
                            @endforelse
                        @elseif ($band['title'] === 'Current Quotes')
                            @forelse ($band['rows'] as $quote)
                                <tr>
                                    <td>{{ $quote->id }}</td>
                                    <td class="ao-mu-left">{{ $quote->subject }}</td>
                                    <td>{{ \Carbon\Carbon::parse($quote->created_at)->format('m/d/Y') }}</td>
                                    <td>—</td>
                                    <td>{{ $quote->valid_until ? \Carbon\Carbon::parse($quote->valid_until)->format('m/d/Y') : '-' }}</td>
                                    <td>{{ ucfirst($quote->status) }}</td>
                                    <td></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="ao-mu-none">No records found</td></tr>
                            @endforelse
                        @else
                            <tr><td colspan="8" class="ao-mu-none">No records found</td></tr>
                        @endif
                    </tbody>
                </table>

                @php $bandCount = count($band['rows']); @endphp
                <div class="ao-cs-band-foot">
                    <span>Show
                        <select disabled><option>50</option></select>
                        entries
                    </span>
                    <span>Showing {{ $bandCount > 0 ? 1 : 0 }} to {{ $bandCount }} of {{ $bandCount }} entries</span>
                    <span class="ao-cs-band-pages">
                        <button type="button" disabled>Previous</button>
                        <i>1</i>
                        <button type="button" disabled>Next</button>
                    </span>
                </div>
            </div>
        @endforeach

        {{-- The reference's bulk row. Disabled: bulk invoicing and bulk deletion have no
             backend here, and a live-looking button that destroys nothing (or worse,
             something) would be the wrong kind of faithful. --}}
        <div class="ao-cs-selected">
            With Selected:
            <button type="button" disabled title="Not available">&#8635; Invoice Selected Items</button>
            <button type="button" class="ao-cs-danger" disabled title="Not available">&#128465; Delete Selected Items</button>
        </div>

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
                        <label class="ao-anc-row"><span>Client Group</span><select><option>None</option></select></label>
                    </div>
                    <div class="ao-anc-col">
                        <label class="ao-anc-row"><span>Address 1</span><input type="text" wire:model="pf.address" placeholder="123 Market Street"></label>
                        <label class="ao-anc-row">
                            <span>Address 2</span>
                            <span class="ao-anc-field"><input type="text" wire:model="pf.address2" placeholder="Suite 400"><i>(Optional)</i></span>
                        </label>
                        <label class="ao-anc-row"><span>City</span><input type="text" wire:model="pf.city" placeholder="San Francisco"></label>
                        <label class="ao-anc-row"><span>State/Region</span><input type="text" wire:model="pf.state" placeholder="—"></label>
                        <label class="ao-anc-row"><span>Postcode</span><input type="text" wire:model="pf.zip" placeholder="94105"></label>
                        <label class="ao-anc-row"><span>Country</span><input type="text" wire:model="pf.country" placeholder="United States"></label>
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
                            'domain' => 'Domain Emails - Registration/Transfer Confirmation & Renewal Notices',
                            'affiliate' => 'Affiliate Emails - Receive Affiliate Notifications',
                        ] as $key => $label)
                            <label><input type="checkbox" wire:model="pfPrefs.{{ $key }}"> {{ $label }}</label>
                        @endforeach
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
            </div>
        </form>

    @elseif ($tab === 'quotes')
        <div class="ao-cs-band">
            <h4>Quotes</h4>
            <table class="ao-mu-grid">
                <thead>
                    <tr><th>ID</th><th>Subject</th><th>Date</th><th>Valid Until Date</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($rows as $quote)
                        <tr>
                            <td>{{ $quote->id }}</td>
                            <td class="ao-mu-left">{{ $quote->subject }}</td>
                            <td>{{ \Carbon\Carbon::parse($quote->created_at)->format('m/d/Y') }}</td>
                            <td>{{ $quote->valid_until ? \Carbon\Carbon::parse($quote->valid_until)->format('m/d/Y') : '-' }}</td>
                            <td>{{ ucfirst($quote->status) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="ao-mu-none">No Records Found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    @elseif ($tab === 'notes')
        {{-- The reference's Notes tab — same note the Summary panel edits, full width. --}}
        <div class="ao-cp ao-cp-wide">
            <h3>Admin Notes</h3>
            <div class="ao-cp-body">
                <textarea class="ao-cp-notes" rows="10" wire:model="adminNotes" placeholder="Notes for staff only — the client never sees these"></textarea>
                <button type="button" class="ao-find-adv ao-cp-notes-save" wire:click="saveNotes">Submit</button>
            </div>
        </div>

    @else
        {{-- Every other tab is one list of one thing. `adminops::pages.client-tab` renders
             whichever it is, so a new tab is a case there rather than another block here. --}}
        @include('adminops::pages.client-tab', ['tab' => $tab, 'rows' => $rows, 'urls' => $urls])
    @endif

    </div>
</x-filament-panels::page>
