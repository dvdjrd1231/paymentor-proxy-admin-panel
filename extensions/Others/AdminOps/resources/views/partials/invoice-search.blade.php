{{-- The reference's invoice Search panel: its own two columns, above the list it narrows. --}}
<div class="ao-anc-card ao-ei-two ao-inv-search">
    <div class="ao-anc-col">
        <label class="ao-anc-row">
            <span>Invoice #</span>
            <input type="text" wire:model="invoiceFilter.number">
        </label>
        <label class="ao-anc-row">
            <span>Line Item Description</span>
            <input type="text" wire:model="invoiceFilter.line">
        </label>
        <label class="ao-anc-row">
            <span>Payment Method</span>
            <select wire:model="invoiceFilter.method">
                <option value="">Any</option>
                @foreach ($this->gatewayChoices() as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </label>
        <label class="ao-anc-row">
            <span>Status</span>
            <select wire:model="invoiceFilter.status">
                <option value="">Any</option>
                <option value="draft">Draft</option>
                <option value="pending">Unpaid</option>
                <option value="paid">Paid</option>
                <option value="cancelled">Cancelled</option>
                <option value="refunded">Refunded</option>
            </select>
        </label>
        <div class="ao-anc-row">
            <span>Total Due</span>
            <span class="ao-anc-field ao-inv-range">
                <i>From:</i>
                <input type="text" inputmode="decimal" class="ao-of-sm" wire:model="invoiceFilter.dueFrom">
                <i>To:</i>
                <input type="text" inputmode="decimal" class="ao-of-sm" wire:model="invoiceFilter.dueTo">
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

<div class="ao-bt-save ao-inv-search-go">
    <button type="button" class="ao-mu-tab ao-bt-head-btn ao-bt-primary"
        wire:click="applyInvoiceFilter">Search</button>
    @if ($this->invoiceFilterActive())
        <button type="button" class="ao-pg-btn" wire:click="clearInvoiceFilter">Clear</button>
    @endif
</div>
