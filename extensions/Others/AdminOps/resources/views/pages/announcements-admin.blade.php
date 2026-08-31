{{--
    Announcements, to the reference screenshots: the Add Announcement form with its
    Published? box and centred Save Changes, then the listing — Date, Title, Published,
    the edit and delete icons — with Add New Announcement above it.
--}}
<x-filament-panels::page>
    <div class="ao-mu ao-an">
        @if ($mode === 'form')
            <h4 class="ao-ano-heading">{{ $editing ? 'Edit Announcement' : 'Add Announcement' }}</h4>
            <form class="ao-anc-card" wire:submit.prevent="save">
                <label class="ao-anc-row">
                    <span>Date</span>
                    <input type="datetime-local" wire:model="date" required>
                </label>
                <label class="ao-anc-row">
                    <span>Title</span>
                    <input type="text" wire:model="headline" placeholder="What is being announced?" required>
                </label>
                <label class="ao-anc-row ao-an-body">
                    <span>Announcement</span>
                    @if ($rich)
                        {{-- The rendered view, as the portal will show it. Edits happen in
                             the source view — the toggle below switches back. --}}
                        <div class="ao-an-rendered">{!! $body ?: '<i>Nothing written yet.</i>' !!}</div>
                    @else
                        <textarea rows="10" wire:model="body"
                            placeholder="The announcement itself — HTML works here, as the portal renders it" required></textarea>
                    @endif
                </label>
                <label class="ao-anc-row">
                    <span>Published?</span>
                    <span class="ao-anc-field"><input type="checkbox" wire:model="published"></span>
                </label>
            </form>

            @if ($errors->any())
                <ul class="ao-anc-errors">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif

            <div class="ao-pr-center">
                <button type="button" class="ao-cq-addline" wire:click="backToList">Back to List</button>
                <button type="button" class="ao-cq-addline" wire:click="$toggle('rich')">Enable/Disable Rich-Text Editor</button>
                <button type="button" class="ao-find-go" wire:click="save">Save Changes</button>
            </div>

            {{-- The reference's translations block. Paymenter announcements are a single
                 text, so the languages are listed but not editable — and say why. --}}
            <h4 class="ao-ano-heading">Multi-Lingual Translations</h4>
            <ul class="ao-an-langs" title="Paymenter announcements have no per-language variants — the portal shows the one text to everyone">
                @foreach (['Arabic', 'Azerbaijani', 'Catalan', 'Chinese', 'Croatian', 'Czech', 'Danish', 'Dutch', 'Estonian', 'Farsi', 'French', 'German', 'Hebrew', 'Hungarian', 'Italian', 'Macedonian', 'Norwegian', 'Portuguese-br', 'Portuguese-pt', 'Romanian', 'Russian', 'Spanish', 'Swedish', 'Turkish', 'Ukrainian'] as $language)
                    <li>{{ $language }}</li>
                @endforeach
            </ul>
        @else
            <div class="ao-pr-center">
                <button type="button" class="ao-find-go" wire:click="openForm">Add New Announcement</button>
            </div>

            <div class="ao-mu-line">
                <span>{{ number_format($rows->total()) }} Records Found, Page {{ $rows->currentPage() }} of {{ max(1, $rows->lastPage()) }}</span>
                <label class="ao-mu-jump">
                    Jump to Page:
                    <select wire:change="jump($event.target.value)">
                        @foreach (range(1, max(1, $rows->lastPage())) as $number)
                            <option value="{{ $number }}" @selected($number === $rows->currentPage())>{{ $number }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <table class="ao-mu-grid">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Title</th>
                        <th>Published</th>
                        <th class="ao-an-icons"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row->published_at?->format('m/d/Y H:i') }}</td>
                            <td class="ao-mu-left">
                                <button type="button" class="ao-cp-link" wire:click="openForm({{ $row->id }})">{{ $row->title }}</button>
                            </td>
                            <td>
                                <button type="button" class="ao-cp-link" wire:click="togglePublished({{ $row->id }})"
                                    title="Click to {{ $row->is_active ? 'unpublish' : 'publish' }}">
                                    {{ $row->is_active ? 'Yes' : 'No' }}
                                </button>
                            </td>
                            <td class="ao-mu-actions ao-mu-iconpair">
                                <button type="button" title="Edit" wire:click="openForm({{ $row->id }})">
                                    <x-filament::icon icon="ri-edit-box-line" class="ao-mu-cell-icon" />
                                </button>
                                <button type="button" class="ao-mo-delete" title="Delete"
                                    wire:click="delete({{ $row->id }})"
                                    wire:confirm="Delete this announcement?">
                                    <x-filament::icon icon="ri-indeterminate-circle-fill" class="ao-mu-cell-icon" />
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="ao-mu-none">No Records Found</td></tr>
                    @endforelse
                </tbody>
            </table>

            <nav class="ao-mu-pages">
                <button type="button" wire:click="jump({{ $rows->currentPage() - 1 }})"
                    @disabled($rows->onFirstPage())>&laquo; Previous Page</button>
                <button type="button" wire:click="jump({{ $rows->currentPage() + 1 }})"
                    @disabled(!$rows->hasMorePages())>Next Page &raquo;</button>
            </nav>
        @endif
    </div>
</x-filament-panels::page>
