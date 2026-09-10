{{--
    The locations console on the WHMCS window standard: Search/Filter band, navy grid,
    quiet row-action icons, "Are you sure?" modals. Uses the AdminOps skin classes —
    this deployment always runs AdminOps, and matching its standard is the point.
--}}
<x-filament-panels::page>
    <div class="ao-mu" x-data="{ filter: @js($filter) }">
        @if ($error)
            <div class="ao-ec-banner">
                <span><b>The panel could not be read.</b> {{ $error }}</span>
            </div>
        @endif

        <div class="ao-tx-tabs">
            <button type="button" class="ao-mu-tab" :class="{ 'ao-on': filter }" @click="filter = !filter">
                Search/Filter
            </button>
            <span class="ao-pl-newrow">
                <button type="button" class="ao-cp-link" wire:click="openCreate">
                    <x-filament::icon icon="ri-add-circle-fill" class="ao-ni-new-ic" />
                    Create New
                </button>
            </span>
        </div>

        <form class="ao-find ao-of" autocomplete="off" wire:submit.prevent="search" x-show="filter" x-cloak>
            <div class="ao-of-rows">
                <div class="ao-of-row ao-of-row-single">
                    <label class="ao-of-label" for="ao-pl-q">Search</label>
                    <span><input @nofill id="ao-pl-q" type="text" class="ao-of-xl" wire:model="q"
                        placeholder="Tag, country, city or continent"></span>
                </div>
                <div class="ao-of-row ao-of-row-single">
                    <label class="ao-of-label" for="ao-pl-status">Status</label>
                    <span><select @nofill id="ao-pl-status" class="ao-of-md" wire:model="status">
                        <option value="">Any</option>
                        <option value="enabled">Enabled</option>
                        <option value="disabled">Disabled</option>
                    </select></span>
                </div>
                <div class="ao-of-row ao-of-row-single">
                    <label class="ao-of-label" for="ao-pl-sellable">Availability</label>
                    <span><select @nofill id="ao-pl-sellable" class="ao-of-md" wire:model="sellable">
                        <option value="">Any</option>
                        <option value="sellable">Sellable now</option>
                        <option value="out">Out of stock</option>
                        <option value="empty">No tunnels</option>
                    </select></span>
                </div>
                <div class="ao-of-row ao-of-row-single">
                    <label class="ao-of-label" for="ao-pl-continent">Continent</label>
                    <span><select @nofill id="ao-pl-continent" class="ao-of-md" wire:model="continent">
                        <option value="">Any</option>
                        @foreach ($continents as $name)
                            <option value="{{ $name }}">{{ $name }}</option>
                        @endforeach
                    </select></span>
                </div>
            </div>
            <div class="ao-of-buttons"><button type="submit" class="ao-find-go">Search/Filter</button></div>
        </form>

        <div class="ao-mu-line">
            <span>
                {{ number_format($locations->total()) }} Records Found{{ $locations->total() > 0 ? ', Showing ' . number_format($locations->firstItem()) . ' to ' . number_format($locations->lastItem()) : '' }}
            </span>
            <label class="ao-mu-jump">
                Jump to Page:
                <select wire:change="jump($event.target.value)">
                    @foreach (range(1, max(1, $locations->lastPage())) as $number)
                        <option value="{{ $number }}" @selected($number === $locations->currentPage())>{{ $number }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th>Tag</th>
                    <th>Country</th>
                    <th>City</th>
                    <th>Continent</th>
                    <th>Tunnels</th>
                    <th>Used</th>
                    <th>Free</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($locations as $row)
                    @php
                        $tag = (string) ($row['tag'] ?? '');
                        $free = (int) ($row['free'] ?? 0);
                        $enabled = ($row['status'] ?? 'enabled') === 'enabled';
                    @endphp
                    <tr wire:key="pl-{{ $tag }}">
                        <td><b>{{ $tag }}</b></td>
                        <td>
                            {{ $row['country_name'] ?? '—' }}
                            @if (!empty($row['state']))
                                <span class="ao-mu-dim">{{ $row['state'] }}</span>
                            @endif
                        </td>
                        <td>{{ $row['city'] ?? '—' }}</td>
                        <td>{{ $row['continent'] ?? '—' }}</td>
                        <td>{{ (int) ($row['total'] ?? 0) }}</td>
                        <td>{{ (int) ($row['used'] ?? 0) }}</td>
                        {{-- Free capacity decides whether the region can be sold, so it is
                             coloured rather than left as one more figure in a row. --}}
                        <td class="{{ $free > 0 ? 'ao-st-open' : ((int) ($row['total'] ?? 0) > 0 ? 'ao-pl-danger' : 'ao-mu-dim') }}">{{ $free }}</td>
                        <td class="{{ $enabled ? 'ao-st-open' : 'ao-mu-dim' }}">{{ $enabled ? 'Enabled' : 'Disabled' }}</td>
                        <td class="ao-mu-actions ao-pl-actions">
                            <button type="button" wire:click="openView(@js($tag))" title="View — the full row with provider priorities">
                                <x-filament::icon icon="ri-eye-line" class="ao-mu-cell-icon" />
                            </button>
                            <button type="button" wire:click="askToggle(@js($tag))"
                                title="{{ $enabled ? 'Disable — remove from checkout' : 'Enable — offer at checkout again' }}">
                                <x-filament::icon :icon="$enabled ? 'ri-pause-circle-line' : 'ri-play-circle-line'" class="ao-mu-cell-icon" />
                            </button>
                            <button type="button" wire:click="openEdit(@js($tag))" title="Edit location">
                                <x-filament::icon icon="ri-edit-box-line" class="ao-mu-cell-icon" />
                            </button>
                            @if ((int) ($row['used'] ?? 0) > 0)
                                <span title="In use by {{ $row['used'] }} tunnel(s) — cannot be deleted">
                                    <x-filament::icon icon="ri-indeterminate-circle-fill" class="ao-mu-cell-icon ao-pl-dead" />
                                </span>
                            @else
                                <button type="button" wire:click="askDelete(@js($tag))" title="Delete location">
                                    <x-filament::icon icon="ri-indeterminate-circle-fill" class="ao-mu-cell-icon ao-mu-icon-red" />
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="ao-mu-none">The panel returned no locations, or it could not be reached.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="ao-mu-pager">
            <button type="button" @disabled($locations->onFirstPage()) wire:click="jump({{ $locations->currentPage() - 1 }})">&laquo; Previous</button>
            <button type="button" @disabled(!$locations->hasMorePages()) wire:click="jump({{ $locations->currentPage() + 1 }})">Next &raquo;</button>
        </div>

        @if ($viewing && $viewDetail)
            <div class="ao-mud-overlay" wire:click.self="closeModals">
                <div class="ao-mud" role="dialog" aria-modal="true">
                    <div class="ao-mud-head">
                        Location {{ $viewing }}
                        <button type="button" wire:click="closeModals" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text">
                        @include('servers.proxypanel::admin.location-detail', ['detail' => $viewDetail, 'row' => ['tag' => $viewing]])
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="closeModals">Close</button>
                        </span>
                    </div>
                </div>
            </div>
        @endif

        @if ($creating || $editing)
            <div class="ao-mud-overlay" wire:click.self="closeModals">
                <div class="ao-mud ao-pl-mud" role="dialog" aria-modal="true">
                    <div class="ao-mud-head">
                        {{ $editing ? 'Edit ' . $editing : 'New panel location' }}
                        <button type="button" wire:click="closeModals" aria-label="Close">&times;</button>
                    </div>
                    <form class="ao-mud-text" wire:submit.prevent="saveLocation">
                        <div class="ao-pl-grid">
                            @foreach ([['continent', 'Continent', 'Europe'], ['country', 'Country code', 'DE'], ['country_name', 'Country name', 'Germany'], ['state', 'State', 'North Rhine-Westphalia'], ['city', 'City', 'Bonn'], ['region_code', 'Region code', 'DE-NW'], ['zip_code', 'ZIP code', '']] as [$key, $label, $hint])
                                <label class="ao-pl-field">
                                    <span>{{ $label }}</span>
                                    <input type="text" wire:model="form.{{ $key }}" placeholder="{{ $hint }}">
                                </label>
                            @endforeach
                        </div>

                        @foreach (\Paymenter\Extensions\Servers\ProxyPanel\Admin\Pages\PanelLocations::PROVIDERS as $key => $provider)
                            <h4 class="ao-pl-provider">{{ $provider['label'] }} regions
                                <i>Tried in order when a tunnel is built here — exactly {{ $provider['length'] }} characters each.</i></h4>
                            <div class="ao-pl-grid ao-pl-grid3">
                                @foreach ([1, 2, 3] as $n)
                                    <label class="ao-pl-field">
                                        <span>Priority {{ $n }}</span>
                                        <input type="text" wire:model="form.{{ $key }}_prio{{ $n }}"
                                            placeholder="{{ $provider['example'] }}" maxlength="{{ $provider['length'] }}">
                                    </label>
                                @endforeach
                            </div>
                        @endforeach

                        @if ($errors->any())
                            <ul class="ao-anc-errors">
                                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                            </ul>
                        @endif

                        <div class="ao-mud-foot ao-mud-foot-only-right">
                            <span class="ao-mud-foot-right">
                                <button type="button" class="ao-mud-close" wire:click="closeModals">Cancel</button>
                                <button type="submit" class="ao-find-go">{{ $editing ? 'Save' : 'Create' }}</button>
                            </span>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if ($confirmToggle)
            <div class="ao-mud-overlay" wire:click.self="closeModals">
                <div class="ao-mud ao-mud-sm" role="alertdialog" aria-modal="true">
                    <div class="ao-mud-head">
                        Are you sure?
                        <button type="button" wire:click="closeModals" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text">
                        @if ($confirmToggle['enable'])
                            <p>Enable {{ $confirmToggle['tag'] }}?</p>
                            <p>It is offered at checkout again, if it has free tunnels.</p>
                        @else
                            <p>Disable {{ $confirmToggle['tag'] }}?</p>
                            <p>Disabling removes this location from checkout. Services already running there are not affected.</p>
                        @endif
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="closeModals">Cancel</button>
                            <button type="button" class="ao-find-go" wire:click="runToggle">OK</button>
                        </span>
                    </div>
                </div>
            </div>
        @endif

        @if ($confirmDelete)
            <div class="ao-mud-overlay" wire:click.self="closeModals">
                <div class="ao-mud ao-mud-sm" role="alertdialog" aria-modal="true">
                    <div class="ao-mud-head">
                        Are you sure?
                        <button type="button" wire:click="closeModals" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text">
                        <p>Delete {{ $confirmDelete }} from the panel?</p>
                        <p>It cannot be undone from here.</p>
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="closeModals">Cancel</button>
                            <button type="button" class="ao-mud-delete" wire:click="runDelete">Delete</button>
                        </span>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <style>
        .ao-pl-newrow { margin-left: auto; display: inline-flex; align-items: center; }
        .ao-pl-newrow .ao-cp-link { display: inline-flex; align-items: center; gap: 0.3rem; }
        .ao-pl-actions { white-space: nowrap; }
        .ao-pl-danger { color: #cc0000; }
        .ao-pl-dead { opacity: 0.35; cursor: not-allowed; }
        .ao-pl-mud { width: min(46rem, calc(100vw - 2rem)); }
        .ao-pl-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.6rem 1rem; }
        .ao-pl-grid3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .ao-pl-field { display: flex; flex-direction: column; gap: 0.25rem; }
        .ao-pl-field > span { font-weight: 600; font-size: 0.88rem; }
        .ao-pl-field input {
            height: 1.9rem;
            padding: 0 0.5rem;
            border: 1px solid var(--wa-border, #ccc);
            border-radius: 4px;
            font: inherit;
            font-size: 0.9rem;
        }
        .ao-pl-provider { margin: 0.9rem 0 0.4rem; font-size: 0.95rem; }
        .ao-pl-provider i { font-weight: 400; font-style: normal; color: var(--wa-muted, #6b6b6b); font-size: 0.85rem; margin-left: 0.4rem; }
    </style>
</x-filament-panels::page>
