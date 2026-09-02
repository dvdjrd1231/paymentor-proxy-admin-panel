{{--
    Administrator Roles, to issue #49's reference: the two buttons, the navy
    Group Name / Assigned Admin Users grid, edit and guarded delete icons.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        <div class="ao-tx-tabs">
            @if ($newUrl)
                <a class="ao-mu-tab" href="{{ $newUrl }}">&#10010; Add New Role Group</a>
            @endif
            <button type="button" class="ao-mu-tab {{ $duplicating ? 'ao-on' : '' }}" wire:click="toggleDuplicating">Duplicate Role Group</button>
        </div>

        @if ($duplicating)
            <form class="ao-anc-card" wire:submit.prevent="duplicate">
                <label class="ao-anc-row">
                    <span>Role Group to Duplicate</span>
                    <select class="ao-w-40" wire:model="duplicateSource" required>
                        <option value="">Choose a role group</option>
                        @foreach ($roles as $entry)
                            <option value="{{ $entry['row']->id }}">{{ $entry['row']->name }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="ao-pr-center"><button type="submit" class="ao-find-go">Duplicate</button></div>
            </form>
            @if ($errors->any())
                <ul class="ao-anc-errors">
                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            @endif
        @endif

        <table class="ao-mu-grid">
            <thead>
                <tr><th>Group Name</th><th>Assigned Admin Users</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($roles as $entry)
                    @php $role = $entry['row']; @endphp
                    <tr>
                        <td class="ao-mu-left">
                            @if ($entry['edit'])
                                <a href="{{ $entry['edit'] }}">{{ $role->name }}</a>
                            @else
                                {{ $role->name }}
                            @endif
                        </td>
                        <td class="ao-mu-left">{{ $entry['assigned']->isEmpty() ? 'None' : $entry['assigned']->implode(', ') }}</td>
                        <td class="ao-mu-actions">
                            @if ($entry['edit'])
                                <a href="{{ $entry['edit'] }}" title="Edit role group">
                                    <x-filament::icon icon="ri-edit-box-line" class="ao-mu-cell-icon" />
                                </a>
                            @endif
                            <button type="button" class="ao-mo-delete" title="Delete role group"
                                wire:click="$set('confirming', {{ $role->id }})">
                                <x-filament::icon icon="ri-indeterminate-circle-fill" class="ao-mu-cell-icon ao-mu-icon-red" />
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        @if ($confirming)
            <div class="ao-mud-overlay" wire:click.self="$set('confirming', null)">
                <div class="ao-mud ao-mud-sm" role="alertdialog" aria-modal="true">
                    <div class="ao-mud-head">
                        Are you sure?
                        <button type="button" wire:click="$set('confirming', null)" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text">
                        <p>Are you sure you wish to delete this role group?</p>
                        <p>A group with admin users still assigned will be refused.</p>
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="$set('confirming', null)">Cancel</button>
                            <button type="button" class="ao-mud-delete" wire:click="runDelete">OK</button>
                        </span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
