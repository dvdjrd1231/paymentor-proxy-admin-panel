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
            <form class="ao-find" autocomplete="off" wire:submit.prevent="$refresh">
                <span class="ao-find-glass" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" width="18" height="18">
                        <circle cx="9" cy="9" r="5.5" /><path d="M13.5 13.5 17 17" />
                    </svg>
                </span>
                <div class="ao-find-fields">
                    <label class="ao-find-field ao-find-grow">
                        <span class="ao-find-label">Client or Description</span>
                        <input @nofill type="search" wire:model.live.debounce.500ms="q" placeholder="Client name, email or description">
                    </label>
                </div>
                <button type="submit" class="ao-find-go">Search</button>
            </form>
        @endif

        @if ($adding)
            <form class="ao-anc-card" wire:submit.prevent="create">
                <label class="ao-anc-row">
                    <span>Client</span>
                    <select class="ao-w-45" wire:model="userId" required>
                        <option value="">Start Typing to Search Clients</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}">{{ trim($client->first_name . ' ' . $client->last_name) ?: $client->email }} - #{{ $client->id }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ao-anc-row">
                    <span>Description</span>
                    <input type="text" class="ao-w-60" wire:model="description" placeholder="e.g. Setup assistance, 2 hours" required>
                </label>
                <label class="ao-anc-row">
                    <span>Hours/Qty</span>
                    <input type="number" step="0.01" min="0.01" class="ao-w-25" wire:model="quantity" required>
                </label>
                <label class="ao-anc-row">
                    <span>Amount</span>
                    <input type="text" inputmode="decimal" class="ao-w-25" wire:model="amount" placeholder="0.00" required>
                </label>
                <label class="ao-anc-row">
                    <span>Invoice Action</span>
                    <select class="ao-w-40" wire:model="action">
                        @foreach ($actions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ao-anc-row">
                    <span>Recur</span>
                    <select class="ao-w-25" wire:model="recur">
                        <option value="">Never</option>
                        <option value="week">Every week</option>
                        <option value="month">Every month</option>
                        <option value="quarter">Every quarter</option>
                        <option value="year">Every year</option>
                    </select>
                </label>
                <label class="ao-anc-row">
                    <span>Due Date</span>
                    <input type="date" wire:model="dueDate">
                </label>
                <div class="ao-pr-center"><button type="submit" class="ao-find-go">Save Changes</button></div>
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
