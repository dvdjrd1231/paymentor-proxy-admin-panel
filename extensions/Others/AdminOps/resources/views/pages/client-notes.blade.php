{{--
    The reference's Notes tab: the notes already on the account, then the box that adds
    another, with Make Sticky beside it.

    The profile's old Admin Notes box was one text field — writing in it overwrote whatever
    the last person wrote, and nothing recorded who or when. These are rows.
--}}
<div class="ao-cn">
    <table class="ao-mu-grid">
        <thead>
            <tr>
                <th>Created</th>
                <th>Note</th>
                <th>Admin</th>
                <th>Last Modified</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $note)
                <tr class="{{ $note->sticky ? 'ao-cn-sticky' : '' }}">
                    <td>{{ $note->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="ao-mu-left">
                        @if ($note->sticky)
                            <span class="ao-tag ao-tag-warning" title="Pinned to the top of this list">Important</span>
                        @endif
                        {{ $note->note }}
                    </td>
                    <td>
                        {{ $note->admin
                            ? (trim($note->admin->first_name . ' ' . $note->admin->last_name) ?: $note->admin->email)
                            : 'System' }}
                    </td>
                    <td>{{ $note->updated_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="ao-mu-actions">
                        <button type="button" class="ao-mo-delete" title="Delete note"
                            wire:click="$set('confirmingNote', {{ $note->id }})">
                            <x-filament::icon icon="ri-indeterminate-circle-fill" class="ao-mu-cell-icon ao-mu-icon-red" />
                        </button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="ao-mu-none">No Records Found</td></tr>
            @endforelse
        </tbody>
    </table>

    <form class="ao-cn-add" wire:submit.prevent="addNote">
        <div class="ao-cn-editor">
            <textarea rows="8" wire:model="newNote"
                placeholder="Notes for staff only — the client never sees these"></textarea>
            <p class="ao-cn-count">
                {{ str_word_count($newNote) }} words &middot; {{ strlen($newNote) }} characters
            </p>
        </div>

        <div class="ao-cn-side">
            <button type="submit" class="ao-find-go">Add New</button>
            <label class="ao-check">
                <input type="checkbox" wire:model="newNoteSticky">
                <span>Make Sticky (Important)</span>
            </label>
        </div>
    </form>

    @error('newNote') <p class="ao-anc-errors">{{ $message }}</p> @enderror

    <p class="ao-cp-note">
        A sticky note sorts to the top of this list. The reference offers a rich-text bar over
        this box; notes here are plain text on purpose, because that is what the Summary panel
        and any future export read — markup would show as markup.
    </p>
</div>

@if ($confirmingNote)
    <div class="ao-mud-overlay" wire:click.self="$set('confirmingNote', null)">
        <div class="ao-mud ao-mud-sm" role="alertdialog" aria-modal="true">
            <div class="ao-mud-head">
                Are you sure?
                <button type="button" wire:click="$set('confirmingNote', null)" aria-label="Close">&times;</button>
            </div>
            <div class="ao-mud-text"><p>Delete this note? It cannot be brought back.</p></div>
            <div class="ao-mud-foot ao-mud-foot-only-right">
                <span class="ao-mud-foot-right">
                    <button type="button" class="ao-mud-close" wire:click="$set('confirmingNote', null)">Cancel</button>
                    <button type="button" class="ao-mud-delete" wire:click="deleteNote">OK</button>
                </span>
            </div>
        </div>
    </div>
@endif
