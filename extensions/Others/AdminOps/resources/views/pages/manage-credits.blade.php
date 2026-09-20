{{-- WHMCS's Credit Management: the client and their balance, its note about log entries,
     the two coloured buttons, and the log itself. --}}
<x-filament-panels::page>
    <div class="ao-mu ao-cr">
        @include('adminops::partials.validation-alert')

        @php ($who = $this->customer())

        @if (! $who)
            <p class="ao-mu-none">Open this from a client profile.</p>
        @else
            <h4 class="ao-bt-h">Credit Management</h4>

            <p class="ao-cr-note">
                You can manage a client's credit balance from here. Every credit adjustment,
                either addition or removal, requires a log entry, and the descriptions you
                enter here are not visible to clients.
            </p>

            <p class="ao-cr-client">
                Client:
                <a class="ao-link" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $who->id]) }}">
                    {{ trim($who->first_name . ' ' . $who->last_name) ?: $who->email }}
                </a>
                (Balance: {{ number_format($this->balance(), 2) }} {{ $this->currency() }})
            </p>

            @if ($action === '')
                <div class="ao-cr-actions">
                    <button type="button" class="ao-pg-btn btn-success" wire:click="open('add')">Add Credit</button>
                    <button type="button" class="ao-pg-btn ao-bt-del" wire:click="open('remove')">Remove Credit</button>
                </div>
            @else
                {{-- The reference's Add / Remove form: the same three fields either way, and
                     the heading says which it is. --}}
                <h5 class="ao-cr-sub">{{ $action === 'add' ? 'Add Credit' : 'Remove Credit' }}</h5>

                <div class="ao-anc-card">
                    <label class="ao-anc-row">
                        <span>Date</span>
                        @include('adminops::partials.datepicker', [
                            'model' => 'entryDate', 'range' => false, 'id' => 'ao-cr-date',
                            'placeholder' => 'MM/DD/YYYY', 'class' => 'ao-of-md',
                        ])
                    </label>
                    <label class="ao-anc-row">
                        <span>Description</span>
                        <textarea rows="4" wire:model="description"></textarea>
                    </label>
                    <label class="ao-anc-row">
                        <span>Amount</span>
                        <input type="text" inputmode="decimal" class="ao-of-sm" wire:model="amount">
                    </label>
                </div>

                <div class="ao-bt-save">
                    <button type="button" class="ao-pg-btn ao-bt-primary"
                        wire:click="save" wire:loading.attr="disabled" wire:target="save">Save Changes</button>
                    <button type="button" class="ao-pg-btn" wire:click="cancel">Cancel</button>
                </div>
            @endif

            <table class="ao-mu-grid ao-cr-grid">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th class="ao-mu-left">Description</th>
                        <th class="ao-num">Amount</th>
                        <th>Admin</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->entries() as $entry)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($entry->entry_date)->format('d/m/Y') }}</td>
                            <td class="ao-mu-left">{{ $entry->description }}</td>
                            {{-- Signed, so a removal reads as one rather than looking like
                                 another addition. --}}
                            <td class="ao-num {{ (float) $entry->amount < 0 ? 'ao-cr-out' : 'ao-cr-in' }}">
                                {{ number_format((float) $entry->amount, 2) }} {{ $entry->currency_code }}
                            </td>
                            <td>
                                {{ trim(($entry->first_name ?? '') . ' ' . ($entry->last_name ?? ''))
                                    ?: ($entry->email ?? 'System') }}
                            </td>
                            <td class="ao-mu-actions">
                                <button type="button" class="ao-mo-delete" title="Remove this entry and undo its effect"
                                    wire:click="deleteEntry({{ $entry->id }})"
                                    wire:confirm="Remove this entry? The balance is put back by the same amount.">
                                    <x-filament::icon icon="ri-indeterminate-circle-fill" class="ao-mu-cell-icon ao-mu-icon-red" />
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="ao-mu-none">No Records Found</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="ao-pr-center ao-cr-close">
                <a class="ao-pg-btn" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $who->id]) }}">
                    Back to Client
                </a>
            </div>
        @endif
    </div>
</x-filament-panels::page>
