{{--
    Edit Product, to the reference's screens: the tab strip, then one card per tab with its
    own Save Changes. See EditProduct's docblock for the reference tabs this deployment has
    nothing behind (Free Domain, Cross-sells, Custom Fields, Other).
--}}
<x-filament-panels::page>
    <div class="ao-mu ao-ep">
        <div class="ao-ei-top">
            <div class="ao-tx-tabs ao-ei-tabs">
                @foreach ([
                    'details' => 'Details',
                    'pricing' => 'Pricing',
                    'module' => 'Module Settings',
                    'custom' => 'Custom Fields',
                    'options' => 'Configurable Options',
                    'upgrades' => 'Upgrades',
                    'domain' => 'Free Domain',
                    'crosssells' => 'Cross-sells',
                    'other' => 'Other',
                    'links' => 'Links',
                ] as $key => $label)
                    <button type="button" class="ao-mu-tab {{ $tab === $key ? 'ao-on' : '' }}"
                        wire:click="$set('tab', '{{ $key }}')">{{ $label }}</button>
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
        @if ($tab === 'details')
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
                </div>
            </form>
        @endif

        {{-- ── Pricing ─────────────────────────────────────────────────────────
             The reference's grid: cycles across the top, Setup Fee / Price / Enable down
             the side, one block per currency. Each column is a Paymenter plan — ticking
             Enable creates it, clearing it removes it and its prices. --}}
        @if ($tab === 'pricing')
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
                </div>

                @if ($errors->any())
                    <ul class="ao-anc-errors">
                        @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                    </ul>
                @endif

                <div class="ao-pr-center ao-cpg-actions">
                    <button type="submit" class="ao-find-go">Save Changes</button>
                </div>
            </form>
        @endif

        {{-- ── Module Settings ─────────────────────────────────────────────────── --}}
        @if ($tab === 'module')
            <form class="ao-anc-card" wire:submit.prevent="saveModule">
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

                <div class="ao-anc-row">
                    <span>Auto Setup</span>
                    {{-- Paymenter provisions when the first payment lands; there is no
                         choice of four to offer, so this reports rather than asks. --}}
                    <span class="ao-cpg-muted">
                        {{ $form['server_id']
                            ? 'Set up automatically as soon as the first payment is received.'
                            : 'No module — nothing is provisioned automatically.' }}
                    </span>
                </div>

                <div class="ao-pr-center ao-cpg-actions">
                    <button type="submit" class="ao-find-go">Save Changes</button>
                </div>
            </form>
        @endif

        {{-- ── Configurable Options ────────────────────────────────────────────── --}}
        @if ($tab === 'options')
            <form class="ao-anc-card" wire:submit.prevent="saveOptions">
                <div class="ao-anc-row">
                    <span>Assigned Option Groups</span>
                    <span class="ao-cpg-gateways">
                        @forelse ($optionGroups as $group)
                            <label class="ao-check">
                                <input type="checkbox" value="{{ $group->id }}" wire:model="optionIds">
                                <span>{{ $group->name }}</span>
                            </label>
                        @empty
                            <i>No configurable option groups exist yet.</i>
                        @endforelse
                    </span>
                </div>

                <div class="ao-pr-center ao-cpg-actions">
                    <button type="submit" class="ao-find-go">Save Changes</button>
                </div>
            </form>
        @endif

        {{-- ── Upgrades ────────────────────────────────────────────────────────── --}}
        @if ($tab === 'upgrades')
            <form class="ao-anc-card" wire:submit.prevent="saveUpgrades">
                <div class="ao-anc-row">
                    <span>
                        Package Upgrades
                        <i>The products a customer on this one may move to.</i>
                    </span>
                    <span class="ao-cpg-gateways">
                        @forelse ($otherProducts as $other)
                            <label class="ao-check">
                                <input type="checkbox" value="{{ $other->id }}" wire:model="upgradeIds">
                                <span>{{ $other->name }}</span>
                            </label>
                        @empty
                            <i>There are no other products to upgrade to.</i>
                        @endforelse
                    </span>
                </div>

                <div class="ao-pr-center ao-cpg-actions">
                    <button type="submit" class="ao-find-go">Save Changes</button>
                </div>
            </form>
        @endif

        {{-- ── Custom Fields ───────────────────────────────────────────────────── --}}
        @if ($tab === 'custom')
            <div class="ao-anc-card">
                <div class="ao-anc-row">
                    <span>Custom Fields</span>
                    <span class="ao-ep-explain">
                        <p>The reference defines custom fields <b>per product</b>, collected on that
                            product's order form. This platform defines them <b>per client</b> —
                            every custom property on this install is attached to
                            <code>App\Models\User</code> — so there is no per-product set to edit here.</p>
                        <p>They are managed at
                            <a class="ao-link" href="{{ url('/admin/custom-properties') }}">Configuration → Custom Client Fields</a>,
                            and apply to every order rather than to one product.</p>
                    </span>
                </div>
            </div>
        @endif

        {{-- ── Free Domain ─────────────────────────────────────────────────────── --}}
        @if ($tab === 'domain')
            <div class="ao-anc-card">
                <div class="ao-anc-row">
                    <span>Free Domain</span>
                    <span class="ao-ep-explain">
                        <p>Domains are switched off on this deployment — it sells proxies, and there
                            is no registrar, no TLD pricing and no domain field at checkout. See
                            <code>docs/10-disable-domains.md</code>.</p>
                        <p>Every control the reference puts on this tab — the free-domain radios,
                            payment terms and TLD list — would have nothing to act on, so they are
                            not drawn.</p>
                    </span>
                </div>
            </div>
        @endif

        {{-- ── Cross-sells ─────────────────────────────────────────────────────── --}}
        @if ($tab === 'crosssells')
            <form class="ao-anc-card" wire:submit.prevent="saveCrossSells">
                <div class="ao-anc-row">
                    <span>
                        Product Cross-sells
                        <i>Shown as recommendations on this product's own page.</i>
                    </span>
                    <span class="ao-cpg-gateways">
                        @forelse ($otherProducts as $other)
                            <label class="ao-check">
                                <input type="checkbox" value="{{ $other->id }}" wire:model="crossSellIds">
                                <span>{{ $other->name }}</span>
                            </label>
                        @empty
                            <i>There are no other products to recommend.</i>
                        @endforelse
                    </span>
                </div>

                <div class="ao-pr-center ao-cpg-actions">
                    <button type="submit" class="ao-find-go">Save Changes</button>
                </div>
            </form>
        @endif

        {{-- ── Other ───────────────────────────────────────────────────────────── --}}
        @if ($tab === 'other')
            <form class="ao-anc-card" wire:submit.prevent="saveDetails">
                {{-- The two things on the reference's Other tab this platform genuinely
                     has. They save through Details, which owns the same record. --}}
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

                <div class="ao-anc-row">
                    <span>Not available here</span>
                    <span class="ao-ep-explain">
                        <p><b>Custom Affiliate Payout</b> — commission is set for the whole store on
                            the Affiliates extension, not per product.</p>
                        <p><b>Subdomain Options</b> and <b>Overages Billing</b> — both belong to
                            shared hosting: a subdomain to offer on signup, and disk/bandwidth
                            metering to charge above. Proxies have neither.</p>
                        <p><b>Associated Downloads</b> — Downloads exist here, but nothing links a
                            file to a product; they are published by category to everyone.</p>
                    </span>
                </div>

                <div class="ao-pr-center ao-cpg-actions">
                    <button type="submit" class="ao-find-go">Save Changes</button>
                </div>
            </form>
        @endif

        {{-- ── Links ───────────────────────────────────────────────────────────── --}}
        @if ($tab === 'links')
            <div class="ao-anc-card">
                @foreach ([
                    'Direct Product Link' => $links['product'],
                    'Product Group Link' => $links['group'],
                    'Direct Shopping Cart Link' => $links['checkout'],
                ] as $label => $url)
                    @continue(!$url)
                    <div class="ao-anc-row">
                        <span>{{ $label }}</span>
                        <span class="ao-anc-field">
                            <input type="text" value="{{ $url }}" readonly onclick="this.select()">
                            <button type="button" class="ao-pg-btn"
                                onclick="navigator.clipboard.writeText(this.previousElementSibling.value)">Copy</button>
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
        @endif
    </div>
</x-filament-panels::page>
