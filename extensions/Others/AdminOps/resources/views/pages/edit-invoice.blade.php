{{-- The reference's invoice screen (Leandro, 2026-09-07, screenshots of invoices.php): the
     tab strip over the shared Invoice Items ladder, then Transactions and Transaction
     History, with View as Client / Download at the top right. --}}
<x-filament-panels::page>
    {{-- Tabs switch in the browser — see the note on edit-product for why they no longer
         go to the server. --}}
    <div class="ao-mu ao-eo ao-ei" x-data="{ tab: @js($tab) }">
        {{-- The reference's draft notice, between the title and the tabs and on every one of them: the
             reason the invoice looks unfinished is that the client cannot see it yet. --}}
        @if ($invoice->status === 'draft')
            <div class="ao-cs-banner ao-ei-draft-note">
                <x-filament::icon icon="ri-information-line" class="ao-cs-banner-ic" />
                <div>This is a Draft Invoice. The client is not able to see or access this invoice until it is published.</div>
            </div>
        @endif

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
                    <button type="button" class="ao-mu-tab"
                        :class="{ 'ao-on': tab === '{{ $key }}' }"
                        @click="tab = '{{ $key }}'">{{ $label }}</button>
                @endforeach
            </div>

            {{-- The reference's four, each with its icon: the invoice itself, the invoice as
                 the client sees it, the file, and the way back (Leandro, 2026-09-16). --}}
            <div class="ao-ei-tools">
                <a class="ao-pg-btn" href="{{ route('adminops.invoice-pdf', $invoice->id) }}" target="_blank" rel="noopener">
                    <x-filament::icon icon="ri-eye-fill" class="ao-ei-tool-ic" /> View Invoice
                </a>
                <a class="ao-pg-btn" href="{{ url('/invoices/' . $invoice->id) }}" target="_blank" rel="noopener">
                    <x-filament::icon icon="ri-account-box-fill" class="ao-ei-tool-ic" /> View as Client
                </a>
                {{-- Print, where Back to List used to sit: the reference's four are View
                     Invoice, View as Client, Print and Download, and the way back is the
                     rail's own List All Invoices (Leandro, 2026-09-16). --}}
                <a class="ao-pg-btn" href="{{ route('adminops.invoice-pdf', $invoice->id) }}?print=1"
                    target="_blank" rel="noopener">
                    <x-filament::icon icon="ri-printer-fill" class="ao-ei-tool-ic" /> Print
                </a>
                <button type="button" class="ao-pg-btn" wire:click="downloadPdf">
                    <x-filament::icon icon="ri-download-fill" class="ao-ei-tool-ic" /> Download
                </button>
            </div>
        </div>

        {{-- ── Summary ─────────────────────────────────────────────────────────── --}}
        <div x-show="tab === 'summary'" x-cloak style="margin-top: -16px;border: 1px solid #e5e5e5;">
            {{-- The reference's own band: a full-width grey strip under the toolbar with
                 the Publish pair at its right, flush with the card beneath it. Ours had the
                 buttons floating unbacked inside the status column (Leandro, 2026-09-17). --}}
            @if ($invoice->status === 'draft')
                <div class="ao-ei-publishbar">
                    <button type="button" class="ao-find-go" wire:click="publish"
                        wire:loading.attr="disabled" wire:target="publish">Publish</button>
                    <button type="button" class="ao-ei-publish-mail" wire:click="publish(true)"
                        wire:loading.attr="disabled" wire:target="publish">Publish and Send Email</button>
                </div>
            @endif

            <div class="ao-ei-summary">
                <div class="ao-anc-card ao-ei-facts" style="margin-left: 10px;">
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
                        {{ ['paid' => 'PAID', 'pending' => 'UNPAID', 'cancelled' => 'CANCELLED', 'draft' => 'DRAFT'][$invoice->status] ?? strtoupper($invoice->status) }}
                    </div>
                    {{-- One line under the status word, as the reference has: the method,
                         or its own wording when nothing has been paid against the invoice
                         yet. Last Capture Attempt was ours and is gone — it only ever said
                         "None" here, since nothing captures against a card
                         (Leandro, 2026-09-17). --}}
                    <div class="ao-ei-status-line">
                        Payment Method: <b>{{ $paymentMethod ?: 'No Transactions Applied' }}</b>
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
        </div>

        {{-- ── Add Payment ─────────────────────────────────────────────────────── --}}
        <div x-show="tab === 'payment'" x-cloak>
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
        </div>

        {{-- ── Options ─────────────────────────────────────────────────────────── --}}
        <div x-show="tab === 'options'" x-cloak>
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
        </div>

        {{-- ── Credit ──────────────────────────────────────────────────────────── --}}
        <div x-show="tab === 'credit'" x-cloak>
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
        </div>

        {{-- ── Refund ──────────────────────────────────────────────────────────── --}}
        <div x-show="tab === 'refund'" x-cloak>
            <form class="ao-anc-card ao-ei-refund" wire:submit.prevent="issueRefund">
                {{-- One line, as the reference's Refund Type row is: the explanation of why
                     this is the only kind of refund available sits in the note under the
                     card rather than inflating the row to a paragraph (Leandro,
                     2026-09-16). --}}
                {{-- The reference's first row: which payment is being refunded. Disabled
                     while there is none, with its own wording (Leandro, 2026-09-16). --}}
                <div class="ao-anc-card-refund-container">
                    <label class="ao-anc-row">
                        <span>Transactions</span>
                        <span class="ao-anc-field">
                            {{-- Not disabled when empty: the reference's opens and shows its single line,
                                and a control that will not open reads as broken (Leandro, 2026-09-16). --}}
                            <select class="ao-of-lg" wire:model="refund.transaction">
                                @forelse ($succeeded as $transaction)
                                    <option value="{{ $transaction->id }}">
                                        {{ $transaction->created_at?->format('m/d/Y') }} &mdash;
                                        ${{ number_format((float) $transaction->amount, 2) }} {{ $invoice->currency_code }}
                                        @if ($transaction->gateway?->name) ({{ $transaction->gateway->name }}) @endif
                                    </option>
                                @empty
                                    <option value="">No Transactions Applied To This Invoice Yet</option>
                                @endforelse
                            </select>
                        </span>
                    </label>

                    <label class="ao-anc-row">
                        <span>Amount</span>
                        <span class="ao-anc-field">
                            <input type="text" inputmode="decimal" class="ao-w-25" wire:model="refund.amount"
                                placeholder="{{ number_format(max(0, $refundable), 2) }}">
                            {{-- The reference's own wording, and it is honoured: an empty box
                                refunds everything still refundable (Leandro, 2026-09-16). The
                                figure follows it so the amount that means is on screen. --}}
                            @php
                                $hint = 'Leave blank for full refund — $' . number_format(max(0, $refundable), 2)
                                    . ' ' . $invoice->currency_code . ' refundable';

                                if ($refunded > 0) {
                                    $hint .= ', $' . number_format($refunded, 2) . ' already returned';
                                }
                            @endphp
                            <i>{{ $hint }}</i>
                        </span>
                    </label>
                    @error('refund.amount') <p class="ao-anc-errors">{{ $message }}</p> @enderror
                    {{-- A real picker, as the reference has: credit the balance, record one
                        sent by hand, or ask the gateway. The gateway option is checked against
                        the gateway when it is used rather than hidden — none here implements a
                        refund hook today, and the refusal says which (Leandro, 2026-09-16). --}}
                    <label class="ao-anc-row">
                        <span>Refund Type</span>
                        <span class="ao-anc-field">
                            <select class="ao-of-lg" wire:model="refund.type">
                                @foreach (\Paymenter\Extensions\Others\AdminOps\Admin\Pages\EditInvoice::REFUND_TYPES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </span>
                    </label>


                    <label class="ao-anc-row">
                        <span>Reason</span>
                        <input type="text" wire:model="refund.reason"
                            style="width: inherit;"
                            placeholder="eg. Service cancelled early — credit for the unused period">
                    </label>

                    {{-- The reference's Reverse Payment. What a payment sets going here is the
                        services the invoice paid for, so undoing it suspends them again —
                        hence "where possible", which is its own wording. --}}
                    <label class="ao-anc-row">
                        <span>Reverse Payment</span>
                        <span class="ao-anc-field">
                            <input type="checkbox" wire:model="refund.reverse">
                            <i>Undo automated actions triggered by this transaction &mdash; suspends the services this invoice paid for, where possible.</i>
                        </span>
                    </label>

                    <label class="ao-anc-row">
                        <span>Send Email</span>
                        <span class="ao-anc-field">
                            <input type="checkbox" wire:model="refund.sendEmail">
                            <i>Check to Send Confirmation Email</i>
                        </span>
                    </label>

                </div>

                <div class="ao-pr-center">
                    <button type="submit" class="ao-find-go" @disabled($refundable <= 0)
                        wire:loading.attr="disabled" wire:target="issueRefund">Refund</button>
                </div>

                <p class="ao-cp-note">
                    No gateway here implements a refund hook, so money cannot be sent back
                    down the card or crypto rail it arrived on — credit is what can genuinely
                    be returned, and it is what the client spends here. The invoice stays
                    settled: this returns credit for a service that ended early, not a
                    reversal of the payment, so nothing here makes the client appear to owe
                    money again.
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
        </div>

        {{-- ── Notes ───────────────────────────────────────────────────────────── --}}
        <div x-show="tab === 'notes'" x-cloak>
            <form class="ao-anc-card ao-ei-notes" wire:submit.prevent="saveNote">
                <textarea rows="8" wire:model="note"
                    placeholder="Internal notes about this invoice. Staff only — the client never sees this."></textarea>
                <div class="ao-pr-center"><button type="submit" class="ao-find-go">Save Note</button></div>
            </form>
        </div>

        {{-- ── Invoice Items, shared under every tab, as the reference has it ──── --}}
        <div class="ao-ei-items-head">
            <h4 class="ao-ano-heading">Invoice Items</h4>
            {{-- Adding a line is a round trip — Livewire re-renders the whole screen — so
                 the button says so rather than looking ignored for half a second
                 (Leandro, 2026-09-16: "Add item feature took a little long time"). --}}
            <button type="button" class="ao-pg-btn" wire:click="addItem"
                wire:loading.attr="disabled" wire:target="addItem">
                <span wire:loading.remove wire:target="addItem">Add Item</span>
                <span wire:loading wire:target="addItem">Adding&hellip;</span>
            </button>
        </div>

        <form wire:submit.prevent="save">
            <table class="ao-mu-grid ao-ei-grid">
                <thead>
                    <tr>
                        <th class="ao-ei-check"></th>
                        <th>Description</th>
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
                                {{-- A textarea, as the reference's is: a line's description
                                     runs to more than one line often enough that a single
                                     line box hides the rest (Leandro, 2026-09-16). --}}
                                <textarea class="ao-ei-desc" rows="1" wire:model="items.{{ $index }}.description"
                                    aria-label="Line description"></textarea>
                            </td>
                            <td><input type="text" inputmode="decimal" class="ao-ei-amount-in" wire:model.live.debounce.500ms="items.{{ $index }}.price" aria-label="Amount"></td>
                            <td class="ao-ei-del">
                                {{-- The reference's circled minus. Drawn as an icon rather
                                     than the ⊖ character, whose weight and alignment are
                                     whatever the system font happens to give it. --}}
                                <button type="button" class="ao-ei-remove" wire:click="removeItem({{ $index }})"
                                    title="Remove this line" aria-label="Remove line">
                                    <x-filament::icon icon="ri-indeterminate-circle-fill" class="ao-ei-remove-ic" />
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="ao-mu-none ao-mu-left">No Records Found</td></tr>
                    @endforelse

                    <tr class="ao-ei-withrow">
                        {{-- The select and the Sub Total label share the row's left half,
                             as the reference has them: one at each end of it. --}}
                        <td colspan="2" class="ao-mu-left">
                            {{-- The flex lives on a wrapper, not the cell: a <td> set to
                                 display:flex stops being a table cell, and the colspan
                                 quietly stopped spanning. --}}
                            <div class="ao-ei-withcell">
                                <select class="ao-ei-with" wire:change="withSelected($event.target.value)"
                                    aria-label="With selected">
                                    <option value="">- With Selected -</option>
                                    <option value="split">Split to New Invoice</option>
                                    <option value="delete">Delete</option>
                                </select>
                                <span class="ao-eo-total-label">Sub Total:</span>
                            </div>
                        </td>
                        {{-- What is on screen, saved or not {@see EditInvoice::liveSubtotal}. --}}
                        <td class="ao-eo-total-value">${{ number_format($this->liveSubtotal(), 2) }} {{ $invoice->currency_code }}</td>
                        <td></td>
                    </tr>
                    <tr class="ao-ei-withrow">
                        <td colspan="2" class="ao-eo-total-label">Credit:</td>
                        <td class="ao-eo-total-value">${{ number_format($creditApplied, 2) }} {{ $invoice->currency_code }}</td>
                        <td></td>
                    </tr>
                    <tr class="ao-eo-total">
                        <td colspan="2" class="ao-eo-total-label">Total Due:</td>
                        {{-- Follows the subtotal on screen, less whatever credit has been
                             applied, so the ladder adds up while the lines are being typed
                             rather than only after a save. --}}
                        <td class="ao-eo-total-value">${{ number_format(max(0, $this->liveSubtotal() - $creditApplied), 2) }} {{ $invoice->currency_code }}</td>
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

    </div>
</x-filament-panels::page>
