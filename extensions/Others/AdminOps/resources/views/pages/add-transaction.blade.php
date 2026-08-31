{{-- Add Transaction, to issue #15: record an offline payment through core's own path. --}}
<x-filament-panels::page>
    <form class="ao-mu" wire:submit.prevent="create">
        <div class="ao-anc-card">
            <label class="ao-anc-row">
                <span>Invoice</span>
                <select class="ao-w-45" wire:model.live="invoiceId" required>
                    <option value="">Pick the unpaid invoice being paid</option>
                    @foreach ($invoices as $invoice)
                        <option value="{{ $invoice->id }}">
                            {{ $invoice->number ?? $invoice->id }} ·
                            {{ trim(($invoice->user->first_name ?? '') . ' ' . ($invoice->user->last_name ?? '')) ?: ($invoice->user->email ?? '') }} ·
                            ${{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency_code }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="ao-anc-row">
                <span>Payment Method</span>
                <select class="ao-w-25" wire:model="gateway">
                    <option value="">Offline / Manual</option>
                    @foreach ($gateways as $extension)
                        <option value="{{ $extension }}">{{ $extension }}</option>
                    @endforeach
                </select>
            </label>
            <label class="ao-anc-row">
                <span>Transaction ID</span>
                <input type="text" class="ao-w-40" wire:model="transactionId"
                    placeholder="Bank reference or terminal receipt — repeats are rejected">
            </label>
            <label class="ao-anc-row">
                <span>Amount In</span>
                <input type="text" inputmode="decimal" class="ao-w-25" wire:model="amount" placeholder="0.00" required>
            </label>
            <label class="ao-anc-row">
                <span>Fees</span>
                <input type="text" inputmode="decimal" class="ao-w-25" wire:model="fee" placeholder="0.00">
            </label>
        </div>

        @if ($errors->any())
            <ul class="ao-anc-errors">
                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        @endif

        <div class="ao-pr-center">
            <button type="submit" class="ao-find-go">Add Transaction</button>
        </div>
    </form>
</x-filament-panels::page>
