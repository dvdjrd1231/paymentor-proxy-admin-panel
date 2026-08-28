{{--
    Add New Client, to the reference screenshot: the two-column zebra form, labels on the
    right edge of their column, the store's custom properties underneath, Add Client at the
    end. Property fields come from `custom_properties`, so the CPF/CNPJ rows of the
    screenshot are here because the store defines them — not because they are hard-coded.
--}}
<x-filament-panels::page>
    <form class="ao-mu ao-anc" wire:submit.prevent="create">
        {{-- The reference's bordered card around the whole form; the send-message line and
             the button sit below it, as they do there. --}}
        <div class="ao-anc-card">
        <div class="ao-anc-cols">
            <div class="ao-anc-col">
                <label class="ao-anc-row">
                    <span>First Name</span>
                    <input type="text" wire:model="first_name" required>
                </label>
                <label class="ao-anc-row">
                    <span>Last Name</span>
                    <input type="text" wire:model="last_name" required>
                </label>
                @isset($fixed['company_name'])
                    <label class="ao-anc-row">
                        <span>Company Name</span>
                        <span class="ao-anc-field">
                            <input type="text" wire:model="props.company_name">
                            <i>(Optional)</i>
                        </span>
                    </label>
                @endisset
                <label class="ao-anc-row">
                    <span>Email Address</span>
                    <input type="email" wire:model="email" required>
                </label>
                <label class="ao-anc-row">
                    <span>Password</span>
                    <span class="ao-anc-field">
                        <input type="text" wire:model="password" data-ao-password required>
                        <button type="button" class="ao-anc-generate" data-ao-generate>Generate Password</button>
                    </span>
                </label>
                {{-- Two blank striped rows, as the reference has them — they are what lines
                     Language up with Payment Method across the two columns. --}}
                <div class="ao-anc-row" aria-hidden="true"><span></span><span></span></div>
                <div class="ao-anc-row" aria-hidden="true"><span></span><span></span></div>

                <label class="ao-anc-row">
                    <span>Language</span>
                    <select><option>Default</option></select>
                </label>
                <label class="ao-anc-row">
                    <span>Status</span>
                    <select><option>Active</option></select>
                </label>
                <label class="ao-anc-row">
                    <span>Client Group</span>
                    <select><option>None</option></select>
                </label>
            </div>

            <div class="ao-anc-col">
                @foreach ([['address', 'Address 1'], ['address2', 'Address 2'], ['city', 'City'], ['state', 'State/Region'], ['zip', 'Postcode']] as [$key, $label])
                    @isset($fixed[$key])
                        <label class="ao-anc-row">
                            <span>{{ $label }}</span>
                            <span class="ao-anc-field">
                                {{-- The reference defaults State/Region to "—"; the store's
                                     property is free text, so the dash is the placeholder. --}}
                                <input type="text" wire:model="props.{{ $key }}"
                                    @if ($key === 'state') placeholder="—" @endif
                                    @if ($fixed[$key]->required) required @endif>
                                @unless ($fixed[$key]->required)
                                    <i>(Optional)</i>
                                @endunless
                            </span>
                        </label>
                    @endisset
                @endforeach

                @isset($fixed['country'])
                    <label class="ao-anc-row">
                        <span>Country</span>
                        <select wire:model="props.country" required>
                            <option value="">—</option>
                            @foreach ($fixed['country']->allowed_values ?? [] as $value => $label)
                                <option value="{{ is_int($value) ? $label : $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                @endisset

                @isset($fixed['phone'])
                    <label class="ao-anc-row">
                        <span>Phone Number</span>
                        <input type="text" wire:model="props.phone" placeholder="+1 201-555-0123" required>
                    </label>
                @endisset

                <label class="ao-anc-row">
                    <span>Payment Method</span>
                    <select><option>Select to Change Default</option></select>
                </label>
                <label class="ao-anc-row">
                    <span>Billing Contact</span>
                    <select><option>Default</option></select>
                </label>
                <label class="ao-anc-row">
                    <span>Currency</span>
                    <select wire:model="currency">
                        @foreach ($currencies as $code)
                            <option value="{{ $code }}">{{ $code }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>

        @if ($extras->isNotEmpty())
            <div class="ao-anc-cols">
                <div class="ao-anc-col">
                    @foreach ($extras->values()->filter(fn ($p, $i) => $i % 2 === 0) as $property)
                        <label class="ao-anc-row">
                            <span>{{ $property->name }}</span>
                            @if ($property->type === 'checkbox')
                                <span class="ao-anc-field"><input type="checkbox" wire:model="props.{{ $property->key }}" value="1"></span>
                            @else
                                <input type="text" wire:model="props.{{ $property->key }}">
                            @endif
                        </label>
                    @endforeach
                </div>
                <div class="ao-anc-col">
                    @foreach ($extras->values()->filter(fn ($p, $i) => $i % 2 === 1) as $property)
                        <label class="ao-anc-row">
                            <span>{{ $property->name }}</span>
                            @if ($property->type === 'checkbox')
                                <span class="ao-anc-field"><input type="checkbox" wire:model="props.{{ $property->key }}" value="1"></span>
                            @else
                                <input type="text" wire:model="props.{{ $property->key }}">
                            @endif
                        </label>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- The reference's full-width rows under the columns: notifications, settings,
             owner, notes — each stored as real properties on the new profile. --}}
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
                    <label><input type="checkbox" wire:model="prefs.{{ $key }}" data-ao-pref> {{ $label }}</label>
                @endforeach
                <button type="button" class="ao-anc-checkall" data-ao-check-all-prefs>Check All</button>
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
                        <input type="checkbox" wire:model="settings.{{ $key }}">
                        <i aria-hidden="true"></i>
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>

        <div class="ao-anc-row ao-anc-row-wide ao-anc-grey">
            <span>Owner</span>
            <div class="ao-anc-checks">
                <label><input type="radio" checked> Create a new user.</label>
                {{-- Paymenter has no sub-account model to associate with; disabled says so
                     rather than pretending. --}}
                <label class="ao-anc-dim"><input type="radio" disabled title="Not available"> Associate with an existing user.</label>
            </div>
        </div>

        <div class="ao-anc-row ao-anc-row-wide">
            <span>Admin Notes</span>
            <textarea class="ao-cp-notes" rows="4" wire:model="notes"></textarea>
        </div>

        @if ($errors->any())
            <div class="ao-anc-errors">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        </div>{{-- /ao-anc-card --}}

        <div class="ao-anc-send">
            <label><input type="checkbox" wire:model="sendWelcome" checked> Check to send a New Account Information Message</label>
        </div>

        <div class="ao-anc-submit">
            <button type="submit" class="ao-find-go">Add Client</button>
        </div>
    </form>

    <script>
        (() => {
            const root = document.currentScript.closest('.fi-page') ?? document;

            root.addEventListener('click', (event) => {
                if (event.target.closest('[data-ao-check-all-prefs]')) {
                    for (const box of root.querySelectorAll('[data-ao-pref]')) {
                        if (!box.checked) {
                            box.checked = true;
                            // 'change', not 'input': Livewire binds checkboxes on change,
                            // so an input event ticks the box on screen and nowhere else —
                            // found when a driven toggle flip never reached the server.
                            box.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    }

                    return;
                }

                if (!event.target.closest('[data-ao-generate]')) return;

                const field = root.querySelector('[data-ao-password]');
                const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!#%+';
                const bytes = crypto.getRandomValues(new Uint8Array(14));
                field.value = [...bytes].map((b) => alphabet[b % alphabet.length]).join('');
                field.dispatchEvent(new Event('input', { bubbles: true }));
            });
        })();
    </script>
</x-filament-panels::page>
