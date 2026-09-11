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
                    <input type="text" wire:model="first_name" placeholder="John" required>
                </label>
                <label class="ao-anc-row">
                    <span>Last Name</span>
                    <input type="text" wire:model="last_name" placeholder="Doe" required>
                </label>
                @isset($fixed['company_name'])
                    <label class="ao-anc-row">
                        <span>Company Name</span>
                        <span class="ao-anc-field">
                            <input type="text" wire:model="props.company_name" placeholder="Acme Technologies, Inc.">
                            <i>(Optional)</i>
                        </span>
                    </label>
                @endisset
                <label class="ao-anc-row">
                    <span>Email Address</span>
                    <input type="email" class="ao-aw-email" wire:model="email" placeholder="user@example.com" required>
                </label>
                <label class="ao-anc-row">
                    <span>Password</span>
                    <span class="ao-anc-field">
                        <input type="text" class="ao-aw-sm" wire:model="password" placeholder="Minimum 8 characters" data-ao-password required>
                        <button type="button" class="ao-anc-generate" data-ao-generate>Generate Password</button>
                    </span>
                </label>
                {{-- Two blank striped rows, as the reference has them — they are what lines
                     Language up with Payment Method across the two columns. --}}
                <div class="ao-anc-row" aria-hidden="true"><span></span><span></span></div>
                <div class="ao-anc-row" aria-hidden="true"><span></span><span></span></div>

                <label class="ao-anc-row">
                    <span>Language</span>
                    <select class="ao-aw-lang" wire:model="language">
                        <option value="">Default</option>
                        @foreach ($languages as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ao-anc-row">
                    <span>Status</span>
                    <select class="ao-aw-tiny"><option>Active</option></select>
                </label>
                <label class="ao-anc-row">
                    <span>Client Group</span>
                    <select class="ao-aw-tiny"><option>None</option></select>
                </label>
            </div>

            <div class="ao-anc-col">
                @foreach ([['address', 'Address 1', '123 Market Street'], ['address2', 'Address 2', 'Suite 400'], ['city', 'City', 'San Francisco'], ['state', 'State/Region', '—'], ['zip', 'Postcode', '94105']] as [$key, $label, $hint])
                    @isset($fixed[$key])
                        <label class="ao-anc-row">
                            <span>{{ $label }}</span>
                            <span class="ao-anc-field">
                                {{-- State/Region is picked from the chosen country's own
                                     subdivisions, as the reference does. Countries we hold
                                     no list for keep the free-text box: a half-remembered
                                     list would put wrong names in front of the typist.
                                     Keyed by country so the morph re-reads the options. --}}
                                @if ($key === 'state' && $regions)
                                    <select class="ao-aw-state" wire:model="props.state" wire:key="state-{{ $props['country'] ?? '' }}"
                                        @if ($fixed[$key]->required) required @endif>
                                        <option value="">—</option>
                                        @foreach ($regions as $region)
                                            <option value="{{ $region }}">{{ $region }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="text" class="{{ $key === 'zip' ? 'ao-aw-sm' : ($key === 'state' ? 'ao-aw-state' : '') }}"
                                        wire:model="props.{{ $key }}" placeholder="{{ $hint }}"
                                        @if ($fixed[$key]->required) required @endif>
                                @endif
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
                        {{-- Live: the Brazil-only registry fields below fold in and out
                             with this choice. --}}
                        <select class="ao-aw-country" wire:model.live="props.country" required>
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
                        {{-- The prefix follows the Country pick above — country is live,
                             so choosing one redraws the flag and dial code here. --}}
                        <span class="ao-anc-field ao-anc-phone">
                            @if ($phoneFlag || $phoneDial)
                                <i class="ao-anc-dial">{{ $phoneFlag }} {{ $phoneDial }} &#9662;</i>
                            @endif
                            <input type="text" class="ao-aw-phone" wire:model="props.phone"
                                placeholder="{{ $phoneDial ? '201-555-0123' : '+1 201-555-0123' }}" required>
                        </span>
                    </label>
                @endisset

                <label class="ao-anc-row">
                    <span>Payment Method</span>
                    <select class="ao-aw-country" wire:model="paymentMethod">
                        <option value="">Select to Change Default</option>
                        @foreach ($gateways as $gateway)
                            <option value="{{ $gateway }}">{{ $gateway }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ao-anc-row">
                    <span>Billing Contact</span>
                    <select class="ao-aw-tiny"><option>Default</option></select>
                </label>
                <label class="ao-anc-row">
                    <span>Currency</span>
                    <select class="ao-aw-cur" wire:model="currency">
                        @foreach ($currencies as $code)
                            <option value="{{ $code }}">{{ $code }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>

        {{-- Brazil's registry block. A tax document there is issued to one of two kinds of
             person, and which one decides the whole set: a Pessoa Física is identified by
             CPF (with the RG alongside it), a Pessoa Jurídica by CNPJ, plus the state and
             municipal registrations. Showing both sets at once asked companies for a CPF,
             which is the wrong question — so the selector comes first and the rest follows
             from it. Everything here is live: nothing is submitted to find out. --}}
        @if (in_array('person_type', $brazilFields, true))
            <div class="ao-anc-brazil">
                <div class="ao-anc-brazil-head">
                    Brazilian Registration
                    <i>Required for issuing tax documents in Brazil</i>
                </div>

                <div class="ao-anc-cols">
                    <div class="ao-anc-col">
                        <label class="ao-anc-row">
                            <span>{{ $brazil['person_type']->name ?? 'Person Type' }}</span>
                            <select wire:model.live="props.person_type" required>
                                <option value="">— Select —</option>
                                @foreach ($brazil['person_type']->allowed_values ?? [] as $value => $label)
                                    <option value="{{ is_int($value) ? $label : $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        @foreach (['cpf', 'cnpj', 'trade_name'] as $key)
                            @if (in_array($key, $brazilFields, true) && isset($brazil[$key]))
                                <label class="ao-anc-row">
                                    <span>{{ $brazil[$key]->name }}</span>
                                    <span class="ao-anc-field">
                                        <input type="text" wire:model="props.{{ $key }}"
                                            placeholder="{{ $key === 'cpf' ? '000.000.000-00' : ($key === 'cnpj' ? '00.000.000/0000-00' : $brazil[$key]->name) }}"
                                            @if (in_array($key, ['cpf', 'cnpj'], true)) required @endif>
                                        @unless (in_array($key, ['cpf', 'cnpj'], true))
                                            <i>(Optional)</i>
                                        @endunless
                                    </span>
                                </label>
                            @endif
                        @endforeach
                    </div>

                    <div class="ao-anc-col">
                        @if (in_array('rg', $brazilFields, true) && isset($brazil['rg']))
                            <label class="ao-anc-row">
                                <span>{{ $brazil['rg']->name }}</span>
                                <span class="ao-anc-field">
                                    <input type="text" wire:model="props.rg" placeholder="Identity document">
                                    <i>(Optional)</i>
                                </span>
                            </label>
                        @endif

                        @if (in_array('state_registration', $brazilFields, true) && isset($brazil['state_registration']))
                            <label class="ao-anc-row">
                                <span>{{ $brazil['state_registration']->name }}</span>
                                {{-- Not mandatory, but not simply blank either: a company
                                     either states its IE or declares itself exempt, and
                                     ticking Isento is what writes ISENTO on the invoice. --}}
                                <span class="ao-anc-field">
                                    <input type="text" wire:model="props.state_registration"
                                        placeholder="Inscrição Estadual"
                                        @disabled($isExempt)>
                                    <label class="ao-anc-inline">
                                        <input type="checkbox" wire:model.live="props.state_registration_exempt" value="1">
                                        Isento
                                    </label>
                                </span>
                            </label>
                        @endif

                        @if (in_array('municipal_registration', $brazilFields, true) && isset($brazil['municipal_registration']))
                            <label class="ao-anc-row">
                                <span>{{ $brazil['municipal_registration']->name }}</span>
                                <span class="ao-anc-field">
                                    <input type="text" wire:model="props.municipal_registration"
                                        placeholder="Inscrição Municipal">
                                    <i>(Optional)</i>
                                </span>
                            </label>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        @if ($extras->isNotEmpty())
            <div class="ao-anc-cols">
                <div class="ao-anc-col">
                    @foreach ($extras->values()->filter(fn ($p, $i) => $i % 2 === 0) as $property)
                        <label class="ao-anc-row">
                            <span>{{ $property->name }}</span>
                            @if ($property->type === 'checkbox')
                                <span class="ao-anc-field"><input type="checkbox" wire:model="props.{{ $property->key }}" value="1"></span>
                            @else
                                <input type="text" wire:model="props.{{ $property->key }}" placeholder="{{ $property->name }}">
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
                                <input type="text" wire:model="props.{{ $property->key }}" placeholder="{{ $property->name }}">
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
            <textarea class="ao-cp-notes" rows="4" wire:model="notes" placeholder="Notes for staff only — the client never sees these"></textarea>
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
