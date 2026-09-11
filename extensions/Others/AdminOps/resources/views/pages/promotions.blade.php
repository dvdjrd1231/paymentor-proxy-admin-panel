{{-- WHMCS's Promotions on Paymenter's coupons: the grid, and the editor beneath it. --}}
<x-filament-panels::page>
    <div class="ao-mu">
        @if ($canCreate)
            <div class="ao-tx-tabs">
                <button type="button" class="ao-mu-tab" wire:click="create">&#10010; Create New Promotion</button>
            </div>
        @endif

        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Type</th>
                    <th class="ao-num">Value</th>
                    <th>Applies To</th>
                    <th>Duration</th>
                    <th class="ao-num">Uses</th>
                    <th>Start Date</th>
                    <th>Expiry Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($promotions as $promotion)
                    @php
                        $expired = $promotion->expires_at && $promotion->expires_at->isPast();
                        $pending = $promotion->starts_at && $promotion->starts_at->isFuture();
                        $spent = $promotion->max_uses && $promotion->services_count >= $promotion->max_uses;
                    @endphp
                    <tr class="{{ (string) $promotion->id === $editing ? 'ao-cn-sticky' : '' }}">
                        <td class="ao-mu-left">
                            <button type="button" class="ao-link" wire:click="edit({{ $promotion->id }})">{{ $promotion->code }}</button>
                            @if ($expired)
                                <span class="ao-tag ao-tag-danger" title="Past its expiry date — the checkout refuses it">Expired</span>
                            @elseif ($pending)
                                <span class="ao-tag ao-tag-warning" title="Start date is in the future — the checkout refuses it until then">Not started</span>
                            @elseif ($spent)
                                <span class="ao-tag ao-tag-danger" title="Maximum uses reached — the checkout refuses it">Used up</span>
                            @endif
                        </td>
                        <td>{{ $types[$promotion->type] ?? ucfirst((string) $promotion->type) }}</td>
                        <td class="ao-num">
                            {{ $promotion->type === 'percentage'
                                ? rtrim(rtrim(number_format((float) $promotion->value, 2), '0'), '.') . '%'
                                : number_format((float) $promotion->value, 2) }}
                        </td>
                        <td>{{ $appliesTo[$promotion->applies_to] ?? 'Recurring price and setup fee' }}</td>
                        <td>
                            @switch((string) $promotion->recurring)
                                @case('0') Every payment @break
                                @case('1') First payment @break
                                @case('') First payment @break
                                @default First {{ $promotion->recurring }} payments
                            @endswitch
                        </td>
                        <td class="ao-num">
                            {{ $promotion->services_count }}{{ $promotion->max_uses ? ' / ' . $promotion->max_uses : '' }}
                        </td>
                        <td>{{ $promotion->starts_at?->format('j M Y') ?? '—' }}</td>
                        <td>{{ $promotion->expires_at?->format('j M Y') ?? 'Never' }}</td>
                        <td class="ao-mu-actions">
                            <button type="button" class="ao-mo-delete" title="Delete this promotion"
                                wire:click="$set('confirming', {{ $promotion->id }})">
                                <x-filament::icon icon="ri-indeterminate-circle-fill" class="ao-mu-cell-icon ao-mu-icon-red" />
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        @if ($editing !== '')
            <h3 class="ao-sub">{{ $editing === 'new' ? 'Create New Promotion' : 'Edit Promotion' }}</h3>

            <form wire:submit.prevent="save">
                <div class="ao-anc-card ao-cc-grid">
                    <div class="ao-cc-col">
                        <label class="ao-anc-row">
                            <span>Promotion Code</span>
                            <input type="text" wire:model="form.code" maxlength="255" placeholder="SUMMER25">
                        </label>

                        <label class="ao-anc-row">
                            <span>Type</span>
                            <select wire:model.live="form.type">
                                @foreach ($types as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="ao-anc-row">
                            <span>Value</span>
                            <input type="number" step="0.01" min="0"
                                @if ($form['type'] === 'percentage') max="100" @endif
                                wire:model="form.value">
                            <i class="ao-anc-hint">{{ $form['type'] === 'percentage' ? 'A percentage off, 0 to 100.' : 'An amount off, in the order currency.' }}</i>
                        </label>

                        <label class="ao-anc-row">
                            <span>Applies To</span>
                            <select wire:model="form.applies_to">
                                @foreach ($appliesTo as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <i class="ao-anc-hint">Which part of the price the discount comes off.</i>
                        </label>

                        <label class="ao-anc-row">
                            <span>Recurring</span>
                            <select wire:model.live="form.recurring">
                                @foreach ($recurringOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <i class="ao-anc-hint">How long the discount lasts once the service is running.</i>
                        </label>

                        @if ($form['recurring'] === 'n')
                            <label class="ao-anc-row">
                                <span>Number of Payments</span>
                                <input type="number" min="2" wire:model="form.recurring_n">
                            </label>
                        @endif
                    </div>

                    <div class="ao-cc-col">
                        <label class="ao-anc-row">
                            <span>Start Date</span>
                            <input type="date" wire:model="form.starts_at">
                            <i class="ao-anc-hint">Before this the checkout refuses the code. Leave empty to start now.</i>
                        </label>

                        <label class="ao-anc-row">
                            <span>Expiry Date</span>
                            <input type="date" wire:model="form.expires_at">
                            <i class="ao-anc-hint">After this the checkout refuses the code. Leave empty for never.</i>
                        </label>

                        <label class="ao-anc-row">
                            <span>Maximum Uses</span>
                            <input type="number" min="1" wire:model="form.max_uses">
                            <i class="ao-anc-hint">Across everyone. Leave empty for unlimited.</i>
                        </label>

                        <label class="ao-anc-row">
                            <span>Maximum Uses Per Client</span>
                            <input type="number" min="1" wire:model="form.max_uses_per_user">
                            <i class="ao-anc-hint">The reference's Once Per Client is this, set to 1.</i>
                        </label>

                        <div class="ao-anc-row">
                            <span>
                                Applies To Products
                                <i class="ao-anc-hint">Ctrl-click or Shift-click to choose more than one. Choose none for every product.</i>
                            </span>
                            <span class="ao-anc-field">
                                <select class="ao-ep-list" multiple size="8" wire:model="productIds">
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                                    @endforeach
                                </select>
                            </span>
                        </div>
                    </div>
                </div>

                {{-- The reference's remaining fields. None is enforced anywhere in the
                     checkout, so each says what it would need rather than pretending. --}}
                <div class="ao-anc-card">
                    <div class="ao-anc-row">
                        <span>Requires</span>
                        <span class="ao-anc-field ao-gs-off">
                            <select disabled title="The checkout checks which products a code applies to, not which must also be in the basket">
                                <option>None</option>
                            </select>
                            <i class="ao-anc-hint">Not available: a code is checked against the products it applies to, and there is no
                                second list of products the basket must also contain.</i>
                        </span>
                    </div>

                    <div class="ao-anc-row">
                        <span>Lifetime Promotion</span>
                        <span class="ao-anc-field ao-gs-off">
                            <label class="ao-check">
                                <input type="checkbox" disabled title="Recurring above already covers this">
                                <span>Check to apply for the life of the service</span>
                            </label>
                            <i class="ao-anc-hint">Set <b>Recurring</b> to "Every payment, for the life of the service" — that is this field.</i>
                        </span>
                    </div>

                    <div class="ao-anc-row">
                        <span>New Signups Only / Existing Clients Only</span>
                        <span class="ao-anc-field ao-gs-off">
                            <label class="ao-check">
                                <input type="checkbox" disabled title="The checkout does not look at how old the account is">
                                <span>Restrict by how long the client has had an account</span>
                            </label>
                            <i class="ao-anc-hint">Not available: the code is validated against the basket and the client's own use of it,
                                and nothing there reads the signup date. Maximum Uses Per Client is the restriction
                                this platform does apply.</i>
                        </span>
                    </div>

                    <div class="ao-anc-row">
                        <span>Upgrade Configuration</span>
                        <span class="ao-anc-field ao-gs-off">
                            <select disabled title="Codes are entered at checkout; an upgrade does not take one">
                                <option>None</option>
                            </select>
                            <i class="ao-anc-hint">Not available: a promotion is entered in the basket, and an upgrade is priced from the
                                old and new plans rather than going through the basket.</i>
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
                    <button type="button" class="ao-pg-btn" wire:click="cancel">Cancel Changes</button>
                </div>
            </form>
        @endif
    </div>

    @if ($confirming)
        <div class="ao-mud-overlay" wire:click.self="$set('confirming', null)">
            <div class="ao-mud ao-mud-sm" role="alertdialog" aria-modal="true">
                <div class="ao-mud-head">
                    Are you sure?
                    <button type="button" wire:click="$set('confirming', null)" aria-label="Close">&times;</button>
                </div>
                <div class="ao-mud-text">
                    <p>Delete this promotion?</p>
                    <p>If any service is still priced off this code, the deletion is refused — set an
                        expiry date instead, which stops it being accepted without changing what those
                        services pay.</p>
                </div>
                <div class="ao-mud-foot ao-mud-foot-only-right">
                    <span class="ao-mud-foot-right">
                        <button type="button" class="ao-mud-close" wire:click="$set('confirming', null)">Cancel</button>
                        <button type="button" class="ao-mud-delete" wire:click="delete">OK</button>
                    </span>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
