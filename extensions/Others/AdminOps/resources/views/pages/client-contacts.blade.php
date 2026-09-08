{{--
    The reference's Contacts tab: a select of the people on this account whose last option
    is Add New, then one form over the picked contact, and the Email Notifications ticks
    beneath it.

    Backed by ClientTools' `ext_ct_contacts`, which is the same record the client edits from
    their own Contacts page — so a change here is a change they see, not a second copy.
--}}
@if (!$hasContacts)
    <x-filament::section heading="Contacts">
        <p class="ao-empty">
            Contacts are part of the Client Tools extension, which is not installed on this
            deployment. Enable it under Extensions and the people on an account appear here.
        </p>
    </x-filament::section>
@else
    <div class="ao-cc">
        <div class="ao-cc-pick">
            <label for="cc-pick">Contacts:</label>
            <select id="cc-pick" wire:model.live="contact">
                @foreach ($rows as $row)
                    <option value="{{ $row->id }}">{{ $row->name }} - {{ $row->email }}</option>
                @endforeach
                <option value="">Add New</option>
            </select>
        </div>

        <form wire:submit.prevent="saveContact">
            <div class="ao-anc-card ao-cc-grid">
                <div class="ao-cc-col">
                    <label class="ao-anc-row">
                        <span>First Name</span>
                        <input type="text" wire:model="contactForm.first_name" maxlength="255">
                    </label>
                    <label class="ao-anc-row">
                        <span>Last Name</span>
                        <input type="text" wire:model="contactForm.last_name" maxlength="255">
                    </label>
                    <label class="ao-anc-row">
                        <span>Company Name <i>(Optional)</i></span>
                        <input type="text" wire:model="contactForm.company_name" maxlength="255">
                    </label>
                    <label class="ao-anc-row">
                        <span>Email Address</span>
                        <input type="email" wire:model="contactForm.email" maxlength="255">
                    </label>
                </div>

                <div class="ao-cc-col">
                    <label class="ao-anc-row">
                        <span>Address 1</span>
                        <input type="text" wire:model="contactForm.address" maxlength="255">
                    </label>
                    <label class="ao-anc-row">
                        <span>Address 2 <i>(Optional)</i></span>
                        <input type="text" wire:model="contactForm.address2" maxlength="255">
                    </label>
                    <label class="ao-anc-row">
                        <span>City</span>
                        <input type="text" wire:model="contactForm.city" maxlength="255">
                    </label>
                    <label class="ao-anc-row">
                        <span>State/Region</span>
                        <input type="text" wire:model="contactForm.state" maxlength="255">
                    </label>
                    <label class="ao-anc-row">
                        <span>Postcode</span>
                        <input type="text" wire:model="contactForm.zip" maxlength="32">
                    </label>
                    <label class="ao-anc-row">
                        <span>Country</span>
                        <select wire:model="contactForm.country">
                            <option value="">Select a country</option>
                            @foreach ($countries as $code => $name)
                                <option value="{{ $code }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="ao-anc-row">
                        <span>Phone Number</span>
                        <input type="text" wire:model="contactForm.phone" maxlength="64">
                    </label>
                </div>
            </div>

            <div class="ao-anc-card">
                <div class="ao-anc-row">
                    <span>Email Notifications</span>
                    <span class="ao-ep-radios"
                        x-data="{ all() { this.$root.querySelectorAll('input[type=checkbox]').forEach(c => { if (!c.checked) c.click(); }); } }">
                        @foreach ([
                            'general' => 'General Emails - All account related emails',
                            'invoice' => 'Invoice Emails - New Invoices, Reminders, & Overdue Notices',
                            'support' => 'Support Emails - Receive a copy of all Support Ticket Communications',
                            'product' => 'Product Emails - Welcome Emails, Suspensions & Other Lifecycle Notifications',
                            'domain' => 'Domain Emails - Registration/Transfer Confirmation & Renewal Notices',
                        ] as $key => $label)
                            <label class="ao-check">
                                <input type="checkbox" value="{{ $key }}" wire:model="contactPrefs">
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                        {{-- The reference's sixth row. Affiliate notices go to the account
                             holder, not to a contact, so there is nothing to tick. --}}
                        <label class="ao-check ao-gs-off">
                            <input type="checkbox" disabled title="Affiliate notices go to the account holder rather than to a contact">
                            <span>Affiliate Emails - Receive Affiliate Notifications</span>
                        </label>
                        <button type="button" class="ao-link ao-cc-all" @click="all()">Check All</button>
                    </span>
                </div>
            </div>

            @if ($errors->any())
                <ul class="ao-anc-errors">
                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            @endif

            <div class="ao-pr-center ao-cpg-actions">
                <button type="submit" class="ao-find-go">Save Changes</button>
                <button type="button" class="ao-pg-btn" wire:click="updatedContact">Cancel Changes</button>
            </div>

            @if ($contact !== '')
                <p class="ao-pr-center">
                    <button type="button" class="ao-cc-delete" wire:click="$set('confirmingContactDelete', true)">Delete</button>
                </p>
            @endif
        </form>
    </div>

    @if ($confirmingContactDelete)
        <div class="ao-mud-overlay" wire:click.self="$set('confirmingContactDelete', false)">
            <div class="ao-mud ao-mud-sm" role="alertdialog" aria-modal="true">
                <div class="ao-mud-head">
                    Are you sure?
                    <button type="button" wire:click="$set('confirmingContactDelete', false)" aria-label="Close">&times;</button>
                </div>
                <div class="ao-mud-text">
                    <p>Delete this contact?</p>
                    <p>If they can sign in to the account, that access goes with them.</p>
                </div>
                <div class="ao-mud-foot ao-mud-foot-only-right">
                    <span class="ao-mud-foot-right">
                        <button type="button" class="ao-mud-close" wire:click="$set('confirmingContactDelete', false)">Cancel</button>
                        <button type="button" class="ao-mud-delete" wire:click="deleteContact">OK</button>
                    </span>
                </div>
            </div>
        </div>
    @endif
@endif
