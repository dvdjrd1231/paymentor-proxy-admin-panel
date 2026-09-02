{{--
    Create New Quote, to the reference screenshots: General Information, the button row,
    Client Information with the existing/new radio, the Line Items grid with discount and
    taxed, then the three notes boxes and the button row again.
--}}
<x-filament-panels::page>
    <form class="ao-mu ao-cq" wire:submit.prevent="save">
        <h4 class="ao-ano-heading">General Information</h4>
        <div class="ao-anc-card">
            <div class="ao-cq-general">
                <label class="ao-anc-row">
                    <span>Subject</span>
                    <input type="text" wire:model="subject" placeholder="Proxy plan proposal" required>
                </label>
                <label class="ao-anc-row">
                    <span>Stage</span>
                    <select wire:model="stage">
                        @foreach (\Paymenter\Extensions\Others\AdminOps\Admin\Pages\CreateQuote::STAGES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ao-anc-row">
                    <span>Date Created</span>
                    <span class="ao-anc-field">{{ ($quote?->created_at ?? now())->format('m/d/Y') }}</span>
                </label>
                <label class="ao-anc-row">
                    <span>Valid Until</span>
                    <input type="date" wire:model="validUntil">
                </label>
            </div>
        </div>

        {{-- The reference's button row, above and below. --}}
        <div class="ao-cq-buttons">
            <button type="submit" class="ao-find-go">Save Changes</button>
            <button type="button" wire:click="duplicate" @disabled(!$quote)>Duplicate</button>
            @if ($pdfUrl)
                <a href="{{ $pdfUrl }}?inline=1" target="_blank" rel="noopener">Printable Version</a>
                <a href="{{ $pdfUrl }}?inline=1" target="_blank" rel="noopener">View PDF</a>
                <a href="{{ $pdfUrl }}">Download PDF</a>
            @else
                <button type="button" disabled title="Save the quote first">Printable Version</button>
                <button type="button" disabled title="Save the quote first">View PDF</button>
                <button type="button" disabled title="Save the quote first">Download PDF</button>
            @endif
            <button type="button" wire:click="emailQuote" @disabled(!$quote)>Email as PDF</button>
            <button type="button" wire:click="convertToInvoice" @disabled(!$quote || $quote->invoice_id)>Convert to Invoice</button>
            <button type="button" class="ao-cq-delete" wire:click="$set('confirmingDelete', true)" @disabled(!$quote)>Delete</button>
        </div>

        <h4 class="ao-ano-heading">Client Information</h4>
        <label class="ao-cq-radio">
            <input type="radio" value="existing" wire:model.live="clientMode"> <b>Quote for existing client:</b>
        </label>
        <select class="ao-cq-client" wire:model="userId" @disabled($clientMode !== 'existing')>
            <option value="">Start Typing to Search Clients</option>
            @foreach ($clients as $client)
                <option value="{{ $client->id }}">
                    {{ trim($client->first_name . ' ' . $client->last_name) ?: $client->email }} - #{{ $client->id }}
                </option>
            @endforeach
        </select>

        @unless ($quote)
            <label class="ao-cq-radio">
                <input type="radio" value="new" wire:model.live="clientMode"> <b>Quote for new client</b>
            </label>

            @if ($clientMode === 'new')
                <div class="ao-anc-card">
                    <div class="ao-anc-cols">
                        <div class="ao-anc-col">
                            <label class="ao-anc-row"><span>First Name</span><input type="text" wire:model="nc.first_name" placeholder="John"></label>
                            <label class="ao-anc-row"><span>Last Name</span><input type="text" wire:model="nc.last_name" placeholder="Doe"></label>
                            <label class="ao-anc-row"><span>Company Name</span><input type="text" wire:model="nc.company_name" placeholder="Acme Technologies, Inc."></label>
                            <label class="ao-anc-row"><span>Email Address</span><input type="email" wire:model="nc.email" placeholder="user@example.com"></label>
                            <label class="ao-anc-row"><span>Phone Number</span><input type="text" wire:model="nc.phone" placeholder="+1 201-555-0123"></label>
                            <label class="ao-anc-row">
                                <span>Currency</span>
                                <select wire:model="nc.currency">
                                    @foreach ($currencies as $code)
                                        <option value="{{ $code }}">{{ $code }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                        <div class="ao-anc-col">
                            <label class="ao-anc-row"><span>Address 1</span><input type="text" wire:model="nc.address" placeholder="123 Market Street"></label>
                            <label class="ao-anc-row"><span>Address 2</span><input type="text" wire:model="nc.address2" placeholder="Suite 400"></label>
                            <label class="ao-anc-row"><span>City</span><input type="text" wire:model="nc.city" placeholder="San Francisco"></label>
                            <label class="ao-anc-row"><span>State/Region</span><input type="text" wire:model="nc.state" placeholder="—"></label>
                            <label class="ao-anc-row"><span>Postcode</span><input type="text" wire:model="nc.zip" placeholder="94105"></label>
                            <label class="ao-anc-row">
                                <span>Country</span>
                                {{-- .live: the Brazilian block below folds in and out with
                                     this choice, the same as Add New Client's own. --}}
                                <select wire:model.live="nc.country">
                                    <option value="">—</option>
                                    @foreach ($countries as $name)
                                        <option value="{{ $name }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </div>

                    {{-- Issue #14: "the information for registering new customers in Brazil
                         also applies to new customers being registered via a quote" — the
                         same block Add New Client carries, because it is the same lead
                         becoming the same kind of user account either way. --}}
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
                                        <select wire:model.live="nc.person_type" required>
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
                                                    <input type="text" wire:model="nc.{{ $key }}"
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
                                                <input type="text" wire:model="nc.rg" placeholder="Identity document">
                                                <i>(Optional)</i>
                                            </span>
                                        </label>
                                    @endif

                                    @if (in_array('state_registration', $brazilFields, true) && isset($brazil['state_registration']))
                                        <label class="ao-anc-row">
                                            <span>{{ $brazil['state_registration']->name }}</span>
                                            <span class="ao-anc-field">
                                                <input type="text" wire:model="nc.state_registration"
                                                    placeholder="Inscrição Estadual" @disabled($isExempt)>
                                                <label class="ao-anc-inline">
                                                    <input type="checkbox" wire:model.live="nc.state_registration_exempt" value="1">
                                                    Isento
                                                </label>
                                            </span>
                                        </label>
                                    @endif

                                    @if (in_array('municipal_registration', $brazilFields, true) && isset($brazil['municipal_registration']))
                                        <label class="ao-anc-row">
                                            <span>{{ $brazil['municipal_registration']->name }}</span>
                                            <span class="ao-anc-field">
                                                <input type="text" wire:model="nc.municipal_registration"
                                                    placeholder="Inscrição Municipal">
                                                <i>(Optional)</i>
                                            </span>
                                        </label>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        @endunless

        <h4 class="ao-ano-heading">Line Items</h4>
        <table class="ao-mu-grid ao-cq-items">
            <thead>
                <tr>
                    <th class="ao-cq-qty">Qty</th>
                    <th>Description</th>
                    <th class="ao-cq-num">Unit Price</th>
                    <th class="ao-cq-num">Discount %</th>
                    <th class="ao-cq-num">Total</th>
                    <th class="ao-cq-taxed">Taxed</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $index => $item)
                    <tr>
                        <td><input type="number" min="1" wire:model.live="items.{{ $index }}.quantity"></td>
                        <td><input type="text" class="ao-cq-desc" wire:model="items.{{ $index }}.description" placeholder="Line item description"></td>
                        <td><input type="text" inputmode="decimal" wire:model.live="items.{{ $index }}.price" placeholder="0.00"></td>
                        <td><input type="text" inputmode="decimal" wire:model.live="items.{{ $index }}.discount" placeholder="0.00"></td>
                        <td class="ao-cq-total">${{ number_format($this->lineTotal($item), 2) }}</td>
                        <td class="ao-cq-taxed"><input type="checkbox" wire:model="items.{{ $index }}.taxed"></td>
                        <td class="ao-mu-actions">
                            <button type="button" class="ao-ano-remove" title="Remove line" wire:click="removeItem({{ $index }})">&times;</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="ao-cq-under">
            <span>
                <button type="button" class="ao-cq-addline" wire:click="addItem">+ Add Line</button>
                <label class="ao-cq-pre">
                    Add a Predefined Product
                    <select wire:change="addProduct($event.target.value)">
                        <option value="">Pick a product…</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}">{{ $product->category?->name }} - {{ $product->name }}</option>
                        @endforeach
                    </select>
                </label>
            </span>
            <table class="ao-cq-sums">
                <tr><td>Sub Total</td><td>${{ number_format($this->subTotal(), 2) }} {{ $quote->currency_code ?? config('settings.default_currency', 'USD') }}</td></tr>
                <tr class="ao-cq-due"><td>Total Due</td><td>${{ number_format($this->subTotal(), 2) }} {{ $quote->currency_code ?? config('settings.default_currency', 'USD') }}</td></tr>
            </table>
        </div>

        <h4 class="ao-ano-heading">Notes</h4>
        <div class="ao-anc-card ao-cq-notes">
            <label class="ao-anc-row">
                <span>Proposal Text<br><i>(Displayed at the Top of the Quote)</i></span>
                <textarea rows="4" wire:model="proposal" placeholder="Introduce the proposal — the client sees this first"></textarea>
            </label>
            <label class="ao-anc-row">
                <span>Customer Notes<br><i>(Displayed as a Footer to the Quote)</i></span>
                <textarea rows="4" wire:model="customerNotes" placeholder="Terms, delivery notes — shown under the line items"></textarea>
            </label>
            <label class="ao-anc-row">
                <span>Admin Only Notes<br><i>(Private Notes)</i></span>
                <textarea rows="4" wire:model="adminNotes" placeholder="Notes for staff only — the client never sees these"></textarea>
            </label>
        </div>

        @if ($errors->any())
            <ul class="ao-anc-errors">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <div class="ao-cq-buttons">
            <button type="submit" class="ao-find-go">Save Changes</button>
            <button type="button" wire:click="duplicate" @disabled(!$quote)>Duplicate</button>
            @if ($pdfUrl)
                <a href="{{ $pdfUrl }}?inline=1" target="_blank" rel="noopener">Printable Version</a>
                <a href="{{ $pdfUrl }}?inline=1" target="_blank" rel="noopener">View PDF</a>
                <a href="{{ $pdfUrl }}">Download PDF</a>
            @endif
            <button type="button" wire:click="emailQuote" @disabled(!$quote)>Email as PDF</button>
            <button type="button" wire:click="convertToInvoice" @disabled(!$quote || $quote->invoice_id)>Convert to Invoice</button>
            <button type="button" class="ao-cq-delete" wire:click="$set('confirmingDelete', true)" @disabled(!$quote)>Delete</button>
        </div>

        @if ($confirmingDelete)
            <div class="ao-mud-overlay" wire:click.self="$set('confirmingDelete', false)">
                <div class="ao-mud ao-mud-sm" role="alertdialog" aria-modal="true">
                    <div class="ao-mud-head">
                        Are you sure?
                        <button type="button" wire:click="$set('confirmingDelete', false)" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text">
                        <p>Are you sure you wish to delete this quote?</p>
                        <p>A quote that became an invoice cannot be deleted — the record stays.</p>
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="$set('confirmingDelete', false)">Cancel</button>
                            <button type="button" class="ao-mud-delete" wire:click="deleteQuote">Delete</button>
                        </span>
                    </div>
                </div>
            </div>
        @endif
    </form>
</x-filament-panels::page>
