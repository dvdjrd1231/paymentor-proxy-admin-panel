{{-- The reference's Billable Items tab: what is still waiting to be billed, then what has
     already gone onto an invoice. It splits them because only the first can be acted on —
     an item already on an invoice cannot be invoiced again or quietly deleted. --}}
@php
    $waiting = $uninvoiced ?? collect();
    $billed = $invoiced ?? collect();
    $waitingTotal = $waiting->sum(fn ($r) => (float) $r->amount * (float) $r->quantity);
    $currency = $waiting->first()->currency_code ?? $billed->first()->currency_code ?? '';
@endphp

<div class="ao-bt-head">
    <span class="ao-bt-total">
        Uninvoiced Items &mdash;
        <b>{{ number_format($waitingTotal, 2) }} {{ $currency }}</b> ({{ $waiting->count() }})
    </span>
</div>

<table class="ao-mu-grid ao-bt-grid">
    <thead>
        <tr>
            <th class="ao-bt-tick">
                <input type="checkbox" aria-label="Select all"
                    x-on:change="$root.querySelectorAll('.ao-bt-row-tick').forEach(b => { b.checked = $event.target.checked; b.dispatchEvent(new Event('input')) })">
            </th>
            <th>ID</th>
            <th class="ao-mu-left">Description</th>
            <th class="ao-num">Hours</th>
            <th class="ao-num">Amount</th>
            <th>Invoice Action</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($waiting as $row)
            <tr>
                <td class="ao-bt-tick">
                    <input class="ao-bt-row-tick" type="checkbox"
                        value="{{ $row->id }}" wire:model.live="billableChosen">
                </td>
                <td>{{ $row->id }}</td>
                <td class="ao-mu-left">{{ $row->description }}</td>
                {{-- The reference's Hours is the quantity a rate is charged for. --}}
                <td class="ao-num">{{ rtrim(rtrim(number_format((float) $row->quantity, 2), '0'), '.') }}</td>
                <td class="ao-num">{{ number_format((float) $row->amount * (float) $row->quantity, 2) }} {{ $row->currency_code }}</td>
                <td>{{ \Illuminate\Support\Str::headline($row->invoice_action) }}</td>
            </tr>
        @empty
            <tr><td class="ao-mu-none" colspan="6">No Records Found</td></tr>
        @endforelse
    </tbody>
</table>

<div class="ao-bt-with">
    <span>With Selected:</span>
    <button type="button" class="ao-pg-btn" wire:click="invoiceChosenBillable"
        wire:loading.attr="disabled" wire:target="invoiceChosenBillable"
        @disabled($waiting->isEmpty())>Invoice Selected Items</button>
    <button type="button" class="ao-pg-btn ao-bt-del" wire:click="deleteChosenBillable"
        wire:confirm="Delete the selected billable items? This cannot be undone."
        wire:loading.attr="disabled" wire:target="deleteChosenBillable"
        @disabled($waiting->isEmpty())>Delete</button>
</div>

<h4 class="ao-bt-h">Invoiced Items</h4>

@include('adminops::partials.records-pager', [
    'total' => $billed->count(), 'page' => 1, 'perPage' => max($billed->count(), 1),
])

<table class="ao-mu-grid ao-bt-grid">
    <thead>
        <tr>
            <th>ID</th>
            <th class="ao-mu-left">Description</th>
            <th class="ao-num">Hours</th>
            <th class="ao-num">Amount</th>
            <th>Invoice Numbers</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($billed as $row)
            <tr>
                <td>{{ $row->id }}</td>
                <td class="ao-mu-left">{{ $row->description }}</td>
                <td class="ao-num">{{ rtrim(rtrim(number_format((float) $row->quantity, 2), '0'), '.') }}</td>
                <td class="ao-num">{{ number_format((float) $row->amount * (float) $row->quantity, 2) }} {{ $row->currency_code }}</td>
                <td><a class="ao-link" href="{{ $urls['invoice']($row->invoice_id) }}">#{{ $row->invoice_id }}</a></td>
            </tr>
        @empty
            <tr><td class="ao-mu-none" colspan="5">No Records Found</td></tr>
        @endforelse
    </tbody>
</table>
