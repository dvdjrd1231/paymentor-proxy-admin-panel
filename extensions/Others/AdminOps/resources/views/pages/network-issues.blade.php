{{--
    Network Issues, to the reference screenshots: the Create New Issue form with the
    validation banner in the reference's words, and the Open/Scheduled/Resolved lists.
--}}
<x-filament-panels::page>
    <div class="ao-mu ao-ni">
        @if ($creating)
            @error('server')
                <div class="ao-ni-error">
                    <span class="ao-ni-error-ic" aria-hidden="true">&#10060;</span>
                    <span><b>Validation Failed</b><br>{{ $message }}</span>
                </div>
            @enderror

            <h4 class="ao-ano-heading">{{ $editing ? 'Edit Issue' : 'Create New Issue' }}</h4>
            <form class="ao-anc-card" wire:submit.prevent="save">
                <label class="ao-anc-row">
                    <span>Title</span>
                    <input type="text" wire:model="headline" placeholder="e.g. network issue" required>
                </label>
                <label class="ao-anc-row">
                    <span>Type</span>
                    <select wire:model.live="type">
                        @foreach (\Paymenter\Extensions\Others\AdminOps\Models\NetworkIssue::TYPES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ao-anc-row">
                    <span>Server</span>
                    <select wire:model="server" @disabled($type !== 'server')>
                        <option value="">None</option>
                        @foreach ($servers as $row)
                            <option value="{{ $row->id }}">{{ $row->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ao-anc-row">
                    <span>Priority</span>
                    <select wire:model="priority">
                        @foreach (\Paymenter\Extensions\Others\AdminOps\Models\NetworkIssue::PRIORITIES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ao-anc-row">
                    <span>Status</span>
                    <select wire:model="status">
                        @foreach (\Paymenter\Extensions\Others\AdminOps\Models\NetworkIssue::STATUSES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ao-anc-row">
                    <span>Start Date</span>
                    <input type="datetime-local" wire:model="startsAt" required>
                </label>
                <label class="ao-anc-row">
                    <span>End Date</span>
                    <input type="datetime-local" wire:model="endsAt">
                </label>
            </form>

            <h4 class="ao-ano-heading">Description</h4>
            <textarea class="ao-ni-desc" rows="8" wire:model="description"
                placeholder="What is affected, and what is being done about it"></textarea>

            @if ($errors->any())
                <ul class="ao-anc-errors">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif

            <div class="ao-pr-center">
                <button type="button" class="ao-cq-addline" wire:click="backToList">Back to List</button>
                <button type="button" class="ao-find-go" wire:click="save">Save Changes</button>
            </div>
        @else
            <div class="ao-mu-line">
                <span>{{ $viewLabel }} Issues: {{ number_format($rows->count()) }}</span>
                <button type="button" class="ao-find-go" wire:click="openForm">Create New</button>
            </div>

            <table class="ao-mu-grid">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Server</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Start</th>
                        <th>End</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="ao-mu-left">
                                <button type="button" class="ao-cp-link" wire:click="openForm({{ $row->id }})">{{ $row->title }}</button>
                            </td>
                            <td>{{ \Paymenter\Extensions\Others\AdminOps\Models\NetworkIssue::TYPES[$row->type] ?? ucfirst($row->type) }}</td>
                            <td>{{ $row->server?->name ?? '—' }}</td>
                            <td>{{ \Paymenter\Extensions\Others\AdminOps\Models\NetworkIssue::PRIORITIES[$row->priority] ?? ucfirst($row->priority) }}</td>
                            <td>
                                <span class="{{ $row->status === 'resolved' ? 'ao-st-closed' : ($row->status === 'outage' ? 'ao-st-open' : 'ao-st-answered') }}">
                                    {{ \Paymenter\Extensions\Others\AdminOps\Models\NetworkIssue::STATUSES[$row->status] ?? ucfirst($row->status) }}
                                </span>
                            </td>
                            <td>{{ $row->starts_at?->format('m/d/Y H:i') }}</td>
                            <td>{{ $row->ends_at?->format('m/d/Y H:i') ?? '—' }}</td>
                            <td class="ao-mu-actions">
                                <button type="button" class="ao-mo-delete" title="Delete issue"
                                    wire:click="delete({{ $row->id }})" wire:confirm="Delete this network issue?">
                                    <x-filament::icon icon="ri-indeterminate-circle-fill" class="ao-mu-cell-icon" />
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="ao-mu-none">No Records Found</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </div>
</x-filament-panels::page>
