{{--
    Products/Services, to the reference screenshot: the Search/Filter tab, records line
    with Jump to Page and the Hide Inactive pill, the navy grid, With Selected, and the
    reference's page buttons.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        <button type="button" class="ao-mu-tab {{ $this->filter ? 'ao-on' : '' }}" wire:click="toggleFilter">
            Search/Filter
        </button>

        @if ($this->filter)
            <form class="ao-find" autocomplete="off" wire:submit.prevent="search">
                <span class="ao-find-glass" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" width="18" height="18">
                        <circle cx="9" cy="9" r="5.5" /><path d="M13.5 13.5 17 17" />
                    </svg>
                </span>

                <div class="ao-find-fields">
                    <label class="ao-find-field ao-find-wide">
                        <span class="ao-find-label">Product/Service</span>
                        <input @nofill type="search" wire:model="product" placeholder="Product name">
                    </label>
                    <label class="ao-find-field ao-find-wide">
                        <span class="ao-find-label">Client Name/Email</span>
                        <input @nofill type="search" wire:model="client" placeholder="Client name or email">
                    </label>
                    <label class="ao-find-field">
                        <span class="ao-find-label">Status</span>
                        <select @nofill wire:model="status">
                            <option value="">Any</option>
                            <option value="pending">Pending</option>
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="cancelled">Terminated</option>
                        </select>
                    </label>
                    <label class="ao-find-field">
                        <span class="ao-find-label">Product Type</span>
                        <select @nofill wire:model="category">
                            <option value="">Any</option>
                            @foreach ($categories as $row)
                                <option value="{{ $row->id }}">{{ $row->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="ao-find-field">
                        <span class="ao-find-label">Billing Cycle</span>
                        <select @nofill wire:model="cycle">
                            <option value="">Any</option>
                            @foreach (['Daily', 'Weekly', 'Monthly', 'Annually', 'One Time'] as $label)
                                <option value="{{ $label }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="ao-find-field">
                        <span class="ao-find-label">Server</span>
                        <select @nofill wire:model="server">
                            <option value="">Any</option>
                            @foreach ($servers as $row)
                                <option value="{{ $row->id }}">{{ $row->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <button type="submit" class="ao-find-go">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" width="13" height="13" aria-hidden="true">
                        <circle cx="9" cy="9" r="5.5" /><path d="M13.5 13.5 17 17" />
                    </svg>
                    Search
                </button>
            </form>
        @endif

        <div class="ao-mu-line">
            <span>
                {{ number_format($services->total()) }} Records Found{{ $services->total() > 0 ? ', Showing ' . number_format($services->firstItem()) . ' to ' . number_format($services->lastItem()) : '' }}
            </span>
            <span class="ao-mu-line-right">
                <button type="button" class="ao-mu-toggle {{ $hideInactive ? 'ao-on' : '' }}"
                    wire:click="toggleInactive">
                    <i>{{ $hideInactive ? 'ON' : 'OFF' }}</i>
                    Hide Inactive ({{ number_format($hiddenCount) }})
                </button>
                <label class="ao-mu-jump">
                    Jump to Page:
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
                        <td>{{ $service->id }}</td>
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
