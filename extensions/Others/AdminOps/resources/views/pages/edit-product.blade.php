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

        {{-- ── Details ─────────────────────────────────────────────────────────── --}}
        @if ($tab === 'details')
            <form class="ao-anc-card" wire:submit.prevent="saveDetails">
                <label class="ao-anc-row">
                    <span>Product Type</span>
                    <select wire:model="extra.type">
                        @foreach (\Paymenter\Extensions\Others\AdminOps\Models\Meta::PRODUCT_TYPES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="ao-anc-row">
                    <span>Product Group</span>
                    <select wire:model="form.category_id">
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="ao-anc-row">
                    <span>Product Name</span>
                    <input type="text" wire:model="form.name" required>
                </label>
                @error('form.name') <p class="ao-anc-errors">{{ $message }}</p> @enderror

                <label class="ao-anc-row">
                    <span>Product Tagline</span>
                    <input type="text" wire:model="extra.tagline" placeholder="Shown under the name on the storefront">
                </label>

                <label class="ao-anc-row">
                    <span>URL</span>
                    <input type="text" wire:model="form.slug">
                </label>
                @error('form.slug') <p class="ao-anc-errors">{{ $message }}</p> @enderror

                <label class="ao-anc-row ao-cpg-desc">
                    <span>Product Short Description</span>
                    <textarea rows="2" wire:model="extra.short_description"
                        placeholder="Around 50 words. Used where a short summary is wanted."></textarea>
                </label>

                <label class="ao-anc-row ao-cpg-desc">
                    <span>Product Description</span>
                    <textarea rows="6" wire:model="form.description"
                        placeholder="HTML is allowed. Shown on the product's own page."></textarea>
                </label>

                <label class="ao-anc-row">
                    <span>Product Colour</span>
                    <span class="ao-anc-field">
                        <input type="color" wire:model="extra.colour" class="ao-ep-colour">
                        <i>Used where the storefront tints a product.</i>
                    </span>
                </label>

                <label class="ao-anc-row">
                    <span>Welcome Email</span>
                    <input type="text" wire:model="form.email_template"
                        placeholder="Notification template key, or blank for none">
                </label>

                <div class="ao-anc-row">
                    <span>Stock Control</span>
                    <span class="ao-anc-field">
                        <input type="checkbox" wire:model.live="form.stock_enabled">
                        <i>Enable — Quantity in Stock:</i>
                        <input type="number" min="0" class="ao-w-25" wire:model="form.stock"
                            @disabled(!$form['stock_enabled'])>
                    </span>
                </div>

                {{-- Three radios, not a tick box: the column is an enum and the reference
                     asks the same three-way question. --}}
                <div class="ao-anc-row">
                    <span>Allow Multiple Quantities</span>
                    <span class="ao-ep-radios">
                        @foreach ([
                            'disabled' => 'No',
                            'separated' => 'Yes - Multiple Services: each unit is its own service instance',
                            'combined' => 'Yes - Scaling Service: one instance with a quantity',
                        ] as $value => $label)
                            <label class="ao-check">
                                <input type="radio" name="ep-qty" value="{{ $value }}" wire:model="form.allow_quantity">
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </span>
                </div>

                <label class="ao-anc-row">
                    <span>Limit Per Client</span>
                    <span class="ao-anc-field">
                        <input type="number" min="0" class="ao-w-25" wire:model="form.per_user_limit">
                        <i>0 or blank for no limit</i>
                    </span>
                </label>

                <label class="ao-anc-row">
                    <span>Featured</span>
                    <span class="ao-anc-field">
                        <input type="checkbox" wire:model="extra.featured">
                        <i>Display this product more prominently on the storefront</i>
                    </span>
                </label>

                <label class="ao-anc-row">
                    <span>Hidden</span>
                    <span class="ao-anc-field">
                        <input type="checkbox" wire:model="form.hidden">
                        <i>Check to hide from the order form</i>
                    </span>
                </label>

                <label class="ao-anc-row">
                    <span>Retired</span>
                    <span class="ao-anc-field">
                        <input type="checkbox" wire:model="extra.retired">
                        <i>Hidden from admin product menus. Existing services keep working.</i>
                    </span>
                </label>

                <div class="ao-pr-center ao-cpg-actions">
                    <button type="submit" class="ao-find-go">Save Changes</button>
                </div>
            </form>
        @endif

        {{-- ── Pricing ─────────────────────────────────────────────────────────── --}}
        @if ($tab === 'pricing')
            <form wire:submit.prevent="savePricing">
                <div class="ao-ei-items-head">
                    <h4 class="ao-ano-heading">Billing Cycles</h4>
                    <button type="button" class="ao-pg-btn" wire:click="addPlan">Add a Cycle</button>
                </div>

                {{-- A Paymenter plan is a billing cycle, so the reference's grid of cycles
                     becomes one block per plan, priced per currency. --}}
                @forelse ($plans as $i => $plan)
                    <div class="ao-anc-card ao-ep-plan">
                        <div class="ao-anc-row">
                            <span>Cycle</span>
                            <span class="ao-anc-field">
                                <input type="text" class="ao-w-25" wire:model="plans.{{ $i }}.name" placeholder="Monthly">
                                <select wire:model="plans.{{ $i }}.type" class="ao-w-25">
                                    <option value="recurring">Recurring</option>
                                    <option value="one-time">One Time</option>
                                    <option value="free">Free</option>
                                </select>
                                <i>every</i>
                                <input type="number" min="1" class="ao-ep-num" wire:model="plans.{{ $i }}.billing_period">
                                <select wire:model="plans.{{ $i }}.billing_unit" class="ao-ep-unit">
                                    <option value="day">day(s)</option>
                                    <option value="week">week(s)</option>
                                    <option value="month">month(s)</option>
                                    <option value="year">year(s)</option>
                                </select>
                                <button type="button" class="ao-ei-remove" wire:click="removePlan({{ $i }})"
                                    wire:confirm="Remove this billing cycle and its prices?"
                                    title="Remove this cycle">&#9679;</button>
                            </span>
                        </div>

                        <table class="ao-mu-grid ao-ep-prices">
                            <thead><tr><th>Currency</th><th>Setup Fee</th><th>Price</th></tr></thead>
                            <tbody>
                                @foreach ($currencies as $code)
                                    <tr>
                                        <td>{{ $code }}</td>
                                        <td><input type="text" inputmode="decimal" class="ao-ei-amount-in"
                                            wire:model="plans.{{ $i }}.prices.{{ $code }}.setup_fee" placeholder="0.00"></td>
                                        <td><input type="text" inputmode="decimal" class="ao-ei-amount-in"
                                            wire:model="plans.{{ $i }}.prices.{{ $code }}.price" placeholder="0.00"></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @empty
                    <p class="ao-cat-empty">This product has no billing cycle yet, so it cannot be ordered. Add one.</p>
                @endforelse

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
