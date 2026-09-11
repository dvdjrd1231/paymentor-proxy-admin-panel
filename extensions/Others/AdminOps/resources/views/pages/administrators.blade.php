<x-filament-panels::page>
    <div class="ao-mu">
        <div class="ao-tx-tabs">
            <button type="button" class="ao-mu-tab {{ $filter ? 'ao-on' : '' }}" wire:click="toggleFilter">Search/Filter</button>
        </div>

        @if ($filter)
            <form class="ao-find ao-of" autocomplete="off" wire:submit.prevent="$set('page', 1)">
                <div class="ao-of-rows">
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-ad-q">Name or Email</label>
                        <span><input @nofill id="ao-ad-q" class="ao-of-lg" type="text" wire:model="q"
                            placeholder="Any part of a name or address"></span>
                        <label class="ao-of-label" for="ao-ad-role">Role</label>
                        <span><select @nofill id="ao-ad-role" class="ao-of-md" wire:model="role">
                            <option value="">Any</option>
                            @foreach ($roles as $option)
                                <option value="{{ $option->id }}">{{ $option->name }}</option>
                            @endforeach
                        </select></span>
                    </div>
                </div>
                <button type="submit" class="ao-of-go">Filter</button>
            </form>
        @endif

        <div class="ao-mu-line">
            <span>{{ number_format($rows->total()) }} Records Found{{ $rows->total() > 0 ? ', Showing ' . number_format($rows->firstItem()) . ' to ' . number_format($rows->lastItem()) : '' }}</span>
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
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email Address</th>
                    <th>Role</th>
                    <th>Two-Factor</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td class="ao-mu-left">{{ trim($row->first_name . ' ' . $row->last_name) ?: '—' }}</td>
                        <td class="ao-mu-left">{{ $row->email }}</td>
                        <td>{{ $row->role?->name ?? '—' }}</td>
                        <td>{{ $row->tfa_secret ? 'Enabled' : 'Disabled' }}</td>
                        <td>{{ $row->created_at?->format('m/d/Y') }}</td>
                        <td class="ao-mu-actions">
                            @if ($canEdit($row))
                                <a class="ao-link" href="{{ \App\Admin\Resources\UserResource::getUrl('edit', ['record' => $row->id]) }}">Edit</a>
                            @endif
                            @if ($canDelete($row) && $row->id !== auth()->id())
                                <button type="button" wire:click="confirmDelete({{ $row->id }})">Delete</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        <nav class="ao-mu-pages">
            <button type="button" wire:click="jump({{ $rows->currentPage() - 1 }})" @disabled($rows->onFirstPage())>&laquo; Previous Page</button>
            <span class="ao-mu-page-now">{{ $rows->currentPage() }}</span>
            <button type="button" wire:click="jump({{ $rows->currentPage() + 1 }})" @disabled(!$rows->hasMorePages())>Next Page &raquo;</button>
        </nav>

        @if ($confirmingDelete)
            <div class="ao-mud-overlay" wire:click.self="$set('confirmingDelete', null)">
                <div class="ao-mud ao-mud-sm" role="alertdialog" aria-modal="true">
                    <div class="ao-mud-head">
                        Are you sure?
                        <button type="button" wire:click="$set('confirmingDelete', null)" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text">
                        <p>Are you sure you wish to remove administrator access from this user?</p>
                        <p>The login itself is kept as an ordinary client account, so the tickets, orders and audit trail attached to it are not lost.</p>
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="$set('confirmingDelete', null)">Cancel</button>
                            <button type="button" class="ao-mud-save" wire:click="deleteAdmin">OK</button>
                        </span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
