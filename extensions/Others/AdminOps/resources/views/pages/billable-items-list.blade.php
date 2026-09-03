{{-- Billable Items, to issue #13: the list, its views, and the reference's Add New form. --}}
<x-filament-panels::page>
    <div class="ao-mu">
        <div class="ao-tx-tabs">
            <button type="button" class="ao-mu-tab {{ $adding ? 'ao-on' : '' }}" wire:click="toggleAdding">Add New</button>
            <button type="button" class="ao-mu-tab {{ $tab === 'all' ? 'ao-on' : '' }}" wire:click="$set('tab', 'all')">List All Billable Items</button>
            <button type="button" class="ao-mu-tab {{ $tab === 'uninvoiced' ? 'ao-on' : '' }}" wire:click="$set('tab', 'uninvoiced')">Uninvoiced Items</button>
            <button type="button" class="ao-mu-tab {{ $tab === 'recurring' ? 'ao-on' : '' }}" wire:click="$set('tab', 'recurring')">Recurring Items</button>
            <button type="button" class="ao-mu-tab {{ $filter ? 'ao-on' : '' }}" wire:click="toggleFilter">Search/Filter</button>
        </div>

        @if ($filter)
            {{-- The reference's Search/Filter panel, field for field: Client, Description
                 on the left; Amount, Status on the right. --}}
            <form class="ao-find ao-of" autocomplete="off" wire:submit.prevent="$refresh">
                <div class="ao-of-rows">
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-bi-client">Client</label>
                        <span><input @nofill id="ao-bi-client" class="ao-of-lg" type="text"
                            wire:model.live.debounce.500ms="client" placeholder="Start Typing to Search Clients"></span>
                        <label class="ao-of-label" for="ao-bi-amount">Amount</label>
                        <span><input @nofill id="ao-bi-amount" class="ao-of-sm" type="text" inputmode="decimal"
                            wire:model.live.debounce.500ms="famount" placeholder="0.00"></span>
                    </div>
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-bi-q">Description</label>
                        <span><input @nofill id="ao-bi-q" class="ao-of-lg" type="text"
                            wire:model.live.debounce.500ms="q" placeholder="Words from the description"></span>
                        <label class="ao-of-label" for="ao-bi-status">Status</label>
                        <span><select @nofill id="ao-bi-status" class="ao-of-md" wire:model.live="status">
                            <option value="">Any</option>
                            <option value="uninvoiced">Uninvoiced</option>
                            <option value="invoiced">Invoiced</option>
                            <option value="recurring">Recurring</option>
                        </select></span>
                    </div>
                </div>
                <button type="submit" class="ao-of-go">Search/Filter</button>
            </form>
        @endif

        @if ($adding)
            {{-- The reference's Add Billable Item, row for row: Client, Product/Service,
                 Description, Hours/Qty, Amount, the Invoice Action radios with the
                 recurrence inline, the (Next) Due Date calendar, Invoice Count. The two
                 controls this billing model cannot honour are dead with the reason on
                 their titles. --}}
            <form class="ao-find ao-of" autocomplete="off" wire:submit.prevent="create">
                <div class="ao-of-rows">
                    <div class="ao-of-row ao-of-row-single">
                        <label class="ao-of-label" for="ao-bi-user">Client</label>
                        <span><select id="ao-bi-user" class="ao-of-lg" wire:model.live="userId" required>
                            <option value="">Start Typing to Search Clients</option>
                            @foreach ($clients as $row)
                                <option value="{{ $row->id }}">{{ trim($row->first_name . ' ' . $row->last_name) ?: $row->email }} - #{{ $row->id }}</option>
                            @endforeach
                        </select></span>
                    </div>
                    <div class="ao-of-row ao-of-row-single">
                        <label class="ao-of-label" for="ao-bi-service">Product/Service</label>
                        <span><select id="ao-bi-service" class="ao-of-lg" wire:model="serviceId"
                            @disabled(!$userId)>
                            <option value="">None</option>
                            @foreach ($clientServices as $service)
                                <option value="{{ $service->id }}">#{{ $service->id }} · {{ $service->product?->name }}</option>
                            @endforeach
                        </select></span>
                    </div>
                    <div class="ao-of-row ao-of-row-single">
                        <label class="ao-of-label" for="ao-bi-desc">Description</label>
                        <span><input id="ao-bi-desc" class="ao-of-lg" type="text" wire:model="description"
                            placeholder="e.g. Setup assistance, 2 hours" required></span>
                    </div>
                    <div class="ao-of-row ao-of-row-single">
                        <label class="ao-of-label" for="ao-bi-qty">Hours/Qty</label>
                        <span><input id="ao-bi-qty" class="ao-of-sm" type="number" step="0.01" min="0.01"
                            wire:model="quantity" required></span>
                    </div>
                    <div class="ao-of-row ao-of-row-single">
                        <label class="ao-of-label" for="ao-bi-amt">Amount</label>
                        <span><input id="ao-bi-amt" class="ao-of-sm" type="text" inputmode="decimal"
                            wire:model="amount" placeholder="0.00" required></span>
                    </div>
                    <div class="ao-of-row ao-of-row-single">
                        <span class="ao-of-label">Invoice Action</span>
                        <span class="ao-of-stack">
                            <label class="ao-of-check">
                                <input type="radio" value="{{ \Paymenter\Extensions\Others\BillableItems\Models\BillableItem::ACTION_HOLD }}" wire:model="action">
                                Don't Invoice for Now
                            </label>
                            <label class="ao-of-check">
                                <input type="radio" value="{{ \Paymenter\Extensions\Others\BillableItems\Models\BillableItem::ACTION_IMMEDIATELY }}" wire:model="action">
                                Invoice on Next Cron Run
                            </label>
                            <label class="ao-of-check">
                                <input type="radio" value="{{ \Paymenter\Extensions\Others\BillableItems\Models\BillableItem::ACTION_NEXT_INVOICE }}" wire:model="action">
                                Add to User's Next Invoice
                            </label>
                            <label class="ao-of-check" title="Paymenter invoices from the daily run or the next invoice — there is no per-item due-date invoicing to schedule">
                                <input type="radio" disabled>
                                <s>Invoice as Normal for Due Date</s>
                            </label>
                            <span class="ao-of-inline">
                                Recur Every
                                <select class="ao-of-sm" wire:model="recur">
                                    <option value="">Never</option>
                                    <option value="week">Week</option>
                                    <option value="month">Month</option>
                                    <option value="quarter">Quarter</option>
                                    <option value="year">Year</option>
                                </select>
                                for
                                <input class="ao-of-sm" type="text" disabled placeholder="∞"
                                    title="A recurrence here runs until the item is removed — no times counter is stored">
                                Times
                            </span>
                        </span>
                    </div>
                    <div class="ao-of-row ao-of-row-single">
                        <label class="ao-of-label" for="ao-bi-due">(Next) Due Date</label>
                        @include('adminops::partials.datepicker', [
                            'model' => 'dueDate', 'range' => false, 'id' => 'ao-bi-due',
                            'placeholder' => 'MM/DD/YYYY', 'class' => 'ao-of-md',
                        ])
                    </div>
                    <div class="ao-of-row ao-of-row-single">
                        <label class="ao-of-label" for="ao-bi-count">Invoice Count</label>
                        <span><input id="ao-bi-count" class="ao-of-sm" type="text" disabled placeholder="0"
                            title="Counted from the invoices actually raised — not an editable number"></span>
                    </div>
                </div>
                <div class="ao-of-buttons">
                    <button type="submit" class="ao-find-go">Save Changes</button>
                </div>
            </form>
            @if ($errors->any())
                <ul class="ao-anc-errors">
                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            @endif
        @endif

        <div class="ao-mu-line"><span>{{ number_format($items->count()) }} Records Found, Page 1 of 1</span></div>

        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th class="ao-mu-check"><input type="checkbox" data-ao-check-all></th>
                    <th>ID &#9662;</th><th>Client Name</th><th>Description</th><th>Hours</th><th>Amount</th>
                    <th>Invoice Action</th><th>Invoiced</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td class="ao-mu-check"><input type="checkbox" data-ao-check wire:model="selected" value="{{ $item->id }}"></td>
                        <td>{{ $item->id }}</td>
                        <td>
                            <a href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $item->user_id]) }}">
                                {{ trim(($item->user->first_name ?? '') . ' ' . ($item->user->last_name ?? '')) ?: ($item->user->email ?? '—') }}
                            </a>
                        </td>
                        <td class="ao-mu-left">{{ $item->description }}</td>
                        <td>{{ (float) $item->quantity }}</td>
                        <td>${{ number_format((float) $item->amount, 2) }} {{ $item->currency_code }}</td>
                        <td>{{ $actions[$item->invoice_action] ?? $item->invoice_action }}</td>
                        <td title="{{ $item->recur_every ? 'Recurs every ' . $item->recur_every : 'Does not recur' }}{{ $item->next_due_at ? ' · next ' . $item->next_due_at->format('m/d/Y') : '' }}">
                            <span class="{{ $item->invoiced_at ? 'ao-st-open' : 'ao-st-answered' }}">
                                {{ $item->invoiced_at ? 'Yes' : 'No' }}
                            </span>
                        </td>
                        <td class="ao-mu-actions">
                            @unless ($item->invoiced_at)
                                <button type="button" class="ao-mo-delete" title="Delete item"
                                    wire:click="delete({{ $item->id }})" wire:confirm="Delete this billable item?">
                                    <x-filament::icon icon="ri-indeterminate-circle-fill" class="ao-mu-cell-icon" />
                                </button>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        {{-- The reference's With Selected bar. --}}
        <div class="ao-st-bulk">
            With Selected:
            <button type="button" wire:click="invoiceSelected"
                wire:confirm="Queue the selected items for the next daily invoice run?">Invoice on Next Cron Run</button>
            <button type="button" class="ao-st-danger" wire:click="deleteSelected"
                wire:confirm="Delete the selected items? Invoiced ones are kept.">Delete</button>
        </div>

        <nav class="ao-mu-pages">
            <button type="button" disabled>&laquo; Previous Page</button>
            <span class="ao-mu-page-now">1</span>
            <button type="button" disabled>Next Page &raquo;</button>
        </nav>
    </div>

    <script>
        (() => {
            const root = document.currentScript.closest('.fi-page') ?? document;
            root.addEventListener('change', (event) => {
                if (!event.target.matches('[data-ao-check-all]')) return;
                for (const box of root.querySelectorAll('[data-ao-check]')) {
                    if (box.checked !== event.target.checked) {
                        box.checked = event.target.checked;
                        box.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            });
        })();
    </script>
</x-filament-panels::page>
