{{-- The reference's Add Time Billing Entries: ten blank rows logged in one go, priced as
     hours at a rate. A saved row is an ordinary billable item — what differs is the entry,
     not the record (Leandro, 2026-09-17). --}}
<h4 class="ao-bt-h">Add Time Billing Entries</h4>

<table class="ao-mu-grid ao-bt-time">
    <thead>
        <tr>
            <th>Item</th>
            <th class="ao-mu-left">Description</th>
            <th class="ao-num">Hours</th>
            <th class="ao-num">Rate</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($timeRows as $i => $row)
            <tr>
                <td>
                    <select wire:model="timeRows.{{ $i }}.service_id">
                        <option value="">None</option>
                        @foreach ($this->billableServices() as $service)
                            <option value="{{ $service->id }}">
                                #{{ $service->id }} — {{ $service->product?->name ?? 'product gone' }}
                            </option>
                        @endforeach
                    </select>
                </td>
                <td class="ao-mu-left">
                    <input type="text" class="ao-bt-time-desc" wire:model="timeRows.{{ $i }}.description">
                </td>
                <td class="ao-num">
                    <input type="number" step="0.01" min="0" class="ao-of-xs"
                        wire:model="timeRows.{{ $i }}.hours">
                </td>
                <td class="ao-num">
                    <input type="text" inputmode="decimal" class="ao-of-sm"
                        wire:model="timeRows.{{ $i }}.rate">
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- The reference has no Cancel here: its tab strip is the way out, and ours now closes
     the form on a tab click too. Its button is a plain one, not the primary. --}}
<div class="ao-bt-save">
    <button type="button" class="ao-pg-btn" wire:click="saveTimeEntries"
        wire:loading.attr="disabled" wire:target="saveTimeEntries">Add Entries</button>
</div>
