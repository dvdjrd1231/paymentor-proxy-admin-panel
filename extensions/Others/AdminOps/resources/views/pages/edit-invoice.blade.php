{{--
    The reference's invoice screen (Leandro, 2026-09-07, screenshots of invoices.php): the
    tab strip over the shared Invoice Items ladder, then Transactions and Transaction
    History, with View as Client / Download at the top right.

    Refund and the tax-rate fields are absent on purpose — see EditInvoice's docblock for
    what would have to exist first. Paid is not a status anyone sets by hand: money
    arriving is what pays an invoice, and Add Payment is how money arrives.
--}}
<x-filament-panels::page>
    <div class="ao-mu ao-eo ao-ei">
        <div class="ao-ei-top">
            <div class="ao-tx-tabs ao-ei-tabs">
                @foreach ([
                    'summary' => 'Summary',
                    'payment' => 'Add Payment',
                    'options' => 'Options',
                    'credit' => 'Credit',
                    'refund' => 'Refund',
                    'notes' => 'Notes',
                ] as $key => $label)
                    <button type="button" class="ao-mu-tab {{ $tab === $key ? 'ao-on' : '' }}"
                        wire:click="$set('tab', '{{ $key }}')">{{ $label }}</button>
                @endforeach
            </div>

            <div class="ao-ei-tools">
                <a class="ao-pg-btn" href="{{ url('/invoices/' . $invoice->id) }}" target="_blank" rel="noopener">
                    View as Client
                </a>
                <button type="button" class="ao-pg-btn" wire:click="downloadPdf">Download</button>
                <a class="ao-pg-btn" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageInvoices::getUrl() }}">
                    &laquo; Back to List
                </a>
            </div>
        </div>

        {{-- ── Summary ─────────────────────────────────────────────────────────── --}}
        @if ($tab === 'summary')
            <div class="ao-ei-summary">
                <div class="ao-anc-card ao-ei-facts">
                    <div class="ao-anc-row">
                        <span>Client Name</span>
                        <span class="ao-eo-fact">
                            <a class="ao-link" href="{{ $clientUrl }}">{{ $clientName }}</a>
                            <a class="ao-link ao-ei-sub" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageInvoices::getUrl() }}?client={{ $invoice->user_id }}">( View Invoices )</a>
                        </span>
                    </div>
                    <div class="ao-anc-row">
                        <span>Invoice Date</span>
                        <span class="ao-eo-fact">{{ $invoice->created_at?->format('m/d/Y') }}</span>
                    </div>
                    <div class="ao-anc-row">
                        <span>Due Date</span>
                        <span class="ao-eo-fact">{{ $invoice->due_at?->format('m/d/Y') ?? '—' }}</span>
                    </div>
                    <div class="ao-anc-row">
                        <span>Invoice Amount</span>
                        <span class="ao-eo-fact">${{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency_code }}</span>
                    </div>
                    <div class="ao-anc-row">
                        <span>Balance</span>
                        <span class="ao-eo-fact ao-ei-balance">${{ number_format(max(0, (float) $invoice->remaining), 2) }} {{ $invoice->currency_code }}</span>
                    </div>
                </div>

                <div class="ao-ei-status-block">
                    <div class="ao-ei-status ao-ei-status--{{ $invoice->status }}">
                        {{ ['paid' => 'PAID', 'pending' => 'UNPAID', 'cancelled' => 'CANCELLED'][$invoice->status] ?? strtoupper($invoice->status) }}
                    </div>
                    <div class="ao-ei-status-line">
                        Last Capture Attempt: <b>{{ $lastAttempt?->created_at?->format('m/d/Y H:i') ?? 'None' }}</b>
                    </div>
                    <div class="ao-ei-status-line">
                        Payment Method: <b>{{ $paymentMethod ?? '—' }}</b>
                    </div>

                    <div class="ao-ei-send">
                        <select class="ao-of-md" wire:model="emailTemplate" aria-label="Email to send">
                            @foreach (\Paymenter\Extensions\Others\AdminOps\Admin\Pages\EditInvoice::EMAILS as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="ao-pg-btn" wire:click="sendEmail"
                            wire:loading.attr="disabled" wire:target="sendEmail">Send Email</button>
                    </div>

                    <div class="ao-ei-marks">
                        @if ($invoice->status !== 'pending')
                            <button type="button" class="ao-pg-btn" wire:click="setStatus('pending')">Mark Unpaid</button>
                        @endif
                        @if ($invoice->status !== 'cancelled')
                            <button type="button" class="ao-pg-btn" wire:click="setStatus('cancelled')">Cancel Invoice</button>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- ── Add Payment ─────────────────────────────────────────────────────── --}}
        @if ($tab === 'payment')
            <form class="ao-anc-card ao-ei-two" wire:submit.prevent="addPayment">
                <div class="ao-anc-col">
                    <label class="ao-anc-row">
                        <span>Date</span>
                        @include('adminops::partials.datepicker', [
                            'model' => 'pay.date', 'range' => false, 'id' => 'ao-ei-paydate',
                            'placeholder' => 'MM/DD/YYYY', 'class' => 'ao-of-md',
                        ])
                    </label>
                    <label class="ao-anc-row">
                        <span>Payment Method</span>
                        <select wire:model="pay.gateway">
                            <option value="">Manually recorded</option>
                            @foreach ($gateways as $gateway)
                                <option value="{{ $gateway->extension }}">{{ $gateway->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="ao-anc-row">
                        <span>Transaction ID</span>
                        <input type="text" wire:model="pay.transactionId">
                    </label>
                </div>

                <div class="ao-anc-col">
                    <label class="ao-anc-row">
                        <span>Amount</span>
                        <input type="text" inputmode="decimal" wire:model="pay.amount">
                    </label>
                    <label class="ao-anc-row">
                        <span>Transaction Fees</span>
                        <input type="text" inputmode="decimal" wire:model="pay.fee" placeholder="0.00">
                    </label>
                    <label class="ao-anc-row">
                        <span>Send Email</span>
                        <span class="ao-anc-field">
                            <input type="checkbox" wire:model="pay.sendEmail">
                            <i>Check to Send Confirmation Email</i>
                        </span>
                    </label>
                </div>

                <div class="ao-pr-center ao-ei-wide"><button type="submit" class="ao-find-go">Add Payment</button></div>
            </form>
        @endif

        {{-- ── Options ─────────────────────────────────────────────────────────── --}}
        @if ($tab === 'options')
            <form class="ao-anc-card ao-ei-two" wire:submit.prevent="saveOptions">
                <div class="ao-anc-col">
                    <label class="ao-anc-row">
                        <span>Invoice Date</span>
                        @include('adminops::partials.datepicker', [
                            'model' => 'options.invoiceDate', 'range' => false, 'id' => 'ao-ei-invdate',
                            'placeholder' => 'MM/DD/YYYY', 'class' => 'ao-of-md',
                        ])
                    </label>
                    <label class="ao-anc-row">
                        <span>Invoice #</span>
                        <input type="text" wire:model="options.number" placeholder="Blank until issued">
                    </label>
                </div>

                <div class="ao-anc-col">
                    <label class="ao-anc-row">
                        <span>Due Date</span>
                        @include('adminops::partials.datepicker', [
                            'model' => 'options.dueAt', 'range' => false, 'id' => 'ao-ei-duedate',
                            'placeholder' => 'MM/DD/YYYY', 'class' => 'ao-of-md',
                        ])
                    </label>
                    {{-- Paid is absent by design: it is the consequence of payments covering
                         the total, not a state to assert. Add Payment is how money arrives. --}}
                    <label class="ao-anc-row">
                        <span>Status</span>
                        <select wire:model="options.status">
                            <option value="pending">Unpaid</option>
                            <option value="cancelled">Cancelled</option>
                            @if ($invoice->status === 'paid')
                                <option value="paid" disabled>Paid — set by recording a payment</option>
                            @endif
                        </select>
                    </label>
                </div>

                <div class="ao-pr-center ao-ei-wide"><button type="submit" class="ao-find-go">Save Changes</button></div>
            </form>
        @endif

        {{-- ── Credit ──────────────────────────────────────────────────────────── --}}
        @if ($tab === 'credit')
            <div class="ao-anc-card ao-ei-credit">
                <div class="ao-ei-credit-side">
                    <h5>Add Credit to Invoice</h5>
                    <div class="ao-ei-credit-row">
                        <input type="text" inputmode="decimal" wire:model="credit.add" placeholder="0.00"
                            aria-label="Credit to add">
                        <button type="button" class="ao-pg-btn" wire:click="addCredit"
                            wire:loading.attr="disabled" wire:target="addCredit">Go</button>
                    </div>
                    <p class="ao-ei-credit-note ao-ei-credit-ok">
                        ${{ number_format($availableCredit, 2) }} {{ $invoice->currency_code }} Available
                    </p>
                </div>

                <div class="ao-ei-credit-side">
                    <h5>Remove Credit from Invoice</h5>
                    <div class="ao-ei-credit-row">
                        <input type="text" inputmode="decimal" wire:model="credit.remove" placeholder="0.00"
                            aria-label="Credit to remove">
                        <button type="button" class="ao-pg-btn" wire:click="removeCredit"
                            wire:loading.attr="disabled" wire:target="removeCredit">Go</button>
                    </div>
                    <p class="ao-ei-credit-note ao-ei-credit-warn">
                        ${{ number_format($creditApplied, 2) }} {{ $invoice->currency_code }} Available
                    </p>
                </div>
            </div>
        @endif

        {{-- ── Refund ──────────────────────────────────────────────────────────── --}}
        @if ($tab === 'refund')
            <form class="ao-anc-card ao-ei-refund" wire:submit.prevent="issueRefund">
                <div class="ao-anc-row">
                    <span>Refund Type</span>
                    <span class="ao-eo-fact">
                        Credit to the client's balance
                        <i class="ao-ei-refund-note">
                            No gateway here implements a refund hook, so money cannot be sent
                            back down the card or crypto rail it arrived on. Credit is what
                            can genuinely be returned — and it is what the client spends here.
                        </i>
                    </span>
                </div>

                <label class="ao-anc-row">
                    <span>Amount</span>
                    <span class="ao-anc-field">
                        <input type="text" inputmode="decimal" class="ao-w-25" wire:model="refund.amount"
                            placeholder="{{ number_format(max(0, $refundable), 2) }}">
                        {{-- Built in PHP rather than with an inline @if: Blade only treats
                             `@if` as a directive at a non-word boundary, so `refundable@if`
                             compiled to nothing and printed the directive to the page. --}}
                        @php
                            $hint = '$' . number_format(max(0, $refundable), 2) . ' ' . $invoice->currency_code . ' refundable';

                            if ($refunded > 0) {
                                $hint .= ', $' . number_format($refunded, 2) . ' already returned';
                            }
                        @endphp
                        <i>{{ $hint }}</i>
                    </span>
                </label>
                @error('refund.amount') <p class="ao-anc-errors">{{ $message }}</p> @enderror

                <label class="ao-anc-row">
                    <span>Reason</span>
                    <input type="text" wire:model="refund.reason"
                        placeholder="eg. Service cancelled early — credit for the unused period">
                </label>

                <label class="ao-anc-row">
                    <span>Send Email</span>
                    <span class="ao-anc-field">
                        <input type="checkbox" wire:model="refund.sendEmail">
                        <i>Check to Send Confirmation Email</i>
                    </span>
                </label>

                <div class="ao-pr-center">
                    <button type="submit" class="ao-find-go" @disabled($refundable <= 0)
                        wire:loading.attr="disabled" wire:target="issueRefund">Refund</button>
                </div>

                <p class="ao-cp-note">
                    The invoice stays settled. This returns credit for a service that ended
                    early; it is not a reversal of the payment, so nothing here makes the
                    client appear to owe money again.
                </p>
            </form>

            @if ($refunds->isNotEmpty())
                <h4 class="ao-ano-heading">Refunds Issued</h4>
                <table class="ao-mu-grid">
                    <thead><tr><th>Date</th><th>Amount</th><th>Reason</th><th>By</th></tr></thead>
                    <tbody>
                        @foreach ($refunds as $entry)
                            <tr>
                                <td>{{ $entry->created_at?->format('m/d/Y H:i') }}</td>
                                <td>${{ number_format((float) $entry->amount, 2) }} {{ $entry->currency_code }}</td>
                                <td class="ao-mu-left">{{ $entry->reason ?: '—' }}</td>
                                <td>{{ $entry->admin?->first_name ? trim($entry->admin->first_name . ' ' . $entry->admin->last_name) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endif

        {{-- ── Notes ───────────────────────────────────────────────────────────── --}}
        @if ($tab === 'notes')
            <form class="ao-anc-card ao-ei-notes" wire:submit.prevent="saveNote">
                <textarea rows="8" wire:model="note"
                    placeholder="Internal notes about this invoice. Staff only — the client never sees this."></textarea>
                <div class="ao-pr-center"><button type="submit" class="ao-find-go">Save Note</button></div>
            </form>
        @endif

        {{-- ── Invoice Items, shared under every tab, as the reference has it ──── --}}
        <div class="ao-ei-items-head">
            <h4 class="ao-ano-heading">Invoice Items</h4>
            <button type="button" class="ao-pg-btn" wire:click="addItem">Add Item</button>
        </div>

        <form wire:submit.prevent="save">
            <table class="ao-mu-grid ao-ei-grid">
                <thead>
                    <tr>
                        <th class="ao-ei-check"></th>
                        <th>Description</th>
                        <th class="ao-ei-qty">Quantity</th>
                        <th class="ao-ei-amount">Amount</th>
                        <th class="ao-ei-del"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $index => $item)
                        <tr>
                            <td class="ao-ei-check">
                                <input type="checkbox" value="{{ $index }}" wire:model="selected"
                                    aria-label="Select line {{ $index + 1 }}">
                            </td>
                            <td class="ao-mu-left">
                                <input type="text" class="ao-ei-desc" wire:model="items.{{ $index }}.description"
                                    aria-label="Line description">
                            </td>
                            <td><input type="number" min="1" class="ao-ei-qty-in" wire:model="items.{{ $index }}.quantity" aria-label="Quantity"></td>
                            <td><input type="text" inputmode="decimal" class="ao-ei-amount-in" wire:model="items.{{ $index }}.price" aria-label="Amount"></td>
                            <td class="ao-ei-del">
                                <button type="button" class="ao-ei-remove" wire:click="removeItem({{ $index }})"
                                    title="Remove this line" aria-label="Remove line">&#9679;</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="ao-mu-none ao-mu-left">No Records Found</td></tr>
                    @endforelse

                    <tr class="ao-ei-withrow">
                        <td colspan="2" class="ao-mu-left">
                            <select class="ao-ei-with" wire:change="withSelected($event.target.value)"
                                aria-label="With selected">
                                <option value="">- With Selected -</option>
                                <option value="delete">Delete</option>
                            </select>
                        </td>
                        <td class="ao-eo-total-label">Sub Total:</td>
                        <td class="ao-eo-total-value">${{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency_code }}</td>
                        <td></td>
                    </tr>
                    <tr class="ao-ei-withrow">
                        <td colspan="2"></td>
                        <td class="ao-eo-total-label">Credit:</td>
                        <td class="ao-eo-total-value">${{ number_format($creditApplied, 2) }} {{ $invoice->currency_code }}</td>
                        <td></td>
                    </tr>
                    <tr class="ao-eo-total">
                        <td colspan="2"></td>
                        <td class="ao-eo-total-label">Total Due:</td>
                        <td class="ao-eo-total-value">${{ number_format(max(0, (float) $invoice->remaining), 2) }} {{ $invoice->currency_code }}</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>

            @if ($errors->any())
                <ul class="ao-anc-errors">
                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            @endif

            <div class="ao-pr-center ao-ei-save">
                <button type="submit" class="ao-find-go">Save Changes</button>
                <a class="ao-pg-btn" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\EditInvoice::getUrl(['record' => $invoice->id]) }}">Cancel Changes</a>
            </div>
        </form>

        {{-- ── Transactions: money that actually landed ─────────────────────────── --}}
        <h4 class="ao-ano-heading">Transactions</h4>
        <table class="ao-mu-grid">
            <thead>
                <tr><th>Date</th><th>Payment Method</th><th>Transaction ID</th><th>Amount</th><th>Transaction Fees</th></tr>
            </thead>
            <tbody>
                @php $succeeded = $invoice->transactions->where('status', \App\Enums\InvoiceTransactionStatus::Succeeded); @endphp
                @forelse ($succeeded as $transaction)
                    <tr>
                        <td>{{ $transaction->created_at?->format('m/d/Y H:i') }}</td>
                        <td>{{ $transaction->is_credit_transaction ? 'Account credit' : ($transaction->gateway?->name ?? '—') }}</td>
                        <td class="ao-mu-left">{{ $transaction->transaction_id ?: '—' }}</td>
                        <td>${{ number_format((float) $transaction->amount, 2) }} {{ $invoice->currency_code }}</td>
                        <td>${{ number_format((float) ($transaction->fee ?? 0), 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="ao-mu-none ao-mu-left">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        {{-- ── Transaction History: every attempt, however it ended ─────────────
             The reference fills this from its gateway log. This install has no such
             table, but invoice_transactions already records processing and failed
             attempts alongside successful ones — which is the same information. --}}
        <h4 class="ao-ano-heading">Transaction History</h4>
        <table class="ao-mu-grid">
            <thead>
                <tr><th>Date</th><th>Payment Method</th><th>Transaction ID</th><th>Status</th><th>Description</th></tr>
            </thead>
            <tbody>
                @forelse ($invoice->transactions->sortByDesc('created_at') as $transaction)
                    @php $status = $transaction->status->value ?? (string) $transaction->status; @endphp
                    <tr>
                        <td>{{ $transaction->created_at?->format('m/d/Y H:i') }}</td>
                        <td>{{ $transaction->is_credit_transaction ? 'Account credit' : ($transaction->gateway?->name ?? '—') }}</td>
                        <td class="ao-mu-left">{{ $transaction->transaction_id ?: '—' }}</td>
                        <td>
                            <span class="ao-mu-status {{ ['succeeded' => 'ao-mu-st-active', 'processing' => 'ao-mu-st-unpaid', 'failed' => 'ao-mu-st-cancelled'][$status] ?? '' }}">
                                {{ ucfirst($status) }}
                            </span>
                        </td>
                        <td class="ao-mu-left">
                            ${{ number_format((float) $transaction->amount, 2) }} {{ $invoice->currency_code }}
                            @if ($transaction->is_credit_transaction) from account credit @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="ao-mu-none ao-mu-left">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

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
