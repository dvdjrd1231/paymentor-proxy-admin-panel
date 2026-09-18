{{-- The reference's Add Billable Item, drawn in the tab body rather than on a page of its
     own: its clientsbillableitems.php keeps the profile header and tab strip and swaps only
     this panel (Leandro, 2026-09-17). --}}
<h4 class="ao-bt-h">Add Billable Item</h4>

<form class="ao-anc-card" wire:submit.prevent="saveBillable">
    <label class="ao-anc-row">
        <span>Product/Service</span>
        <select wire:model="billableServiceId">
            <option value="">None</option>
            @foreach ($this->billableServices() as $service)
                <option value="{{ $service->id }}">
                    #{{ $service->id }} — {{ $service->product?->name ?? 'product gone' }}
                </option>
            @endforeach
        </select>
    </label>

    <label class="ao-anc-row">
        <span>Description</span>
        <input type="text" wire:model="billableDescription" required>
    </label>

    {{-- One number, labelled by the radio beside it — the reference's Hours/Qty pair. --}}
    <label class="ao-anc-row">
        <span>Hours/Qty</span>
        <span class="ao-anc-field ao-bt-unit">
            <input type="number" step="0.01" min="0.01" class="ao-of-sm" wire:model="billableQuantity" required>
            <label class="ao-of-check">
                <input type="radio" value="hours" wire:model="billableUnit"> Hours
            </label>
            <label class="ao-of-check">
                <input type="radio" value="qty" wire:model="billableUnit"> Qty
            </label>
        </span>
    </label>

    <label class="ao-anc-row">
        <span>Amount</span>
        <input type="text" inputmode="decimal" class="ao-of-sm" wire:model="billableAmount" required>
    </label>

    <div class="ao-anc-row">
        <span>Invoice Action</span>
        <span class="ao-of-stack">
            @php ($item = \Paymenter\Extensions\Others\BillableItems\Models\BillableItem::class)
            <label class="ao-of-check">
                <input type="radio" value="{{ $item::ACTION_HOLD }}" wire:model="billableAction">
                Don't Invoice for Now
            </label>
            <label class="ao-of-check">
                <input type="radio" value="{{ $item::ACTION_IMMEDIATELY }}" wire:model="billableAction">
                Invoice on Next Cron Run
            </label>
            <label class="ao-of-check">
                <input type="radio" value="{{ $item::ACTION_NEXT_INVOICE }}" wire:model="billableAction">
                Add to User's Next Invoice
            </label>
            {{-- The reference's fourth action and its Recur Every row belong to its own
                 due-date model, which this platform bills from the service instead. The
                 recurrence itself is real and is kept. --}}
            <span class="ao-of-check ao-bt-recur">
                Recur Every
                <select class="ao-of-sm" wire:model="billableRecurEvery">
                    <option value="">Never</option>
                    <option value="week">Week</option>
                    <option value="month">Month</option>
                    <option value="quarter">Quarter</option>
                    <option value="year">Year</option>
                </select>
                for <input type="number" min="0" class="ao-of-xs" wire:model="billableRecurTimes"> Times
            </span>
        </span>
    </div>

    <label class="ao-anc-row">
        <span>(Next) Due Date</span>
        @include('adminops::partials.datepicker', [
            'model' => 'billableDueDate', 'range' => false, 'id' => 'ao-bt-due',
            'placeholder' => 'MM/DD/YYYY', 'class' => 'ao-of-md',
        ])
    </label>

    <label class="ao-anc-row">
        <span>Invoice Count</span>
        <input type="number" min="0" class="ao-of-sm" wire:model="billableInvoiceCount" disabled
            title="How many times this item has been invoiced — counted by the system, not set here.">
    </label>
</form>

<div class="ao-pr-center ao-bt-save">
    <button type="button" class="ao-find-go" wire:click="saveBillable"
        wire:loading.attr="disabled" wire:target="saveBillable">Save Changes</button>
    <button type="button" class="ao-pg-btn" wire:click="cancelAddBillable">Cancel</button>
</div>
