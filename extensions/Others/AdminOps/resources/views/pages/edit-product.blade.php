{{--
    Edit Product, to the reference's screens: the tab strip, then one card per tab with its
    own Save Changes. See EditProduct's docblock for the reference tabs this deployment has
    nothing behind (Free Domain, Cross-sells, Custom Fields, Other).
--}}
<x-filament-panels::page>
    {{-- Tabs switch in the browser, not on the server. --}}
    <div class="ao-mu ao-ep" x-data="{ tab: @js($tab) }">
        <div class="ao-ei-top">
            <div class="ao-tx-tabs ao-ei-tabs">
                @foreach ([
                    'details' => 'Details',
                    'pricing' => 'Pricing',
                    'module' => 'Module Settings',
                    'custom' => 'Custom Fields',
                    'options' => 'Configurable Options',
                    'upgrades' => 'Upgrades',
                    'crosssells' => 'Cross-sells',
                    'other' => 'Other',
                    'links' => 'Links',
                ] as $key => $label)
                    <button type="button" class="ao-mu-tab"
                        :class="{ 'ao-on': tab === '{{ $key }}' }"
                        @click="tab = '{{ $key }}'">{{ $label }}</button>
                @endforeach
            </div>

            <div class="ao-ei-tools">
                {{-- Only when there is one: a product with no group has no storefront
                     address, and a button pointing at nothing is worse than no button. --}}
                @if ($links['product'])
                    <a class="ao-pg-btn" href="{{ $links['product'] }}" target="_blank" rel="noopener">View on Store</a>
                @endif
                <a class="ao-pg-btn" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\Catalogue::getUrl() }}">&laquo; Back to List</a>
            </div>
        </div>

        {{-- ── Details ─────────────────────────────────────────────────────────
             Three columns — label, field, help — the same .ao-gs-row grid General
             Settings uses, because that is the shape the reference draws every one of
             these screens in. This was a two-column form with the help tucked under the
             label, which is why it did not read like the target. --}}
        <div x-show="tab === 'details'" x-cloak>
            <form wire:submit.prevent="saveDetails">
                <div class="ao-gs-card">
                    <div class="ao-gs-row">
                        <label class="ao-gs-label" for="ep-type">Product Type</label>
                        <div class="ao-gs-field">
                            <select id="ep-type" wire:model="extra.type">
                                @foreach (\Paymenter\Extensions\Others\AdminOps\Models\Meta::PRODUCT_TYPES as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ao-gs-hint">How the catalogue's Type column describes it.</div>
                    </div>

                    <div class="ao-gs-row">
                        <label class="ao-gs-label" for="ep-group">Product Group</label>
                        <div class="ao-gs-field">
                            <select id="ep-group" wire:model="form.category_id">
                                @foreach ($groups as $group)
                                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ao-gs-hint">The group this product is ordered from.</div>
                    </div>

                    <div class="ao-gs-row">
                        <label class="ao-gs-label" for="ep-name">Product Name</label>
                        <div class="ao-gs-field"><input id="ep-name" type="text" wire:model="form.name" required></div>
                        <div class="ao-gs-hint">The default display name for this product.</div>
                    </div>
                    @error('form.name') <p class="ao-anc-errors">{{ $message }}</p> @enderror

                    <div class="ao-gs-row">
                        <label class="ao-gs-label" for="ep-tagline">Product Tagline</label>
                        <div class="ao-gs-field"><input id="ep-tagline" type="text" wire:model="extra.tagline"></div>
                        <div class="ao-gs-hint">Shown under the name on the storefront.</div>
                    </div>

                    <div class="ao-gs-row">
                        <label class="ao-gs-label" for="ep-slug">URL</label>
                        <div class="ao-gs-field ao-ep-url">
                            <i>{{ $urlPrefix }}</i>
                            <input id="ep-slug" type="text" wire:model="form.slug">
                        </div>
                        <div class="ao-gs-hint">A friendly URL to use to link to this product.</div>
                    </div>
                    @error('form.slug') <p class="ao-anc-errors">{{ $message }}</p> @enderror

                    <div class="ao-gs-row ao-gs-row--tall">
                        <label class="ao-gs-label" for="ep-short">Product Short Description</label>
                        <div class="ao-gs-field"><textarea id="ep-short" rows="2" wire:model="extra.short_description"></textarea></div>
                        <div class="ao-gs-hint">We recommend limiting this description to 50 words.</div>
                    </div>

                    <div class="ao-gs-row ao-gs-row--tall">
                        <label class="ao-gs-label" for="ep-desc">Product Description</label>
                        <div class="ao-gs-field"><textarea id="ep-desc" rows="6" wire:model="form.description"></textarea></div>
                        <div class="ao-gs-hint">
                            You may use HTML in this field<br>
                            <code>&lt;br /&gt;</code> New line<br>
                            <code>&lt;strong&gt;Bold&lt;/strong&gt;</code> <b>Bold</b><br>
                            <code>&lt;em&gt;Italics&lt;/em&gt;</code> <i>Italics</i>
                        </div>
                    </div>

                    <div class="ao-gs-row">
                        <label class="ao-gs-label" for="ep-colour">Product Colour</label>
                        <div class="ao-gs-field"><input id="ep-colour" type="color" wire:model="extra.colour" class="ao-ep-colour"></div>
                        <div class="ao-gs-hint">Used where the storefront tints a product.</div>
                    </div>

                    <div class="ao-gs-row">
                        <label class="ao-gs-label" for="ep-welcome">Welcome Email</label>
                        <div class="ao-gs-field">
                            {{-- The templates that exist, not a free-text key: a mistyped key
                                 sends nothing and says nothing about why. --}}
                            <select id="ep-welcome" wire:model="form.email_template">
                                <option value="">None</option>
                                @foreach ($emailTemplates as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ao-gs-hint">Sent to the client when this product is set up.</div>
                    </div>

                    {{-- Both of the reference's remaining Details rows. Neither can act
                         here, and each says so rather than looking live: domains are off
                         on this deployment, and tax is decided by the client's country
                         through tax_rates, not per product. --}}
                    <div class="ao-gs-row">
                        <span class="ao-gs-label">Require Domain</span>
                        <div class="ao-gs-field">
                            <label class="ao-check ao-gs-off">
                                <input type="checkbox" disabled title="Domains are switched off on this deployment">
                                <span>Check to show domain registration options</span>
                            </label>
                        </div>
                        <div class="ao-gs-hint">Domains are switched off here — see <code>docs/10-disable-domains.md</code>.</div>
                    </div>

                    <div class="ao-gs-row">
                        <span class="ao-gs-label">Apply Tax</span>
                        <div class="ao-gs-field">
                            <label class="ao-check ao-gs-off">
                                <input type="checkbox" disabled title="Tax is decided by the client's country, not per product">
                                <span>Check to charge tax for this product</span>
                            </label>
                        </div>
                        <div class="ao-gs-hint">Tax comes from Tax Rates against the client's country, so it is not a per-product choice.</div>
                    </div>

                    <div class="ao-gs-row">
                        <span class="ao-gs-label">Stock Control</span>
                        <div class="ao-gs-field">
                            <label class="ao-check">
                                <input type="checkbox" wire:model.live="form.stock_enabled">
                                <span>Enable — Quantity in Stock:</span>
                            </label>
                            <input type="number" min="0" class="ao-ep-num" wire:model="form.stock" @disabled(!$form['stock_enabled'])>
                        </div>
                        <div class="ao-gs-hint">Unticked, the product is unlimited.</div>
                    </div>

                    <div class="ao-gs-row">
                        <span class="ao-gs-label">Featured</span>
                        <div class="ao-gs-field">
                            <label class="ao-check"><input type="checkbox" wire:model="extra.featured">
                                <span>Display this product more prominently on supported order forms</span></label>
                        </div>
                        <div class="ao-gs-hint"></div>
                    </div>

                    <div class="ao-gs-row">
                        <span class="ao-gs-label">Hidden</span>
                        <div class="ao-gs-field">
                            <label class="ao-check"><input type="checkbox" wire:model="form.hidden">
                                <span>Check to hide from order form</span></label>
                        </div>
                        <div class="ao-gs-hint">It stays orderable on its direct link.</div>
                    </div>

                    <div class="ao-gs-row">
                        <span class="ao-gs-label">Retired</span>
                        <div class="ao-gs-field">
                            <label class="ao-check"><input type="checkbox" wire:model="extra.retired">
                                <span>Check to hide from admin area product dropdown menus</span></label>
                        </div>
                        <div class="ao-gs-hint">Does not apply to services already on this product.</div>
                    </div>
                </div>

                <div class="ao-pr-center ao-cpg-actions">
                    <button type="submit" class="ao-find-go">Save Changes</button>
                    <a class="ao-pg-btn" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\EditProduct::getUrl(['record' => $product->id]) }}">Cancel Changes</a>
                </div>
            </form>
        </div>

        {{-- ── Pricing ─────────────────────────────────────────────────────────
             The reference's grid: cycles across the top, Setup Fee / Price / Enable down
             the side, one block per currency. Each column is a Paymenter plan — ticking
             Enable creates it, clearing it removes it and its prices. --}}
        <div x-show="tab === 'pricing'" x-cloak>
            <form wire:submit.prevent="savePricing">
                <div class="ao-gs-card">
                    <div class="ao-gs-row">
                        <span class="ao-gs-label">Payment Type</span>
                        <div class="ao-gs-field ao-ep-paytype">
                            @foreach (['free' => 'Free', 'one-time' => 'One Time', 'recurring' => 'Recurring'] as $value => $label)
                                <label class="ao-check">
                                    <input type="radio" name="ep-paytype" value="{{ $value }}" wire:model.live="paymentType">
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="ao-gs-hint">Which cycles this product is sold on.</div>
                    </div>
                </div>

                @php
                    $cycles = \Paymenter\Extensions\Others\AdminOps\Admin\Pages\EditProduct::CYCLES;
                    // Free products have nothing to price; One Time hides the recurring
                    // columns, as the reference does.
                    $shown = collect($cycles)->filter(function ($c) use ($paymentType) {
                        if ($paymentType === 'free') return false;
                        return $paymentType === 'one-time' ? $c['type'] === 'one-time' : $c['type'] === 'recurring';
                    });
                @endphp

                @if ($shown->isEmpty())
                    <p class="ao-cat-empty">A free product carries no prices.</p>
                @else
                    @foreach ($currencies as $code)
                        <table class="ao-mu-grid ao-ep-grid">
                            <thead>
                                <tr>
                                    <th class="ao-ep-cur">{{ $code }}</th>
                                    @foreach ($shown as $key => $cycle)
                                        <th>{{ $cycle['label'] }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="ao-ep-cur">Setup Fee</td>
                                    @foreach ($shown as $key => $cycle)
                                        <td><input type="text" inputmode="decimal" class="ao-ei-amount-in"
                                            wire:model="pricing.{{ $key }}.{{ $code }}.setup_fee" placeholder="0.00"></td>
                                    @endforeach
                                </tr>
                                <tr>
                                    <td class="ao-ep-cur">Price</td>
                                    @foreach ($shown as $key => $cycle)
                                        <td><input type="text" inputmode="decimal" class="ao-ei-amount-in"
                                            wire:model="pricing.{{ $key }}.{{ $code }}.price" placeholder="0.00"></td>
                                    @endforeach
                                </tr>
                                <tr>
                                    <td class="ao-ep-cur">Enable</td>
                                    @foreach ($shown as $key => $cycle)
                                        {{-- One tick box per cycle, shown on every currency block
                                             because a cycle is enabled for the product, not per
                                             currency. --}}
                                        <td><input type="checkbox" wire:model="enabled.{{ $key }}"></td>
                                    @endforeach
                                </tr>
                            </tbody>
                        </table>
                    @endforeach
                @endif

                <div class="ao-gs-card">
                    <div class="ao-gs-row ao-gs-row--tall">
                        <span class="ao-gs-label">Allow Multiple Quantities</span>
                        <div class="ao-ep-radios">
                            @foreach ([
                                'disabled' => 'No',
                                'separated' => 'Yes - Multiple Services: each unit represents its own individual service instance',
                                'combined' => 'Yes - Scaling Service: each service instance allows a quantity to be defined',
                            ] as $value => $label)
                                <label class="ao-check">
                                    <input type="radio" name="ep-qty" value="{{ $value }}" wire:model="form.allow_quantity">
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="ao-gs-hint"></div>
                    </div>

                    {{-- Auto Terminate and its email are real: TermLimits' product term
                         table holds exactly these two, days after activation and which
                         email announces the end. --}}
                    <div class="ao-gs-row">
                        <label class="ao-gs-label" for="ep-term">Auto Terminate/Fixed Term</label>
                        <div class="ao-gs-field">
                            <input id="ep-term" type="number" min="0" class="ao-ep-num" wire:model="term.days">
                        </div>
                        <div class="ao-gs-hint">Enter the number of days after activation to automatically terminate (eg. free trials, time limited products). 0 is off.</div>
                    </div>

                    <div class="ao-gs-row">
                        <label class="ao-gs-label" for="ep-termmail">Termination Email</label>
                        <div class="ao-gs-field">
                            <select id="ep-termmail" wire:model="term.termination_email">
                                <option value="">Default (server terminated)</option>
                                @foreach ($emailTemplates as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ao-gs-hint">Choose the email template to send when the fixed term comes to an end.</div>
                    </div>

                    {{-- The rest of the reference's pricing block. Each is shown so the tab
                         reads as the target does, and each says why it cannot act rather
                         than looking live. --}}
                    <div class="ao-gs-row">
                        <span class="ao-gs-label">Recurring Cycles Limit</span>
                        <div class="ao-gs-field">
                            <input type="number" class="ao-ep-num" value="0" disabled
                                title="Paymenter invoices a service until it is cancelled; there is no cycle counter to stop it after N">
                        </div>
                        <div class="ao-gs-hint">Not available: billing runs until the service is cancelled, so there is no count to limit. Auto Terminate above ends a product after a fixed period.</div>
                    </div>

                    <div class="ao-gs-row">
                        <span class="ao-gs-label">Prorata Billing</span>
                        <div class="ao-gs-field">
                            <label class="ao-check ao-gs-off">
                                <input type="checkbox" disabled title="Renewals fall on the service's own date, not a shared day of the month">
                                <span>Check to enable</span>
                            </label>
                        </div>
                        <div class="ao-gs-hint">Not available: each service renews on its own anniversary rather than a shared billing day, so there is nothing to prorate onto. Prorata Date and Charge Next Month belong to that same model.</div>
                    </div>

                    <div class="ao-gs-row">
                        <span class="ao-gs-label">On-Demand Renewals</span>
                        <div class="ao-gs-field ao-gs-off">
                            <span class="ao-cpg-muted">Clients may renew early from their service page at any time.</span>
                        </div>
                        <div class="ao-gs-hint">Always on here, with no per-cycle window to configure.</div>
                    </div>
                </div>

                @if ($errors->any())
                    <ul class="ao-anc-errors">
                        @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                    </ul>
                @endif

                <div class="ao-pr-center ao-cpg-actions">
                    <button type="submit" class="ao-find-go">Save Changes</button>
                    <a class="ao-pg-btn" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\EditProduct::getUrl(['record' => $product->id]) }}">Cancel Changes</a>
                </div>
            </form>
        </div>

        {{-- ── Module Settings ─────────────────────────────────────────────────── --}}
        <div x-show="tab === 'module'" x-cloak>
            <form wire:submit.prevent="saveModule">
                <div class="ao-anc-card">
                <label class="ao-anc-row">
                    <span>Module Name</span>
                    <select wire:model.live="form.server_id">
                        <option value="">No Module</option>
                        @foreach ($servers as $server)
                            <option value="{{ $server->id }}">
                                {{ strcasecmp($server->name, $server->extension) === 0 ? $server->name : $server->name . ' (' . $server->extension . ')' }}
                            </option>
                        @endforeach
                    </select>
                </label>

                {{-- The reference picks a module, then a server *group* within it. A server
                     here is already one configured module instance rather than a machine,
                     so there is no group to rotate new orders around. --}}
                <div class="ao-anc-row">
                    <span>Server Group</span>
                    <span class="ao-anc-field ao-gs-off">
                        <select disabled title="A server here is one configured module, not a machine to fill">
                            <option>None</option>
                        </select>
                        <i>Not available: a server on this platform is a configured module rather than a
                            machine with a capacity, so there is nothing to rotate orders around or fill.</i>
                    </span>
                </div>

                {{-- The module's own fields, from its getProductConfig() — the same
                     descriptor shape the gateway editor renders. --}}
                @forelse ($moduleFields as $field)
                    <label class="ao-anc-row">
                        <span>
                            {{ $field['label'] ?? $field['name'] }}
                            @if (!empty($field['description'])) <i>{{ $field['description'] }}</i> @endif
                        </span>
                        @if (($field['type'] ?? 'text') === 'select')
                            <select wire:model="moduleSettings.{{ $field['name'] }}">
                                <option value="">—</option>
                                @foreach (($field['options'] ?? []) as $value => $label)
                                    <option value="{{ is_int($value) ? $label : $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        @elseif (($field['type'] ?? '') === 'checkbox')
                            <span class="ao-anc-field"><input type="checkbox" wire:model="moduleSettings.{{ $field['name'] }}"></span>
                        @else
                            <input type="text" wire:model="moduleSettings.{{ $field['name'] }}">
                        @endif
                    </label>
                @empty
                    <div class="ao-anc-row">
                        <span>Module Fields</span>
                        <span class="ao-cpg-muted">
                            {{ $form['server_id'] ? 'This module declares no per-product settings, or it could not be reached.' : 'Choose a module to see its settings.' }}
                        </span>
                    </div>
                @endforelse

                {{-- The reference's four auto-setup choices. Provisioning here runs off the
                     paid invoice, so the second is the one this platform does; the other
                     three are drawn so the tab reads as the target does, and each says why
                     it cannot be chosen rather than looking available. --}}
                <div class="ao-anc-row">
                    <span>Auto Setup</span>
                    <span class="ao-ep-radios">
                        <label class="ao-check ao-gs-off">
                            <input type="radio" name="ep-autosetup" disabled
                                title="Nothing is provisioned before the order is paid">
                            <span>Automatically setup the product as soon as an order is placed</span>
                        </label>
                        <label class="ao-check">
                            <input type="radio" name="ep-autosetup" checked
                                @disabled(!$form['server_id'])>
                            <span>Automatically setup the product as soon as the first payment is received</span>
                        </label>
                        <label class="ao-check ao-gs-off">
                            <input type="radio" name="ep-autosetup" disabled
                                title="Orders are not held for manual acceptance on this platform">
                            <span>Automatically setup the product when you manually accept a pending order</span>
                        </label>
                        <label class="ao-check @if ($form['server_id']) ao-gs-off @endif">
                            <input type="radio" name="ep-autosetup" @checked(!$form['server_id']) disabled
                                title="Choose No Module above to stop this product provisioning">
                            <span>Do not automatically setup this product</span>
                        </label>
                        <i>
                            @if ($form['server_id'])
                                Provisioning runs off the paid invoice, so payment is what starts it. Clearing
                                Module Name above is how this product is set up by hand instead.
                            @else
                                No module is chosen, so nothing is provisioned automatically.
                            @endif
                        </i>
                    </span>
                </div>

                </div>

                <div class="ao-pr-center ao-cpg-actions">
                    <button type="submit" class="ao-find-go">Save Changes</button>
                    <a class="ao-pg-btn" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\EditProduct::getUrl(['record' => $product->id]) }}">Cancel Changes</a>
                </div>
            </form>
        </div>

        {{-- ── Configurable Options ────────────────────────────────────────────── --}}
        <div x-show="tab === 'options'" x-cloak>
            <form wire:submit.prevent="saveOptions">
                <div class="ao-anc-card">
                {{-- The reference's list box, not a column of tick boxes: hold Ctrl or
                     Shift to pick more than one, the same as the target. --}}
                <div class="ao-anc-row">
                    <span>Assigned Option Groups</span>
                    <span class="ao-anc-field ao-ep-listfield">
                        <select class="ao-ep-list" multiple size="10" wire:model="optionIds"
                            title="Ctrl-click or Shift-click to choose more than one">
                            @foreach ($optionGroups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                        {{-- The reference draws the box alone; only an empty store needs
                             telling where groups come from. --}}
                        @if ($optionGroups->isEmpty())
                            <i>No configurable option groups exist yet — create them on
                                <a class="ao-link" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ConfigOptionGroups::getUrl() }}">Configurable Options</a>.</i>
                        @endif
                    </span>
                </div>

                </div>

                <div class="ao-pr-center ao-cpg-actions">
                    <button type="submit" class="ao-find-go">Save Changes</button>
                    <a class="ao-pg-btn" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\EditProduct::getUrl(['record' => $product->id]) }}">Cancel Changes</a>
                </div>
            </form>
        </div>

        {{-- ── Upgrades ────────────────────────────────────────────────────────── --}}
        <div x-show="tab === 'upgrades'" x-cloak>
            <form wire:submit.prevent="saveUpgrades">
                <div class="ao-anc-card">
                <div class="ao-anc-row">
                    <span title="The products a customer on this one may move to">Packages Upgrades</span>
                    <span class="ao-anc-field ao-ep-listfield">
                        <select class="ao-ep-list" multiple size="10" wire:model="upgradeIds">
                            @foreach ($otherProducts as $other)
                                <option value="{{ $other->id }}">{{ trim(($other->category?->name ? $other->category->name . ' - ' : '') . $other->name) }}</option>
                            @endforeach
                        </select>
                        <i>{{ $otherProducts->isEmpty()
                            ? 'There are no other products to upgrade to.'
                            : 'Use Ctrl+Click to select multiple packages' }}</i>
                    </span>
                </div>

                {{-- The reference's second control on this tab. Here it is
                     `config_options.upgradable`, which core reads when a client asks to
                     change an option mid-term. --}}
                <div class="ao-anc-row">
                    <span title="Allow the options this product carries to be changed mid-term">Configurable Options</span>
                    <span class="ao-anc-field">
                        <label class="ao-check"
                            title="Applies to drop-down, radio and slider options — a text box has no second choice to move to. This product carries {{ $product->configOptions->whereIn('type', ['select', 'radio', 'slider'])->count() }} such option(s).">
                            <input type="checkbox" wire:model="upgradeConfigOptions">
                            <span>Check to allow Upgrading/Downgrading of configurable options</span>
                        </label>
                    </span>
                </div>

                <div class="ao-anc-row">
                    <span>Upgrade Email</span>
                    <span class="ao-anc-field ao-gs-off">
                        <select class="ao-ep-narrow" disabled title="Not available: an upgrade amends the running service and raises the difference as an invoice, so the client is told by that invoice rather than by a separate template">
                            <option>None</option>
                        </select>
                    </span>
                </div>

                </div>

                <div class="ao-pr-center ao-cpg-actions">
                    <button type="submit" class="ao-find-go">Save Changes</button>
                    <a class="ao-pg-btn" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\EditProduct::getUrl(['record' => $product->id]) }}">Cancel Changes</a>
                </div>
            </form>
        </div>

        {{-- ── Custom Fields ───────────────────────────────────────────────────── --}}
        <div x-show="tab === 'custom'" x-cloak>
            {{-- The fields already asked for this product. The reference lists them above
                 the add form, each with the delete cross on the right. --}}
            <div class="ao-anc-card">
                <table class="ao-mu-grid ao-ep-fields">
                    <thead>
                        <tr>
                            <th>Field Name</th>
                            <th>Field Type</th>
                            <th>Description</th>
                            <th>Options</th>
                            <th>Order</th>
                            <th>Admin Only</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customFields as $field)
                            <tr>
                                <td>{{ $field->name }}</td>
                                <td>{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\EditProduct::FIELD_TYPES[$field->type] ?? ucfirst((string) $field->type) }}</td>
                                <td>{{ $field->description ?: '—' }}</td>
                                <td>{{ $field->children->pluck('name')->implode(', ') ?: '—' }}</td>
                                <td>{{ (int) $field->sort }}</td>
                                <td>{{ $field->hidden ? 'Yes' : 'No' }}</td>
                                <td class="ao-mu-actions">
                                    <button type="button" class="ao-mo-delete" title="Remove this field from the product"
                                        wire:click="deleteCustomField({{ $field->id }})"
                                        wire:confirm="Remove this field from the product?">
                                        <x-filament::icon icon="ri-indeterminate-circle-fill" class="ao-mu-cell-icon ao-mu-icon-red" />
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="ao-mu-none">No custom fields have been defined for this product.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <h3 class="ao-sub">Add New Custom Field</h3>

            <form wire:submit.prevent="saveCustomField">
                <div class="ao-anc-card">
                <label class="ao-anc-row">
                    <span>
                        Field Name
                        <i>Shown to the client on the order form.</i>
                    </span>
                    <input type="text" wire:model="customField.name" maxlength="255">
                </label>

                <label class="ao-anc-row">
                    <span>Field Type</span>
                    <select wire:model.live="customField.type">
                        @foreach (\Paymenter\Extensions\Others\AdminOps\Admin\Pages\EditProduct::FIELD_TYPES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="ao-anc-row">
                    <span>
                        Description
                        <i>Optional help text beneath the field.</i>
                    </span>
                    <input type="text" wire:model="customField.description" maxlength="255">
                </label>

                {{-- The reference's Validation row, in its place. `config_options` carries
                     no pattern column, so there is nowhere to keep one — the field types
                     above are what constrain input here (Number takes only numbers, Drop
                     Down only its own choices). --}}
                <label class="ao-anc-row ao-gs-off">
                    <span>Validation</span>
                    <span class="ao-anc-field"
                        title="Not available: a config option stores no validation pattern — pick a Field Type that constrains the answer instead">
                        <input type="text" disabled placeholder="Regular Expression Validation String">
                    </span>
                </label>

                @if (in_array($customField['type'], \Paymenter\Extensions\Others\AdminOps\Admin\Pages\EditProduct::FIELD_TYPES_WITH_CHOICES, true))
                    <label class="ao-anc-row">
                        <span>
                            Select Options
                            <i>One choice per line.</i>
                        </span>
                        <textarea rows="5" wire:model="customField.allowed_values"></textarea>
                    </label>
                @endif

                <label class="ao-anc-row">
                    <span>
                        Variable Name
                        <i>What the provisioning module reads the answer back under. Left blank, it is
                            made from the field name.</i>
                    </span>
                    <input type="text" wire:model="customField.env_variable" maxlength="255" placeholder="PROXY_REGION">
                </label>

                <label class="ao-anc-row">
                    <span>Display Order</span>
                    <input type="number" min="0" max="255" class="ao-w-25" wire:model="customField.sort">
                </label>

                <div class="ao-anc-row">
                    <span>Field Options</span>
                    <span class="ao-ep-radios">
                        <label class="ao-check">
                            <input type="checkbox" wire:model="customField.hidden">
                            <span>Admin Only — hide this field from the order form</span>
                        </label>
                        {{-- The reference's other three ticks. A config option has no column
                             for any of them, and a box that saved nowhere would be worse
                             than one that says so. --}}
                        <label class="ao-check ao-gs-off">
                            <input type="checkbox" disabled title="Every field asked on the order form is answered before the order is placed">
                            <span>Required Field</span>
                        </label>
                        <label class="ao-check ao-gs-off">
                            <input type="checkbox" checked disabled title="A field not marked Admin Only is always on the order form">
                            <span>Show on Order Form</span>
                        </label>
                        <label class="ao-check ao-gs-off">
                            <input type="checkbox" disabled title="Invoice lines name the product and its options, not each field">
                            <span>Show on Invoice</span>
                        </label>
                    </span>
                </div>

                @if ($errors->any())
                    <div class="ao-anc-errors">
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

            </div>

                <div class="ao-pr-center ao-cpg-actions">
                    <button type="submit" class="ao-find-go">Save Changes</button>
                    <a class="ao-pg-btn" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\EditProduct::getUrl(['record' => $product->id]) }}">Cancel Changes</a>
                </div>

                <p class="ao-cp-note">
                    A per-product field is a configurable option here, attached to this product alone —
                    the same thing the reference asks on the order form and carries onto the service.
                    Fields shared across products are better made once on
                    <a class="ao-link" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ConfigOptionGroups::getUrl() }}">Configurable Options</a>
                    and assigned from the tab beside this one.
                </p>
            </form>
        </div>

        {{-- ── Free Domain ─────────────────────────────────────────────────────── --}}
        {{-- ── Cross-sells ─────────────────────────────────────────────────────── --}}
        <div x-show="tab === 'crosssells'" x-cloak>
            <form wire:submit.prevent="saveCrossSells">
                <div class="ao-anc-card">
                <div class="ao-anc-row">
                    <span title="Shown as recommendations on this product's own page">Product Cross-sells</span>
                    {{-- The reference's type-to-search picker: a search box that filters a
                         grouped list, each pick becoming a removable tag. --}}
                    <span class="ao-anc-field ao-ep-cross"
                        x-data="{
                            open: false,
                            q: '',
                            chosen: @js(array_map('strval', $this->crossSellIds)),
                            all: @js($otherProducts->map(fn ($p) => [
                                'id' => (string) $p->id,
                                'name' => $p->name,
                                'group' => $p->category?->name ?? 'Ungrouped',
                            ])->values()->all()),
                            get picked() { return this.all.filter((p) => this.chosen.includes(p.id)) },
                            get groups() {
                                const q = this.q.trim().toLowerCase();
                                const hits = this.all.filter((p) =>
                                    !this.chosen.includes(p.id)
                                    && (q === '' || p.name.toLowerCase().includes(q) || p.group.toLowerCase().includes(q)));
                                return hits.reduce((acc, p) => {
                                    (acc[p.group] = acc[p.group] || []).push(p);
                                    return acc;
                                }, {});
                            },
                            get anyLeft() { return Object.keys(this.groups).length > 0 },
                            sync() { this.$wire.set('crossSellIds', [...this.chosen], false) },
                            add(id) {
                                if (!this.chosen.includes(id)) { this.chosen.push(id); this.sync() }
                                this.q = '';
                                this.$refs.search && this.$refs.search.focus();
                            },
                            drop(id) { this.chosen = this.chosen.filter((v) => v !== String(id)); this.sync() },
                        }"
                        @click.outside="open = false"
                        @keydown.escape.window="open = false">
                        <span class="ao-ep-cross-box" @click="open = true">
                            <template x-for="p in picked" :key="p.id">
                                <span class="ao-stf-chip">
                                    <span x-text="p.name"></span>
                                    <button type="button" @click.stop="drop(p.id)"
                                        :aria-label="'Remove ' + p.name" title="Remove">&times;</button>
                                </span>
                            </template>
                            <input type="text" class="ao-ep-cross-input" x-ref="search" x-model="q"
                                @focus="open = true" placeholder="Start typing to search for products.">
                        </span>
                        <span class="ao-xsel-list ao-ep-cross-list" x-show="open" x-cloak>
                            <template x-for="(items, group) in groups" :key="group">
                                <span>
                                    <span class="ao-xsel-group" x-text="group"></span>
                                    <template x-for="p in items" :key="p.id">
                                        <span class="ao-xsel-opt" x-text="p.name" @mousedown.prevent="add(p.id)"></span>
                                    </template>
                                </span>
                            </template>
                            <span class="ao-xsel-opt ao-off" x-show="!anyLeft"
                                x-text="q.trim() ? 'No products match.' : 'Every other product is already selected.'"></span>
                        </span>
                    </span>
                </div>

            </div>

                <div class="ao-pr-center ao-cpg-actions">
                    <button type="submit" class="ao-find-go">Save Changes</button>
                    <a class="ao-pg-btn" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\EditProduct::getUrl(['record' => $product->id]) }}">Cancel Changes</a>
                </div>
            </form>
        </div>

        {{-- ── Other ───────────────────────────────────────────────────────────── --}}
        <div x-show="tab === 'other'" x-cloak>
            <form wire:submit.prevent="saveOther">
                <div class="ao-anc-card">
                {{-- The reference's own rows, in its order (issue #35). The inert ones say
                     so on themselves rather than in a paragraph underneath; the three that
                     are real — Associated Downloads, and this platform's own Sort Order and
                     Limit Per Client below — save with the rest. --}}
                @php
                    $affiliateOff = 'Commission is set for the whole store by the Affiliates extension, not per product';
                    $hostingOff = 'Belongs to shared hosting — a proxy service has no disk or bandwidth to meter';
                    $overagesNote = 'Saved, but nothing bills from it: an overage needs a usage figure and the panel reports none. A bandwidth cap the panel does honour is set on Module Settings as bwlimit.';
                @endphp
                <div class="ao-anc-row ao-gs-off">
                    <span>Custom Affiliate Payout</span>
                    <span class="ao-ano-checks">
                        @foreach (['default' => 'Use Default', 'percentage' => 'Percentage',
                                   'fixed' => 'Fixed Amount', 'none' => 'No Commission'] as $value => $label)
                            <label><input type="radio" value="{{ $value }}" wire:model="affiliatePayout"> {{ $label }}</label>
                        @endforeach
                    </span>
                </div>
                <div class="ao-anc-row ao-gs-off">
                    <span>Affiliate Pay Amount</span>
                    <span class="ao-anc-field">
                        <input type="text" class="ao-w-25" wire:model="affiliateAmount"
                            title="A percentage of the line when Percentage is picked, or an amount in the invoice's currency when Fixed Amount is">
                        <label class="ao-check"><input type="checkbox" wire:model="affiliateOneTime">
                            <span>One Time Payout (Default is Recurring)</span></label>
                    </span>
                </div>
                {{-- The reference's own sentence, above the pair it explains. --}}
                <p class="ao-ep-dl-lead">This is where you can specify files that are granted
                    access to by purchasing this product.</p>

                {{-- Available Files / Selected Files, with the reference's single `<->`
                     between them: it moves whichever box has a highlighted row. The list is
                     AdminOps' own Downloads area, so this records a real grant rather than
                     a remembered one. Alpine does the moving; Livewire keeps the ids. --}}
                <div class="ao-anc-row ao-ep-dl-row">
                    <span>Associated Downloads</span>
                    {{-- Drawn even with nothing to put in it: the reference shows the pair
                         empty on a store that has uploaded no files, and a store with none
                         is exactly when an admin needs Add Category and Quick Upload. --}}
                        <span class="ao-anc-field ao-ep-dl"
                            x-data="{
                                all: @js($downloadFiles),
                                left: [],
                                right: [],
                                get chosen() { return this.$wire.downloadIds.map(String) },
                                get available() { return this.all.filter((f) => !this.chosen.includes(f.id)) },
                                get selected() { return this.all.filter((f) => this.chosen.includes(f.id)) },
                                move() {
                                    if (this.left.length) {
                                        this.$wire.downloadIds = [...this.chosen, ...this.left.map(String)];
                                        this.left = [];
                                    } else if (this.right.length) {
                                        const drop = this.right.map(String);
                                        this.$wire.downloadIds = this.chosen.filter((id) => !drop.includes(id));
                                        this.right = [];
                                    }
                                },
                            }">
                            <span class="ao-ep-dl-col">
                                <b>Available Files</b>
                                <select class="ao-ep-list ao-ep-dl-list" multiple size="8" x-model="left">
                                    <template x-for="file in available" :key="file.id">
                                        <option :value="file.id" x-text="file.title"></option>
                                    </template>
                                </select>
                            </span>
                            <button type="button" class="ao-pg-btn ao-ep-dl-move" @click="move()"
                                title="Move the highlighted files between the two lists">&lt;-&gt;</button>
                            <span class="ao-ep-dl-col">
                                <b>Selected Files</b>
                                <select class="ao-ep-list ao-ep-dl-list" multiple size="8" x-model="right">
                                    <template x-for="file in selected" :key="file.id">
                                        <option :value="file.id" x-text="file.title"></option>
                                    </template>
                                </select>
                            </span>
                            {{-- The reference's two buttons under the pair, centred on it
                                 rather than on the page. Both are the Downloads area's own
                                 jobs, so they go there rather than opening a second
                                 uploader here. --}}
                            <span class="ao-ep-dl-actions">
                                <a class="ao-pg-btn" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\DownloadsAdmin::getUrl() }}">Add Category</a>
                                <a class="ao-pg-btn" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\DownloadsAdmin::getUrl() }}">Quick Upload</a>
                            </span>
                        </span>
                </div>

                <div class="ao-anc-row ao-gs-off">
                    <span>Overages Billing</span>
                    <span class="ao-of-check" title="{{ $overagesNote }}">
                        <input type="checkbox" wire:model="overagesBilling"> Check to Enable
                    </span>
                </div>
                <div class="ao-anc-row ao-gs-off">
                    <span>Soft Limits</span>
                    <span class="ao-anc-field ao-ep-units" title="{{ $overagesNote }}">
                        <i>Disk Usage</i> <input type="text" class="ao-w-25" wire:model="softLimits.disk">
                        <select class="ao-ep-unit" wire:model="softLimits.disk_unit"><option>MB</option><option>GB</option></select>
                        <i>Bandwidth</i> <input type="text" class="ao-w-25" wire:model="softLimits.bw">
                        <select class="ao-ep-unit" wire:model="softLimits.bw_unit"><option>MB</option><option>GB</option></select>
                    </span>
                </div>
                <div class="ao-anc-row ao-gs-off">
                    <span>Overage Costs</span>
                    <span class="ao-anc-field ao-ep-units" title="{{ $overagesNote }}">
                        <i>Disk Usage</i> <input type="text" class="ao-w-25" wire:model="overageCosts.disk">
                        <i>Bandwidth</i> <input type="text" class="ao-w-25" wire:model="overageCosts.bw">
                        <i>(Price Per Unit Over Above)</i>
                    </span>
                </div>

                {{-- Not on the reference's tab, but this platform's own and real: they sit
                     after its rows so the top of the screen reads as the target does. --}}
                <label class="ao-anc-row">
                    <span>
                        Sort Order
                        <i>Where this product sits within its group on the storefront.</i>
                    </span>
                    <input type="number" min="0" class="ao-w-25" wire:model="form.sort">
                </label>

                <label class="ao-anc-row">
                    <span>
                        Limit Per Client
                        <i>0 or blank for no limit.</i>
                    </span>
                    <input type="number" min="0" class="ao-w-25" wire:model="form.per_user_limit">
                </label>

                </div>

                <div class="ao-pr-center ao-cpg-actions">
                    <button type="submit" class="ao-find-go">Save Changes</button>
                    <a class="ao-pg-btn" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\EditProduct::getUrl(['record' => $product->id]) }}">Cancel Changes</a>
                </div>
            </form>
        </div>

        {{-- ── Links ───────────────────────────────────────────────────────────── --}}
        <div x-show="tab === 'links'" x-cloak>
            <div class="ao-anc-card">
                {{-- The reference's four rows, in its order and under its names, plus this
                     storefront's own direct product link. Its template and domain variants
                     carry the same parameters the reference puts on them; this storefront
                     draws one cart template, which is the one named. --}}
                @foreach ([
                    'Direct Shopping Cart Link' => $links['checkout'],
                    'Direct Shopping Cart Link Specifying Template' => $links['checkout'] ? $links['checkout'] . '?carttpl=' . config('settings.theme', 'proxy') : null,
                    'Direct Shopping Cart Link Including Domain' => $links['checkout'],
                    'Product Group Cart Link' => $links['group'],
                    'Direct Product Link' => $links['product'],
                ] as $label => $url)
                    @continue(!$url)
                    <div class="ao-anc-row ao-ep-linkrow">
                        <span>{{ $label }}</span>
                        <span class="ao-anc-field">
                            <input type="text" value="{{ $url }}" readonly onclick="this.select()">
                            <button type="button" class="ao-pg-btn ao-ep-copy" title="Copy this link"
                                onclick="navigator.clipboard.writeText(this.previousElementSibling.value); this.textContent = 'Copied';">&#128203;</button>
                        </span>
                    </div>
                @endforeach

                @unless ($links['product'])
                    <div class="ao-anc-row">
                        <span>Links</span>
                        <span class="ao-cpg-muted">This product has no group yet, so it has no storefront address.</span>
                    </div>
                @endunless

                <p class="ao-cp-note">
                    A hidden product keeps working on its direct link — that is what the
                    Products/Services page means by ordering a hidden product by link.
                </p>
            </div>

            {{-- The reference's Product URLs table, inside the card under its own grey band.
                 Visits are real now — counted per requested path in ext_product_url_visits
                 (AdminOps::countProductUrlVisits) — so a figure here is traffic that
                 happened. The remove column stays inert: these addresses are derived from
                 the group and slug, not a stored list there is a row to delete from. --}}
            <div class="ao-anc-card ao-ep-urls">
                <div class="ao-ep-urls-head">Product URLs</div>
                <table class="ao-mu-grid">
                    <thead>
                        <tr>
                            <th>URL</th>
                            <th class="ao-ep-urls-visits">Visits</th>
                            <th class="ao-ep-urls-act"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (array_filter($links) as $url)
                            <tr>
                                <td class="ao-mu-left">
                                    <span class="ao-anc-field">
                                        <input type="text" value="{{ $url }}" readonly onclick="this.select()">
                                        <button type="button" class="ao-pg-btn ao-ep-copy" title="Copy this link"
                                            onclick="navigator.clipboard.writeText(this.previousElementSibling.value); this.textContent = 'Copied';">&#128203;</button>
                                    </span>
                                </td>
                                <td title="Hits on this address since counting began">{{ number_format(\Paymenter\Extensions\Others\AdminOps\Admin\Pages\EditProduct::visitsFor($urlVisits, $url)) }}</td>
                                <td class="ao-mu-actions">
                                    <span title="These addresses are derived from the product's group and slug — change those to change the link; there is no stored list to delete from">
                                        <x-filament::icon icon="ri-indeterminate-circle-fill" class="ao-mu-cell-icon ao-pl-dead" />
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="ao-mu-none">This product has no group yet, so it has no storefront address.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                <p class="ao-cp-note">
                    These are the product's real addresses rather than a list to add to, so there is
                    no row to delete. Visits count every storefront request for each address, from the
                    day this was deployed onwards — earlier traffic was never recorded.
                </p>
            </div>
        </div>
    </div>
</x-filament-panels::page>
