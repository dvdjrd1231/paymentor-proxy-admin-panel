{{-- The reference's Add New Transaction, drawn in the tab body rather than on a page of its
     own: it keeps the profile header and tab strip and swaps only this panel
     (Leandro, 2026-09-18). Its left column of identifiers, its right column of money. --}}
<h4 class="ao-bt-h">Add New Transaction</h4>

<div class="ao-anc-card ao-ei-two">
    <div class="ao-anc-col">
        <label class="ao-anc-row">
            <span>Date</span>
            @include('adminops::partials.datepicker', [
                'model' => 'txDate', 'range' => false, 'id' => 'ao-ct-txdate',
                'placeholder' => 'MM/DD/YYYY', 'class' => 'ao-of-md',
            ])
        </label>
        <label class="ao-anc-row">
            <span>Description</span>
            <input type="text" wire:model="txDescription">
        </label>
        <label class="ao-anc-row">
            <span>Transaction ID</span>
            <input type="text" wire:model="txTransactionId"
                placeholder="Bank reference or receipt — repeats are rejected">
        </label>
        <label class="ao-anc-row">
            <span>Invoice ID</span>
            <select wire:model="txInvoiceId">
                <option value="">None</option>
                @foreach ($this->transactionInvoices() as $invoice)
                    <option value="{{ $invoice->id }}">
                        {{ $invoice->number ?: $invoice->id }} ·
                        {{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency_code }}
                    </option>
                @endforeach
            </select>
        </label>
        <label class="ao-anc-row">
            <span>Payment Method</span>
            <select wire:model="txGateway">
                <option value="">None</option>
                @foreach ($this->transactionGateways() as $extension)
                    <option value="{{ $extension }}">{{ $extension }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <div class="ao-anc-col">
        <label class="ao-anc-row">
            <span>Amount In</span>
            <input type="text" inputmode="decimal" class="ao-of-sm" wire:model="txAmountIn" placeholder="0.00">
        </label>
        <label class="ao-anc-row">
            <span>Fees</span>
            <input type="text" inputmode="decimal" class="ao-of-sm" wire:model="txFees" placeholder="0.00">
        </label>
        {{-- Out is the same movement with its sign reversed: a refund recorded by hand. --}}
        <label class="ao-anc-row">
            <span>Amount Out</span>
            <input type="text" inputmode="decimal" class="ao-of-sm" wire:model="txAmountOut" placeholder="0.00">
        </label>
        {{-- The reference can record a transaction against no invoice; a transaction row
             here must carry one, because core reads the invoice's currency straight off it.
             Money belonging to the client rather than an invoice is their credit balance. --}}
        <label class="ao-anc-row">
            <span>Credit</span>
            <span class="ao-anc-field">
                <label class="ao-of-check">
                    <input type="checkbox" wire:model.live="txToCredit">
                    Add to Client's Credit Balance
                </label>
            </span>
        </label>
    </div>
</div>

@if ($errors->any())
    <ul class="ao-anc-errors">
        @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
    </ul>
@endif

<div class="ao-bt-save">
    <button type="button" class="ao-pg-btn" wire:click="saveTransaction"
        wire:loading.attr="disabled" wire:target="saveTransaction">Add Transaction</button>
</div>
