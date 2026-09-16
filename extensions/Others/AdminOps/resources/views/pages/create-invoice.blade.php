{{--
    Create Invoice, carrying the client it was opened for (Leandro, issue #53).

    Deliberately the same furniture as the invoice screen next door: the banded facts card
    on the left, the navy Invoice Items ladder with Add Item above it, and the centred
    button row. A create screen that looked like a different product from the invoice it
    makes was the first thing wrong with it.
--}}
<x-filament-panels::page>
    <form class="ao-mu ao-ei" wire:submit.prevent="save">
        <div class="ao-ei-summary">
            <div class="ao-anc-card ao-ei-facts">
                <div class="ao-anc-row">
                    <span>Client Name</span>
                    <span class="ao-eo-fact">
                        <select class="ao-of-lg" wire:model.live="userId" required>
                            <option value="">Start Typing to Search Clients</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}">
                                    {{ trim($client->first_name . ' ' . $client->last_name) ?: $client->email }} - #{{ $client->id }}
                                </option>
                            @endforeach
                        </select>
                        @error('userId')<span class="ao-err">{{ $message }}</span>@enderror
                    </span>
                </div>
                <div class="ao-anc-row">
                    <span>Invoice Date</span>
                    <span class="ao-eo-fact"><input class="ao-of-md" type="date" wire:model="issuedAt"></span>
                </div>
                <div class="ao-anc-row">
                    <span>Due Date</span>
                    <span class="ao-eo-fact">
                        <input class="ao-of-md" type="date" wire:model="dueAt">
                        @error('dueAt')<span class="ao-err">{{ $message }}</span>@enderror
                    </span>
                </div>
                <div class="ao-anc-row">
                    <span>Status</span>
                    <span class="ao-eo-fact">
                        <select class="ao-of-sm" wire:model="status">
                            @foreach (\Paymenter\Extensions\Others\AdminOps\Admin\Pages\CreateInvoice::STATUSES as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </span>
                </div>
                <div class="ao-anc-row">
                    <span>Currency</span>
                    <span class="ao-eo-fact">
                        <select class="ao-of-sm" wire:model="currencyCode">
                            @foreach ($currencies as $code)
                                <option value="{{ $code }}">{{ $code }}</option>
                            @endforeach
                        </select>
                    </span>
                </div>
                <div class="ao-anc-row">
                    <span>Invoice Number</span>
                    <span class="ao-eo-fact"><i>Generated on save</i></span>
                </div>
                <div class="ao-anc-row">
                    <span>Send Email</span>
                    <span class="ao-eo-fact">
                        <label class="ao-check">
                            <input type="checkbox" wire:model="sendEmail">
                            <span title="Core's own switch: whether the client is told the invoice exists">Tell the client this invoice exists</span>
                        </label>
                    </span>
                </div>
            </div>
        </div>

        <div class="ao-ei-items-head">
            <h4 class="ao-ano-heading">Invoice Items</h4>
            <button type="button" class="ao-pg-btn" wire:click="addItem">Add Item</button>
        </div>

        <table class="ao-mu-grid ao-ei-grid">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="ao-ei-qty">Quantity</th>
                    <th class="ao-ei-amount">Amount</th>
                    <th class="ao-ei-del"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $index => $row)
                    <tr wire:key="ci-item-{{ $index }}">
                        <td>
                            <input type="text" class="ao-ei-desc" wire:model="items.{{ $index }}.description"
                                placeholder="What this line is for" aria-label="Description">
                            @error("items.$index.description")<span class="ao-err">{{ $message }}</span>@enderror
                        </td>
                        <td class="ao-ei-qty">
                            <input type="number" min="1" class="ao-ei-qty-in" wire:model="items.{{ $index }}.quantity" aria-label="Quantity">
                        </td>
                        <td class="ao-ei-amount">
                            <input type="text" inputmode="decimal" class="ao-ei-amount-in" wire:model="items.{{ $index }}.price" aria-label="Amount">
                            @error("items.$index.price")<span class="ao-err">{{ $message }}</span>@enderror
                        </td>
                        <td class="ao-ei-del">
                            @if (count($items) > 1)
                                <button type="button" class="ao-ei-remove" title="Remove this line"
                                    wire:click="removeItem({{ $index }})">&bull;</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="ao-pr-center">
            <button type="submit" class="ao-find-go">Create Invoice</button>
            <a class="ao-of-go" href="{{ $for ? \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $for]) : \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageInvoices::getUrl() }}">Cancel</a>
        </div>
    </form>
</x-filament-panels::page>
