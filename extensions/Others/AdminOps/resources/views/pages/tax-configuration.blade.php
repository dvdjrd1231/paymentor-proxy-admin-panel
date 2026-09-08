{{--
    Tax Configuration, to the reference's screenshots: four tabs over one page. Tabs switch
    in the browser — every panel is built from data this component already holds, so none of
    them needs the server. See the page class for which controls are real and why the rest
    are drawn disabled rather than left out.
--}}
<x-filament-panels::page>
    <div class="ao-mu ao-ep" x-data="{ tab: @js($tab) }">
        <div class="ao-tx-tabs ao-ei-tabs">
            @foreach ([
                'general' => 'General Settings',
                'vat' => 'VAT Settings',
                'rules' => 'Tax Rules',
                'advanced' => 'Advanced Settings',
            ] as $key => $label)
                <button type="button" class="ao-mu-tab"
                    :class="{ 'ao-on': tab === '{{ $key }}' }"
                    @click="tab = '{{ $key }}'">{{ $label }}</button>
            @endforeach
        </div>

        {{-- ── General Settings ──────────────────────────────────────────────── --}}
        <div x-show="tab === 'general'" x-cloak>
            <form wire:submit.prevent="saveGeneral">
                <div class="ao-gs-card">
                    <div class="ao-gs-row">
                        <label class="ao-gs-label" for="tx-enabled">Tax Support</label>
                        <div class="ao-gs-field">
                            <label class="ao-check">
                                <input id="tx-enabled" type="checkbox" wire:model="taxEnabled">
                                <span>Enable tax billing</span>
                            </label>
                        </div>
                        <div class="ao-gs-hint">Toggle to enable tax billing functionality. With it off, no invoice carries tax whatever rules exist below.</div>
                    </div>

                    <div class="ao-gs-row">
                        <span class="ao-gs-label">Your Tax ID/VAT Number</span>
                        <div class="ao-gs-field ao-gs-off">
                            <input type="text" disabled title="Invoices carry the company block from General Settings, which has no tax-ID line">
                        </div>
                        <div class="ao-gs-hint">Not available: an invoice shows the company block set on General Settings, and that block has no tax-ID line to fill.</div>
                    </div>

                    <div class="ao-gs-row">
                        <span class="ao-gs-label">Customer Tax IDs/VAT Numbers</span>
                        <div class="ao-gs-field ao-gs-off">
                            <label class="ao-check">
                                <input type="checkbox" disabled title="A client record has no tax-number field to collect">
                                <span>Collect at signup and on the profile</span>
                            </label>
                        </div>
                        <div class="ao-gs-hint">Not available: a client record has no tax-number field, so there is nothing to ask for or store.</div>
                    </div>

                    <div class="ao-gs-row">
                        <span class="ao-gs-label">Taxation Type</span>
                        <div class="ao-gs-field ao-ep-radios">
                            <label class="ao-check">
                                <input type="radio" value="exclusive" wire:model="taxType">
                                <span>Exclusive Tax &mdash; prices are entered without tax</span>
                            </label>
                            <label class="ao-check">
                                <input type="radio" value="inclusive" wire:model="taxType">
                                <span>Inclusive Tax &mdash; prices are entered including tax</span>
                            </label>
                        </div>
                        <div class="ao-gs-hint">Choose how you want tax to be billed. This changes what the prices already entered on your products mean, so check them after changing it.</div>
                    </div>
                </div>

                {{-- The reference puts numbering on this tab and its paid-invoice twin on
                     the next. There is one scheme here, so it is shown once. --}}
                <div class="ao-gs-card">
                    <div class="ao-gs-row">
                        <label class="ao-gs-label" for="tx-custom">Custom Invoice Numbering</label>
                        <div class="ao-gs-field">
                            <label class="ao-check">
                                <input id="tx-custom" type="checkbox" wire:model.live="customNumbering">
                                <span>Check to enable Custom Invoice Number Formats for Generated Invoices</span>
                            </label>
                        </div>
                        <div class="ao-gs-hint">With this off, an invoice is known by its own number and nothing is reformatted.</div>
                    </div>

                    <div class="ao-gs-row">
                        <label class="ao-gs-label" for="tx-format">Custom Invoice Numbering Format</label>
                        <div class="ao-gs-field">
                            <input id="tx-format" type="text" wire:model="numberFormat" placeholder="{NUMBER}"
                                @disabled(!$customNumbering)>
                        </div>
                        <div class="ao-gs-hint">Available Tags: <code>&#123;YEAR&#125;</code> <code>&#123;MONTH&#125;</code> <code>&#123;DAY&#125;</code> <code>&#123;NUMBER&#125;</code></div>
                    </div>

                    <div class="ao-gs-row">
                        <label class="ao-gs-label" for="tx-next">Next Invoice Number</label>
                        <div class="ao-gs-field">
                            <input id="tx-next" type="number" min="1" class="ao-w-25" wire:model="nextNumber">
                        </div>
                        <div class="ao-gs-hint">The next invoice number that will be assigned.</div>
                    </div>

                    <div class="ao-gs-row">
                        <label class="ao-gs-label" for="tx-pad">Number Padding</label>
                        <div class="ao-gs-field">
                            <input id="tx-pad" type="number" min="0" max="12" class="ao-w-25" wire:model="numberPadding">
                        </div>
                        <div class="ao-gs-hint">Pad the number to this many digits — 5 turns 42 into 00042.</div>
                    </div>

                    <div class="ao-gs-row">
                        <span class="ao-gs-label">Auto Reset Numbering</span>
                        <div class="ao-gs-field ao-ep-radios ao-gs-off">
                            <label class="ao-check">
                                <input type="radio" checked disabled title="Numbers run on without resetting">
                                <span>Never</span>
                            </label>
                            <label class="ao-check">
                                <input type="radio" disabled title="Nothing here resets a counter on a schedule">
                                <span>Monthly</span>
                            </label>
                            <label class="ao-check">
                                <input type="radio" disabled title="Nothing here resets a counter on a schedule">
                                <span>Annually</span>
                            </label>
                        </div>
                        <div class="ao-gs-hint">Not available: the counter runs on. Putting <code>&#123;YEAR&#125;</code> in the format above keeps each year's invoices apart without restarting the number.</div>
                    </div>
                </div>

                @if ($errors->any())
                    <ul class="ao-anc-errors">
                        @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                    </ul>
                @endif

                <div class="ao-pr-center ao-cpg-actions">
                    <button type="submit" class="ao-find-go">Save</button>
                    <a class="ao-pg-btn" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\TaxConfiguration::getUrl() }}">Cancel</a>
                </div>
            </form>
        </div>

        {{-- ── VAT Settings ──────────────────────────────────────────────────── --}}
        <div x-show="tab === 'vat'" x-cloak>
            <div class="ao-anc-card">
                <div class="ao-anc-row">
                    <span>VAT Settings</span>
                    <span class="ao-ep-explain">
                        <p>VAT mode is a second scheme the reference runs beside its tax rules: a home
                            country, a validated VAT number per client, an exemption when that number
                            checks out, and a separate sequence of paid-invoice numbers to go with it.</p>
                        <p>None of it exists here. A client record has no VAT number to validate or
                            exempt against, there is no home country to compare one to, and invoices are
                            numbered once rather than again on payment — so every control on this tab
                            would have nothing to act on.</p>
                        <p>Charging different rates by country is done on <b>Tax Rules</b>, which is
                            where a per-country rate genuinely lives.</p>
                    </span>
                </div>
            </div>
        </div>

        {{-- ── Tax Rules ─────────────────────────────────────────────────────── --}}
        <div x-show="tab === 'rules'" x-cloak>
            @if ($canManage)
                <h3 class="ao-sub">Quick Add</h3>

                <form class="ao-gs-card" wire:submit.prevent="addRule">
                    <div class="ao-gs-row">
                        <label class="ao-gs-label" for="tx-rname">Name</label>
                        <div class="ao-gs-field"><input id="tx-rname" type="text" wire:model="rule.name" placeholder="Tax"></div>
                        <div class="ao-gs-hint">What this charge is called on the invoice.</div>
                    </div>

                    <div class="ao-gs-row">
                        <label class="ao-gs-label" for="tx-rrate">Tax Rate</label>
                        <div class="ao-gs-field ao-tx-rate">
                            <input id="tx-rrate" type="number" step="0.01" min="0" max="999.99" class="ao-w-25" wire:model="rule.rate">
                            <span>%</span>
                        </div>
                        <div class="ao-gs-hint">Supports up to 2 decimals.</div>
                    </div>

                    <div class="ao-gs-row">
                        <span class="ao-gs-label">Level</span>
                        <div class="ao-gs-field ao-gs-off">
                            <select disabled title="Rates here apply once; there is no second level to stack on top">
                                <option>Level 1</option>
                            </select>
                        </div>
                        <div class="ao-gs-hint">Not available: a rate applies once. There is no second level to stack on top of the first, so nothing can compound.</div>
                    </div>

                    <div class="ao-gs-row">
                        <label class="ao-gs-label" for="tx-rcountry">Country</label>
                        <div class="ao-gs-field">
                            <select id="tx-rcountry" wire:model="rule.country">
                                <option value="all">Apply Rule to All Countries</option>
                                @foreach ($countries as $code => $name)
                                    <option value="{{ $code }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ao-gs-hint">The country the rule applies to, matched against the client's own. One rate per country.</div>
                    </div>

                    <div class="ao-gs-row">
                        <span class="ao-gs-label">State/Region</span>
                        <div class="ao-gs-field ao-gs-off">
                            <label class="ao-check">
                                <input type="radio" checked disabled title="A rate is matched by country alone">
                                <span>Apply Rule to All States</span>
                            </label>
                        </div>
                        <div class="ao-gs-hint">Not available: a rate is matched by country alone, so a state cannot be told apart from the rest of its country.</div>
                    </div>

                    @if ($errors->any())
                        <ul class="ao-anc-errors">
                            @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                        </ul>
                    @endif

                    <div class="ao-pr-center ao-cpg-actions">
                        <button type="submit" class="ao-find-go">Add Rule</button>
                    </div>
                </form>
            @endif

            <h3 class="ao-sub">Level 1 Rules</h3>

            <table class="ao-mu-grid">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Country</th>
                        <th>State/Region</th>
                        <th>Tax Rate</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rates as $rate)
                        <tr>
                            <td class="ao-mu-left">
                                @if ($editUrl($rate))
                                    <a href="{{ $editUrl($rate) }}">{{ $rate->name }}</a>
                                @else
                                    {{ $rate->name }}
                                @endif
                            </td>
                            <td>{{ $countries[$rate->country] ?? 'All Countries' }}</td>
                            <td class="ao-cpg-muted">All States</td>
                            <td>{{ rtrim(rtrim(number_format((float) $rate->rate, 2, '.', ''), '0'), '.') }}%</td>
                            <td class="ao-mu-actions">
                                @if ($canManage)
                                    <button type="button" class="ao-mo-delete" title="Delete rule"
                                        wire:click="$set('confirming', {{ $rate->id }})">
                                        <x-filament::icon icon="ri-indeterminate-circle-fill" class="ao-mu-cell-icon ao-mu-icon-red" />
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="ao-mu-none">No Records Found</td></tr>
                    @endforelse
                </tbody>
            </table>

            <p class="ao-cp-note">
                Level 2 rules are the reference's way of charging a second tax on top of the first.
                A rate here applies once, so there is no second level and no table for it.
                @unless ($taxEnabled)
                    <b>Tax Support is currently off</b>, so none of these rules is applied to an invoice.
                @endunless
            </p>
        </div>

        {{-- ── Advanced Settings ─────────────────────────────────────────────── --}}
        <div x-show="tab === 'advanced'" x-cloak>
            <div class="ao-gs-card">
                <div class="ao-gs-row">
                    <span class="ao-gs-label">Taxed Items</span>
                    <div class="ao-gs-field ao-ep-radios ao-gs-off">
                        @foreach (['Domains', 'Billable Items', 'Late Fees', 'Custom Invoices'] as $item)
                            <label class="ao-check">
                                <input type="checkbox" checked disabled title="Every taxable line is taxed the same way">
                                <span>{{ $item }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="ao-gs-hint">Not available: a rule matched to the client's country applies to the whole invoice, so lines cannot be taxed one kind at a time.</div>
                </div>

                <div class="ao-gs-row">
                    <span class="ao-gs-label">Calculation Mode</span>
                    <div class="ao-gs-field ao-ep-radios ao-gs-off">
                        <label class="ao-check">
                            <input type="radio" checked disabled title="Tax is worked out from the invoice total">
                            <span>Calculate based on collective sum of the taxable line items</span>
                        </label>
                        <label class="ao-check">
                            <input type="radio" disabled title="There is no per-line tax to round separately">
                            <span>Calculate individually per line item</span>
                        </label>
                    </div>
                    <div class="ao-gs-hint">Not available: tax is worked out once from the invoice total, so there is no per-line figure to round separately.</div>
                </div>

                <div class="ao-gs-row">
                    <span class="ao-gs-label">Compound Tax</span>
                    <div class="ao-gs-field ao-gs-off">
                        <label class="ao-check">
                            <input type="checkbox" disabled title="There is no second level to compound onto the first">
                            <span>Enable level 2 taxes being applied to level 1 taxes</span>
                        </label>
                    </div>
                    <div class="ao-gs-hint">Not available: there is only one level of tax, so nothing can be charged on top of it.</div>
                </div>

                <div class="ao-gs-row">
                    <span class="ao-gs-label">Deduct Tax Amount</span>
                    <div class="ao-gs-field ao-gs-off">
                        <label class="ao-check">
                            <input type="checkbox" disabled title="An inclusive price already has the tax taken out of it">
                            <span>Deduct calculated tax amount when no tax rules are met</span>
                        </label>
                    </div>
                    <div class="ao-gs-hint">Not available: with Inclusive Tax the amount is already taken out of the price when a rule matches, and left in when none does.</div>
                </div>
            </div>
        </div>

        @if ($confirming)
            <div class="ao-mud-overlay" wire:click.self="$set('confirming', null)">
                <div class="ao-mud ao-mud-sm" role="alertdialog" aria-modal="true">
                    <div class="ao-mud-head">
                        Are you sure?
                        <button type="button" wire:click="$set('confirming', null)" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text">
                        <p>Delete this tax rule?</p>
                        <p>Invoices already raised keep the tax they were charged. Only new ones change.</p>
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="$set('confirming', null)">Cancel</button>
                            <button type="button" class="ao-mud-delete" wire:click="runDelete">OK</button>
                        </span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
