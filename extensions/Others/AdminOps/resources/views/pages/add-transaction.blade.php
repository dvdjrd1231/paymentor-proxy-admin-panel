{{-- Add Transaction, to issue #15: record a payment taken outside the gateways, through
     core's own idempotent path. Laid out as the reference lays it out — its left column of
     identifiers, its right column of money. --}}
<x-filament-panels::page>
    <form class="ao-mu" wire:submit.prevent="create">
        <h4 class="ao-bt-h">Add New Transaction</h4>

        <div class="ao-anc-card ao-ei-two">
            <div class="ao-anc-col">
                <label class="ao-anc-row">
                    <span>Date</span>
                    @include('adminops::partials.datepicker', [
                        'model' => 'date', 'range' => false, 'id' => 'ao-tx-date',
                        'placeholder' => 'MM/DD/YYYY', 'class' => 'ao-of-md',
                    ])
                </label>
                <label class="ao-anc-row">
                    <span>Description</span>
                    <input type="text" wire:model="description">
                </label>
                <label class="ao-anc-row">
                    <span>Transaction ID</span>
                    <input type="text" wire:model="transactionId"
                        placeholder="Bank reference or receipt — repeats are rejected">
                </label>
                <label class="ao-anc-row">
                    <span>Invoice ID</span>
                    <select wire:model.live="invoiceId">
                        <option value="">
                            {{ $forUser ? 'None — this client has no unpaid invoice selected' : 'Pick the unpaid invoice being paid' }}
                        </option>
                        @foreach ($invoices as $invoice)
                            <option value="{{ $invoice->id }}">
                                {{ $invoice->number ?? $invoice->id }} ·
                                {{ trim(($invoice->user->first_name ?? '') . ' ' . ($invoice->user->last_name ?? '')) ?: ($invoice->user->email ?? '') }} ·
                                {{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency_code }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label class="ao-anc-row">
                    <span>Payment Method</span>
                    <select wire:model="gateway">
                        <option value="">None</option>
                        @foreach ($gateways as $extension)
                            <option value="{{ $extension }}">{{ $extension }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="ao-anc-col">
                <label class="ao-anc-row">
                    <span>Amount In</span>
                    <input type="text" inputmode="decimal" class="ao-of-sm" wire:model="amount" placeholder="0.00">
                </label>
                <label class="ao-anc-row">
                    <span>Fees</span>
                    <input type="text" inputmode="decimal" class="ao-of-sm" wire:model="fee" placeholder="0.00">
                </label>
                {{-- Out is the same movement with its sign reversed: a refund recorded by hand. --}}
                <label class="ao-anc-row">
                    <span>Amount Out</span>
                    <input type="text" inputmode="decimal" class="ao-of-sm" wire:model="amountOut" placeholder="0.00">
                </label>
                {{-- The reference can record a transaction against no invoice; a transaction
                     row here must have one, so money that belongs to the client rather than
                     to an invoice goes to their credit balance instead. --}}
                <label class="ao-anc-row">
                    <span>Credit</span>
                    <span class="ao-anc-field">
                        <label class="ao-of-check">
                            <input type="checkbox" wire:model.live="toCredit"
                                @disabled(!$forUser)>
                            Add to Client's Credit Balance
                        </label>
                        @unless ($forUser)
                            <i class="ao-anc-hint">Open this from a client profile to credit a balance.</i>
                        @endunless
                    </span>
                </label>
            </div>
        </div>

        @if ($errors->any())
            <ul class="ao-anc-errors">
                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        @endif

        <div class="ao-pr-center">
            <button type="submit" class="ao-pg-btn">Add Transaction</button>
        </div>
    </form>
</x-filament-panels::page>
