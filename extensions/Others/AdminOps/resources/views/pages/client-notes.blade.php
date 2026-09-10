{{--
    The reference's Notes tab: the notes already on the account, then the box that adds
    another, with Make Sticky beside it.

    The profile's old Admin Notes box was one text field — writing in it overwrote whatever
    the last person wrote, and nothing recorded who or when. These are rows.
--}}
<div class="ao-cn">
    {{-- The reference bands this list like every other one on the profile. It was the only
         tab without it, which is part of why it read as a different screen. --}}
    @include('adminops::partials.records-band', [
        'total' => $rows->count(), 'page' => 1, 'perPage' => max(1, $rows->count()),
    ])

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
            {{-- The reference's formatting bar over the box. It writes Markdown into the
                 textarea, the same handler the ticket editors use, so a note stays plain
                 text — which is what the Summary panel and any export read back. --}}
            <div class="ao-ont-toolbar ao-cn-toolbar">
                <button type="button" data-md="**" title="Bold"><b>B</b></button>
                <button type="button" data-md="*" title="Italic"><i>I</i></button>
                <button type="button" data-md-line="# " title="Heading"><b>H</b></button>
                <button type="button" data-md-line="[Link](https://)" title="Link">&#128279;</button>
                <button type="button" data-md-line="- " title="Bullet list">&#8226;&#8226;</button>
                <button type="button" data-md-line="1. " title="Numbered list">1.</button>
                <button type="button" data-md-line="> " title="Quote">&#10078;</button>
            </div>

            <textarea rows="8" wire:model="newNote" data-ao-message
                placeholder="Notes for staff only — the client never sees these"></textarea>
            <p class="ao-cn-count">
                {{ str_word_count($newNote) }} words &middot; {{ strlen($newNote) }} characters
            </p>
        </div>

        <div class="ao-cn-side">
            <button type="submit" class="ao-find-go">Add New</button>
            <label class="ao-check" title="A sticky note sorts to the top of this list">
                <input type="checkbox" wire:model="newNoteSticky">
                <span>Make Sticky (Important)</span>
            </label>
        </div>
    </form>

    @error('newNote') <p class="ao-anc-errors">{{ $message }}</p> @enderror

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

{{-- The toolbar's handler, as on the ticket and template editors: wrap or prefix the
     selection and tell Livewire the box changed. --}}
<script>
    (() => {
        const root = document.currentScript.closest('.fi-page') ?? document;
        root.addEventListener('click', (event) => {
            const button = event.target.closest('[data-md], [data-md-line]');
            if (!button) return;
            const box = root.querySelector('[data-ao-message]');
            if (!box) return;
            const [start, end] = [box.selectionStart, box.selectionEnd];
            const picked = box.value.slice(start, end);
            let text;
            if (button.dataset.md !== undefined) {
                const wrap = button.dataset.md;
                text = box.value.slice(0, start) + wrap + (picked || 'text') + wrap + box.value.slice(end);
            } else {
                text = box.value.slice(0, start) + '\n' + button.dataset.mdLine + picked + box.value.slice(end);
            }
            box.value = text;
            box.dispatchEvent(new Event('input', { bubbles: true }));
            box.focus();
        });
    })();
</script>
