{{--
    The reference's invoice screen (user request, 2026-09-04): the facts band, the line
    items with the Sub Total / Amount Paid / Balance Due ladder, the transactions list,
    and Add Payment. Paid is not a dropdown state — money arriving is what pays an
    invoice, and Add Payment is how money arrives.
--}}
<x-filament-panels::page>
    <div class="ao-mu ao-eo">
        <div class="ao-tx-tabs">
            <a class="ao-mu-tab" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageInvoices::getUrl() }}">&laquo; Back to List</a>
        </div>

        <div class="ao-find ao-of">
            <div class="ao-of-rows">
                <div class="ao-of-row">
                    <span class="ao-of-label">Client</span>
                    <span class="ao-eo-fact"><a class="ao-link" href="{{ $clientUrl }}">{{ $clientName }}</a></span>
                    <span class="ao-of-label">Status</span>
                    <span class="ao-eo-fact">
                        <span class="ao-mu-status {{ ['paid' => 'ao-mu-st-active', 'pending' => 'ao-mu-st-unpaid', 'cancelled' => 'ao-mu-st-cancelled'][$invoice->status] ?? '' }}">
                            {{ ['paid' => 'Paid', 'pending' => 'Unpaid', 'cancelled' => 'Cancelled'][$invoice->status] ?? ucfirst($invoice->status) }}
                        </span>
                    </span>
                </div>
                <div class="ao-of-row">
                    <span class="ao-of-label">Invoice Date</span>
                    <span class="ao-eo-fact">{{ $invoice->created_at?->format('m/d/Y') }}</span>
                    <span class="ao-of-label">Change Status</span>
                    <span class="ao-eo-fact">
                        <select class="ao-of-md" wire:change="setStatus($event.target.value)"
                            title="Paid is not set by hand — record the money under Add Payment below and the invoice pays itself">
                            <option value="" @selected(true)>— pick an action —</option>
                            <option value="pending">Mark Unpaid</option>
                            <option value="cancelled">Cancel Invoice</option>
                        </select>
                    </span>
                </div>
                <div class="ao-of-row">
                    <label class="ao-of-label" for="ao-ei-due">Due Date</label>
                    @include('adminops::partials.datepicker', [
                        'model' => 'dueAt', 'range' => false, 'id' => 'ao-ei-due',
                        'placeholder' => 'MM/DD/YYYY', 'class' => 'ao-of-md',
                    ])
                    <span class="ao-of-label">Payment Method</span>
                    <span class="ao-eo-fact">{{ $invoice->transactions->first()?->gateway?->name ?? '—' }}</span>
                </div>
            </div>
        </div>

        <h4 class="ao-ano-heading">Invoice Items</h4>
        <form wire:submit.prevent="save">
            <table class="ao-mu-grid">
                <thead>
                    <tr><th>Description</th><th class="ao-ei-qty">Quantity</th><th class="ao-ei-amount">Amount</th></tr>
                </thead>
                <tbody>
                    @forelse ($items as $index => $item)
                        <tr>
                            <td class="ao-mu-left">
                                <input type="text" class="ao-ei-desc" wire:model="items.{{ $index }}.description"
                                    aria-label="Line description">
                            </td>
                            <td><input type="number" min="1" class="ao-ei-qty-in" wire:model="items.{{ $index }}.quantity" aria-label="Quantity"></td>
                            <td><input type="text" inputmode="decimal" class="ao-ei-amount-in" wire:model="items.{{ $index }}.price" aria-label="Amount"></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="ao-mu-none ao-mu-left">No Records Found</td></tr>
                    @endforelse
                    <tr class="ao-eo-total">
                        <td></td>
                        <td class="ao-eo-total-label">Sub Total:</td>
                        <td class="ao-eo-total-value">${{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency_code }}</td>
                    </tr>
                    <tr class="ao-eo-total">
                        <td></td>
                        <td class="ao-eo-total-label">Amount Paid:</td>
                        <td class="ao-eo-total-value">${{ number_format($paid, 2) }} {{ $invoice->currency_code }}</td>
                    </tr>
                    <tr class="ao-eo-total">
                        <td></td>
                        <td class="ao-eo-total-label">Balance Due:</td>
                        <td class="ao-eo-total-value">${{ number_format(max(0, (float) $invoice->remaining), 2) }} {{ $invoice->currency_code }}</td>
                    </tr>
                </tbody>
            </table>

            @if ($errors->any())
                <ul class="ao-anc-errors">
                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            @endif

            <div class="ao-of-buttons">
                <button type="submit" class="ao-find-go">Save Changes</button>
            </div>
        </form>

        <h4 class="ao-ano-heading">Transactions</h4>
        <table class="ao-mu-grid">
            <thead>
                <tr><th>Date</th><th>Payment Method</th><th>Transaction ID</th><th>Amount</th></tr>
            </thead>
            <tbody>
                @forelse ($invoice->transactions as $transaction)
                    <tr>
                        <td>{{ $transaction->created_at?->format('m/d/Y H:i') }}</td>
                        <td>{{ $transaction->is_credit_transaction ? 'Account credit' : ($transaction->gateway?->name ?? '—') }}</td>
                        <td class="ao-mu-left">{{ $transaction->transaction_id ?: '—' }}</td>
                        <td>${{ number_format((float) $transaction->amount, 2) }} {{ $invoice->currency_code }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="ao-mu-none ao-mu-left">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        <h4 class="ao-ano-heading">Add Payment</h4>
        <form class="ao-anc-card" wire:submit.prevent="addPayment">
            <div class="ao-anc-row">
                <span>Date:</span>
                <span class="ao-eo-fact">{{ now()->format('m/d/Y') }}</span>
            </div>
            <label class="ao-anc-row">
                <span>Amount:</span>
                <input type="text" class="ao-w-25" inputmode="decimal" wire:model="pay.amount">
            </label>
            <label class="ao-anc-row">
                <span>Fees:</span>
                <span class="ao-anc-field">
                    <input type="text" class="ao-w-25" inputmode="decimal" wire:model="pay.fee" placeholder="0.00">
                    <i>(Optional — the gateway's cut, for the income report)</i>
                </span>
            </label>
            <label class="ao-anc-row">
                <span>Transaction ID:</span>
                <input type="text" class="ao-w-40" wire:model="pay.transactionId" placeholder="Optional — the gateway or bank reference">
            </label>
            <label class="ao-anc-row">
                <span>Payment Method:</span>
                <select class="ao-w-25" wire:model="pay.gateway">
                    <option value="">Manually recorded</option>
                    @foreach ($gateways as $gateway)
                        <option value="{{ $gateway->extension }}">{{ $gateway->name }}</option>
                    @endforeach
                </select>
            </label>
            <div class="ao-pr-center"><button type="submit" class="ao-find-go">Add Payment</button></div>
        </form>

        <div class="ao-eo-actions">
            <button type="button" class="ao-eo-delete" wire:click="$set('confirming', 'delete')">Delete Invoice</button>
        </div>

        @if ($confirming)
            <div class="ao-mud-overlay" wire:click.self="$set('confirming', null)">
                <div class="ao-mud ao-mud-sm" role="alertdialog" aria-modal="true">
                    <div class="ao-mud-head">
                        Are you sure?
                        <button type="button" wire:click="$set('confirming', null)" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text">
                        <p>Delete invoice #{{ $invoice->number ?: $invoice->id }}?</p>
                        <p>Its line items go with it. Payments already recorded stay in the ledger.</p>
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="$set('confirming', null)">Cancel</button>
                            <button type="button" class="ao-mud-delete" wire:click="runDelete">Delete</button>
                        </span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
