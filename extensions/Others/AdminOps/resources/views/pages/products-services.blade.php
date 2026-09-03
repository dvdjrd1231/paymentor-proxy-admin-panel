{{--
    Products/Services, to the reference screenshot: the Search/Filter tab, records line
    with Jump to Page and the Hide Inactive pill, the navy grid, With Selected, and the
    reference's page buttons.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        <div class="ao-tx-tabs">
            <button type="button" class="ao-mu-tab {{ $this->filter ? 'ao-on' : '' }}" wire:click="toggleFilter">
                Search/Filter
            </button>
        </div>

        @if ($this->filter)
            {{-- The reference's Search/Filter panel, field for field and in its order:
                 Product Type, Product/Service, Billing Cycle, Domain, Client Name on the
                 left; Server, Payment Method, Status, Custom Field, Custom Field Value on
                 the right. Domain is the one honestly-dead field — proxy services carry
                 none — with the reason on its title. --}}
            <form class="ao-find ao-of" autocomplete="off" wire:submit.prevent="search">
                <div class="ao-of-rows">
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-ps-category">Product Type</label>
                        <span><select @nofill id="ao-ps-category" class="ao-of-md" wire:model="category">
                            <option value="">Any</option>
                            @foreach ($categories as $row)
                                <option value="{{ $row->id }}">{{ $row->name }}</option>
                            @endforeach
                        </select></span>
                        <label class="ao-of-label" for="ao-ps-server">Server</label>
                        <span><select @nofill id="ao-ps-server" class="ao-of-md" wire:model="server">
                            <option value="">Any</option>
                            @foreach ($servers as $row)
                                <option value="{{ $row->id }}">{{ $row->name }}</option>
                            @endforeach
                        </select></span>
                    </div>
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-ps-product">Product/Service</label>
                        <span><select @nofill id="ao-ps-product" class="ao-of-md" wire:model="product">
                            <option value="">Any</option>
                            @foreach ($products as $row)
                                <option value="{{ $row->id }}">{{ $row->name }}</option>
                            @endforeach
                        </select></span>
                        <label class="ao-of-label" for="ao-ps-pay">Payment Method</label>
                        <span><select @nofill id="ao-ps-pay" class="ao-of-md" wire:model="paymentMethod">
                            <option value="">Any</option>
                            @foreach ($gateways as $row)
                                <option value="{{ $row->name }}">{{ $row->name }}</option>
                            @endforeach
                        </select></span>
                    </div>
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-ps-cycle">Billing Cycle</label>
                        <span><select @nofill id="ao-ps-cycle" class="ao-of-sm" wire:model="cycle">
                            <option value="">Any</option>
                            @foreach (['Daily', 'Weekly', 'Monthly', 'Annually', 'One Time'] as $label)
                                <option value="{{ $label }}">{{ $label }}</option>
                            @endforeach
                        </select></span>
                        <label class="ao-of-label" for="ao-ps-status">Status</label>
                        <span><select @nofill id="ao-ps-status" class="ao-of-sm" wire:model="status">
                            <option value="">Any</option>
                            <option value="pending">Pending</option>
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="cancelled">Terminated</option>
                        </select></span>
                    </div>
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-ps-domain">Domain</label>
                        <span><input id="ao-ps-domain" class="ao-of-md" type="text" disabled
                            placeholder="Not recorded"
                            title="Proxy services carry no domain, so this field cannot filter anything"></span>
                        <label class="ao-of-label" for="ao-ps-cf">Custom Field</label>
                        <span><select @nofill id="ao-ps-cf" class="ao-of-md" wire:model="cfField">
                            <option value="">Any</option>
                            @foreach ($customFields as $row)
                                <option value="{{ $row->id }}">{{ $row->name }}</option>
                            @endforeach
                        </select></span>
                    </div>
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-ps-client">Client Name</label>
                        <span><input @nofill id="ao-ps-client" class="ao-of-lg" type="text"
                            wire:model="client" placeholder="Client name or email"></span>
                        <label class="ao-of-label" for="ao-ps-cfv">Custom Field Value</label>
                        <span><input @nofill id="ao-ps-cfv" class="ao-of-lg" type="text"
                            wire:model="cfValue" placeholder="Value within the chosen field"></span>
                    </div>
                </div>
                <button type="submit" class="ao-of-go">Search</button>
            </form>
        @endif

        <div class="ao-mu-line">
            <span>
                {{ number_format($services->total()) }} Records Found{{ $services->total() > 0 ? ', Showing ' . number_format($services->firstItem()) . ' to ' . number_format($services->lastItem()) : '' }}
            </span>
            <span class="ao-mu-line-right">
                {{-- The reference's order and wording: the Jump to Page label first, the
                     Hide Inactive Clients pill between it and its select. --}}
                <label class="ao-mu-jump">
                    Jump to Page:
                </label>
                <button type="button" class="ao-mu-toggle {{ $hideInactive ? 'ao-on' : '' }}"
                    wire:click="toggleInactive">
                    <i>{{ $hideInactive ? 'ON' : 'OFF' }}</i>
                    Hide Inactive Clients ({{ number_format($hiddenCount) }})
                </button>
                <label class="ao-mu-jump">
                    <select wire:change="jump($event.target.value)">
                        @foreach (range(1, max(1, $services->lastPage())) as $number)
                            <option value="{{ $number }}" @selected($number === $services->currentPage())>{{ $number }}</option>
                        @endforeach
                    </select>
                </label>
            </span>
        </div>

        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th class="ao-mu-check"><input type="checkbox" data-ao-check-all></th>
                    <th>ID &#9662;</th>
                    <th>Product/Service</th>
                    <th>Domain</th>
                    <th>Client Name</th>
                    <th>Price</th>
                    <th>Billing Cycle</th>
                    <th>Next Due Date</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($services as $service)
                    @php
                        $edit = \App\Admin\Resources\ServiceResource::getUrl('edit', ['record' => $service->id]);
                        $summary = $service->user_id
                            ? \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $service->user_id])
                            : null;
                        $label = \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ProductsServices::statusLabel($service->status);
                        // Issue #7: the same addon-of tie the client's own service list now
                        // shows, so an addon does not read as an ordinary product here either.
                        $addon = $addonParents->get($service->id);
                    @endphp
                    <tr>
                        <td class="ao-mu-check"><input type="checkbox" data-ao-check value="{{ $service->user?->email }}"></td>
                        {{-- The reference's hop: the ID opens the client's profile on its
                             Products/Services tab with this service's editor selected. --}}
                        <td>
                            @if ($service->user_id)
                                <a href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $service->user_id, 'tab' => 'services', 'service' => $service->id]) }}">{{ $service->id }}</a>
                            @else
                                {{ $service->id }}
                            @endif
                        </td>
                        <td class="ao-mu-left">
                            <a href="{{ $edit }}">
                                @if ($addon?->parent)&#8618; @endif{{ $service->product?->name ?? '—' }}
                            </a>
                            @if ($addon?->parent)
                                <span class="ao-mu-dim">{{ __('theme.addon_of', ['service' => $addon->parent->product?->name ?? ('#' . $addon->parent->id)]) }}</span>
                            @endif
                        </td>
                        <td><span class="ao-mu-dim">(No Domain)</span></td>
                        <td>
                            @if ($summary)
                                <a href="{{ $summary }}">{{ trim(($service->user->first_name ?? '') . ' ' . ($service->user->last_name ?? '')) ?: $service->user->email }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td>${{ number_format((float) $service->price, 2) }} {{ $service->currency_code }}</td>
                        <td>{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ProductsServices::cycle($service) }}</td>
                        <td>{{ $service->expires_at?->format('m/d/Y') ?? '-' }}</td>
                        <td>
                            <span class="ao-mu-status ao-mu-st-{{ $service->status }}">{{ $label }}</span>
                        </td>
                        <td class="ao-mu-actions">
                            {{-- The reference's "+": the row opens in place; the edit screen
                                 stays one more click away, inside the strip. --}}
                            <button type="button" class="ao-ps-plus {{ $expanded === $service->id ? 'ao-on' : '' }}"
                                wire:click="expand({{ $service->id }})">{{ $expanded === $service->id ? '−' : '+' }}</button>
                        </td>
                    </tr>
                    @if ($expanded === $service->id)
                        @php
                            // Issue #4: the reference's Server/Username pair, which for this
                            // store's own proxy service is real, stored data — the ProxyPanel
                            // module's own service properties — not something to invent.
                            $props = $service->properties;
                            $username = $props->firstWhere('key', 'proxy_username')?->value;
                        @endphp
                        <tr class="ao-ps-detail">
                            <td colspan="10">
                                <div class="ao-ps-detail-grid">
                                    <dl>
                                        <dt>Registration Date</dt><dd>{{ $service->created_at?->format('m/d/Y H:i') }}</dd>
                                        <dt>First Payment</dt><dd>${{ number_format((float) $service->price, 2) }} {{ $service->currency_code }}</dd>
                                        <dt>Recurring Amount</dt><dd>${{ number_format((float) $service->price * (float) $service->quantity, 2) }} {{ $service->currency_code }}</dd>
                                        <dt>Quantity</dt><dd>{{ (int) $service->quantity }}</dd>
                                    </dl>
                                    <dl>
                                        <dt>Billing Cycle</dt><dd>{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ProductsServices::cycle($service) }}</dd>
                                        <dt>Next Due Date</dt><dd>{{ $service->expires_at?->format('m/d/Y') ?? '—' }}</dd>
                                        <dt>Order</dt><dd>#{{ $service->order_id }}</dd>
                                        <dt>Status</dt><dd>{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ProductsServices::statusLabel($service->status) }}</dd>
                                    </dl>
                                    <dl>
                                        <dt>Server</dt><dd>{{ $service->product?->server?->name ?? '—' }}</dd>
                                        <dt>Username</dt><dd>{{ $username ?? '—' }}</dd>
                                        <dt>Configuration</dt>
                                        <dd>
                                            @forelse ($service->configs as $config)
                                                {{ $config->configOption?->name ?? 'Option' }}: {{ $config->configValue?->name ?? $config->value ?? '—' }}<br>
                                            @empty
                                                No configurable options
                                            @endforelse
                                        </dd>
                                    </dl>
                                    <div class="ao-ps-detail-actions">
                                        <a class="ao-find-go" href="{{ $edit }}">Open Full Service</a>
                                        {{-- Issue #7: the reference's "+ New Addon" on the service —
                                             opens Service Addons with this service preselected. --}}
                                        <a class="ao-find-go"
                                            href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ServiceAddons::getUrl(['adding' => 1, 'service' => $service->id]) }}">
                                            &#10010; New Addon
                                        </a>
                                        @if ($summary)
                                            <a class="ao-cq-addline" href="{{ $summary }}">Client Profile</a>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="10" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="ao-mu-selected">
            With Selected:
            <button type="button" data-ao-send-message>Send Message</button>
        </div>

        <nav class="ao-mu-pages">
            <button type="button" wire:click="jump({{ $services->currentPage() - 1 }})"
                @disabled($services->onFirstPage())>&laquo; Previous Page</button>
            <span class="ao-mu-page-now">{{ $services->currentPage() }}</span>
            <button type="button" wire:click="jump({{ $services->currentPage() + 1 }})"
                @disabled(!$services->hasMorePages())>Next Page &raquo;</button>
        </nav>
    </div>

    <script>
        (() => {
            const root = document.currentScript.closest('.fi-page') ?? document;

            root.addEventListener('change', (event) => {
                if (!event.target.matches('[data-ao-check-all]')) return;
                for (const box of root.querySelectorAll('[data-ao-check]')) box.checked = event.target.checked;
            });

            root.addEventListener('click', (event) => {
                const button = event.target.closest('[data-ao-send-message]');
                if (!button) return;

                const picked = [...root.querySelectorAll('[data-ao-check]:checked')]
                    .map((box) => box.value).filter(Boolean);

                if (!picked.length) {
                    alert('Tick at least one row first.');
                    return;
                }

                window.location.href = 'mailto:' + encodeURIComponent([...new Set(picked)].join(','));
            });
        })();
    </script>
</x-filament-panels::page>
