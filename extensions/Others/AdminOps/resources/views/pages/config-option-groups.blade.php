{{--
    Configurable Option Groups, to issue #42's reference: the two buttons, the navy
    Group Name / Description grid with edit and delete icons, and — because this store's
    Region choice is a ProxyPanel checkout field, not a config option — one honest line
    saying where that lives, so an empty list here does not read as something missing.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        <div class="ao-tx-tabs">
            @if ($newUrl)
                <a class="ao-mu-tab" href="{{ $newUrl }}">&#10010; Create a New Group</a>
            @endif
            <button type="button" class="ao-mu-tab {{ $duplicating ? 'ao-on' : '' }}" wire:click="toggleDuplicating">Duplicate a Group</button>
        </div>

        @if ($duplicating)
            <form class="ao-anc-card" wire:submit.prevent="duplicate">
                <label class="ao-anc-row">
                    <span>Group to Duplicate</span>
                    <select class="ao-w-40" wire:model="duplicateSource" required>
                        <option value="">Choose a group</option>
                        @foreach ($groups as $entry)
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
                <tr><th>Group Name</th><th>Description</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($groups as $entry)
                    @php $group = $entry['row']; @endphp
                    <tr>
                        <td class="ao-mu-left">
                            @if ($entry['edit'])
                                <a href="{{ $entry['edit'] }}">{{ $group->name }}</a>
                            @else
                                {{ $group->name }}
                            @endif
                        </td>
                        <td class="ao-mu-left">
                            {{ $group->description ?: '—' }}
                            <i class="ao-cat-flag">({{ $group->children_count }} {{ Str::plural('value', $group->children_count) }},
                                applied to {{ $group->products_count }} {{ Str::plural('product', $group->products_count) }})</i>
                        </td>
                        <td class="ao-mu-actions">
                            @if ($entry['edit'])
                                <a href="{{ $entry['edit'] }}" title="Edit group">
                                    <x-filament::icon icon="ri-edit-box-line" class="ao-mu-cell-icon" />
                                </a>
                            @endif
                            <button type="button" class="ao-mo-delete" title="Delete group"
                                wire:click="$set('confirming', {{ $group->id }})">
                                <x-filament::icon icon="ri-indeterminate-circle-fill" class="ao-mu-cell-icon ao-mu-icon-red" />
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        <p class="ao-gs-empty">
            The Region choice on proxy products is not a configurable option — it is the
            ProxyPanel module's own checkout field, fed live from the panel's locations, so
            it is managed under Panel Locations rather than here.
        </p>

        @if ($confirming)
            <div class="ao-mud-overlay" wire:click.self="$set('confirming', null)">
                <div class="ao-mud ao-mud-sm" role="alertdialog" aria-modal="true">
                    <div class="ao-mud-head">
                        Are you sure?
                        <button type="button" wire:click="$set('confirming', null)" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text">
                        <p>Are you sure you wish to delete this group?</p>
                        <p>Its values are deleted with it. A group applied to products will be refused.</p>
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
