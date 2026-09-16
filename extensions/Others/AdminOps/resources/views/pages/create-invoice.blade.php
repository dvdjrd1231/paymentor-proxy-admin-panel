{{--
    Create Invoice, in the admin's own skin rather than core's Filament form, and carrying
    the client it was opened for (Leandro, issue #53). Its shape follows the reference's
    invoice screen: the facts above, the line-item ladder below, the buttons under both.
--}}
<x-filament-panels::page>
    <form class="ao-mu ao-ci" wire:submit.prevent="save">
        <h4 class="ao-ano-heading">Invoice Details</h4>
        <div class="ao-anc-card">
            <div class="ao-of-rows">
                <div class="ao-of-row">
                    <label class="ao-of-label" for="ao-ci-user">Client</label>
                    <span>
                        <select id="ao-ci-user" class="ao-of-lg" wire:model.live="userId" required>
                            <option value="">Start Typing to Search Clients</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}">
                                    {{ trim($client->first_name . ' ' . $client->last_name) ?: $client->email }} - #{{ $client->id }}
                                </option>
                            @endforeach
                        </select>
                        @error('userId')<span class="ao-err">{{ $message }}</span>@enderror
                    </span>
                    <label class="ao-of-label" for="ao-ci-num">Invoice Number</label>
                    <span class="ao-eo-fact"><i>Generated automatically on save</i></span>
                </div>
                <div class="ao-of-row">
                    <label class="ao-of-label" for="ao-ci-issued">Issued At</label>
                    <span><input id="ao-ci-issued" class="ao-of-md" type="date" wire:model="issuedAt"></span>
                    <label class="ao-of-label" for="ao-ci-due">Due At</label>
                    <span>
                        <input id="ao-ci-due" class="ao-of-md" type="date" wire:model="dueAt">
                        @error('dueAt')<span class="ao-err">{{ $message }}</span>@enderror
                    </span>
                </div>
                <div class="ao-of-row">
                    <label class="ao-of-label" for="ao-ci-status">Status</label>
                    <span>
                        <select id="ao-ci-status" class="ao-of-sm" wire:model="status">
                            @foreach (\Paymenter\Extensions\Others\AdminOps\Admin\Pages\CreateInvoice::STATUSES as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </span>
                    <label class="ao-of-label" for="ao-ci-cur">Currency</label>
                    <span>
                        <select id="ao-ci-cur" class="ao-of-sm" wire:model="currencyCode">
                            @foreach ($currencies as $code)
                                <option value="{{ $code }}">{{ $code }}</option>
                            @endforeach
                        </select>
                    </span>
                </div>
                <div class="ao-of-row">
                    <span class="ao-of-label"></span>
                    <span>
                        <label class="ao-check">
                            <input type="checkbox" wire:model="sendEmail">
                            <span title="Core's own switch: whether the client is told the invoice exists">Send Email</span>
                        </label>
                    </span>
                    <span class="ao-of-label"></span>
                    <span></span>
                </div>
            </div>
        </div>

        <h4 class="ao-ano-heading">Invoice Items</h4>
        <div class="ao-mu-wrap">
            <table class="ao-mu-grid ao-ci-items">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Quantity</th>
                        <th>Amount</th>
                        <th></th>
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
                            <td><input type="number" min="1" class="ao-ei-qty-in" wire:model="items.{{ $index }}.quantity" aria-label="Quantity"></td>
                            <td>
                                <input type="text" inputmode="decimal" class="ao-ei-amount-in" wire:model="items.{{ $index }}.price" aria-label="Amount">
                                @error("items.$index.price")<span class="ao-err">{{ $message }}</span>@enderror
                            </td>
                            <td class="ao-mu-actions">
                                @if (count($items) > 1)
                                    <button type="button" class="ao-ano-remove" title="Remove this line"
                                        wire:click="removeItem({{ $index }})">&times;</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="ao-of-buttons">
            <button type="button" class="ao-pg-btn" wire:click="addItem">Add Item</button>
        </div>

        <div class="ao-of-buttons">
            <button type="submit" class="ao-find-go">Create Invoice</button>
            <a class="ao-of-go" href="{{ $for ? \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $for]) : \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageInvoices::getUrl() }}">Cancel</a>
        </div>
    </form>
</x-filament-panels::page>
