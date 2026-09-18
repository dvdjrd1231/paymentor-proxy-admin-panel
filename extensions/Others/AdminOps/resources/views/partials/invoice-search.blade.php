{{-- The reference's invoice Search panel: its own two columns, above the list it narrows. --}}
<div class="ao-anc-card ao-ei-two ao-inv-search">
    <div class="ao-anc-col">
        <label class="ao-anc-row">
            <span>Invoice #</span>
            <input type="text" wire:model.live.debounce.500ms="invoiceFilter.number">
        </label>
        <label class="ao-anc-row">
            <span>Line Item Description</span>
            <input type="text" wire:model.live.debounce.500ms="invoiceFilter.line">
        </label>
        <label class="ao-anc-row">
            <span>Payment Method</span>
            <select wire:model.live.debounce.500ms="invoiceFilter.method">
                <option value="">Any</option>
                @foreach ($this->gatewayChoices() as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </label>
        <label class="ao-anc-row">
            <span>Status</span>
            <select wire:model.live.debounce.500ms="invoiceFilter.status">
                <option value="">Any</option>
                <option value="draft">Draft</option>
                <option value="pending">Unpaid</option>
                <option value="paid">Paid</option>
                <option value="cancelled">Cancelled</option>
                <option value="refunded">Refunded</option>
            </select>
        </label>
        {{-- The reference stacks From over To, with the label beside the pair. --}}
        <div class="ao-anc-row ao-inv-total">
            <span>Total Due</span>
            <span class="ao-anc-field ao-inv-range">
                <label><i>From:</i>
                    <input type="text" inputmode="decimal" class="ao-of-sm"
                        wire:model.live.debounce.500ms="invoiceFilter.dueFrom"></label>
                <label><i>To:</i>
                    <input type="text" inputmode="decimal" class="ao-of-sm"
                        wire:model.live.debounce.500ms="invoiceFilter.dueTo"></label>
            </span>
        </div>
    </div>

    <div class="ao-anc-col">
        @foreach ([
            'invoiceDate' => 'Invoice Date',
            'dueDate' => 'Due Date',
            'datePaid' => 'Date Paid',
            'dateRefunded' => 'Date Refunded',
        ] as $key => $label)
            <label class="ao-anc-row">
                <span>{{ $label }}</span>
                @include('adminops::partials.datepicker', [
                    'model' => 'invoiceFilter.' . $key, 'range' => false,
                    'id' => 'ao-inv-' . $key, 'placeholder' => 'MM/DD/YYYY', 'class' => 'ao-of-md',
                ])
            </label>
        @endforeach
    </div>
</div>

{{-- No Search button: the reference has none, because its boxes filter the list
     themselves. Clear is ours — with ten boxes and no page reload to fall back on, emptying
     them by hand is the one thing the reference's layout does not give you. --}}
@if ($this->invoiceFilterActive())
    <div class="ao-bt-save ao-inv-search-go">
        <button type="button" class="ao-pg-btn" wire:click="clearInvoiceFilter">Clear Search</button>
    </div>
@endif
