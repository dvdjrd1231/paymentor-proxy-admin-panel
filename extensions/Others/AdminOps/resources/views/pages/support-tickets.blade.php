{{--
    Support Tickets, to the reference screenshots: Search/Filter and Auto Refresh tabs, the
    records line, With Selected — Merge, Close, Delete, Block Sender & Delete — above and
    below the navy grid, and the "Are you sure?" before anything destructive.
--}}
<x-filament-panels::page>
    <div class="ao-mu" wire:poll.120s>
        <div class="ao-tx-tabs">
            <button type="button" class="ao-mu-tab {{ $this->filter ? 'ao-on' : '' }}" wire:click="toggleFilter">
                Search/Filter
            </button>
            {{-- The page already refreshes itself every two minutes (wire:poll); the tab
                 names the behaviour rather than switching anything. --}}
            <span class="ao-mu-tab ao-tx-tab-dead" title="This list refreshes itself every two minutes">Auto Refresh</span>
        </div>

        @if ($this->filter)
            {{-- The reference's framed filter: labels on the left, one control per row,
                 the centred blue Search/Filter. Every row is a live filter. --}}
            <form class="ao-stf" wire:submit.prevent="search">
                <label class="ao-stf-row">
                    <span>Client</span>
                    <select wire:model="clientId">
                        <option value="">Start Typing to Search Clients</option>
                        @foreach ($clients as $row)
                            <option value="{{ $row->id }}">
                                {{ trim($row->first_name . ' ' . $row->last_name) ?: $row->email }} - #{{ $row->id }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label class="ao-stf-row">
                    <span>Department</span>
                    <select wire:model="dept">
                        <option value="">Any</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department }}">{{ $department }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ao-stf-row">
                    <span>Status</span>
                    <select wire:model="tab">
                        @foreach (\Paymenter\Extensions\Others\AdminOps\Admin\Pages\SupportTickets::VIEWS as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ao-stf-row">
                    <span>Tags</span>
                    <input type="text" value="Any" disabled title="Paymenter tickets have no tags">
                </label>
                <label class="ao-stf-row">
                    <span>Priority</span>
                    <select wire:model="prio">
                        <option value="">Any</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </label>
                <label class="ao-stf-row">
                    <span>Subject/Message</span>
                    <input type="text" wire:model="q" placeholder="Words from the subject">
                </label>
                <label class="ao-stf-row">
                    <span>Email Address</span>
                    <input type="text" class="ao-stf-mid" wire:model="email" placeholder="user@example.com">
                </label>
                <label class="ao-stf-row">
                    <span>Ticket ID</span>
                    <input type="text" class="ao-stf-small" wire:model="tid" placeholder="e.g. 86">
                </label>
                <label class="ao-stf-row">
                    <span>Assigned To</span>
                    <select class="ao-stf-small" wire:model="assigned">
                        <option value="">Any</option>
                        @foreach ($admins as $admin)
                            <option value="{{ $admin->id }}">{{ trim($admin->first_name . ' ' . $admin->last_name) ?: $admin->email }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="ao-stf-submit">
                    <button type="submit" class="ao-find-go">Search/Filter</button>
                </div>
            </form>
        @endif

        <div class="ao-mu-line">
            <span>
                {{ number_format($tickets->total()) }} Records Found{{ $tickets->total() > 0 ? ', Showing ' . number_format($tickets->firstItem()) . ' to ' . number_format($tickets->lastItem()) : '' }}
            </span>
            <label class="ao-mu-jump">
                Jump to Page:
                <select wire:change="jump($event.target.value)">
                    @foreach (range(1, max(1, $tickets->lastPage())) as $number)
                        <option value="{{ $number }}" @selected($number === $tickets->currentPage())>{{ $number }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="ao-st-bulk">
            With Selected:
            <button type="button" wire:click="ask('merge')">Merge</button>
            <button type="button" wire:click="ask('close')">Close</button>
            <button type="button" class="ao-st-danger" wire:click="ask('delete')">Delete</button>
            <button type="button" class="ao-st-danger" wire:click="ask('block')">Block Sender &amp; Delete</button>
        </div>

        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th class="ao-mu-check"><input type="checkbox" data-ao-check-all></th>
                    <th class="ao-st-flag"></th>
                    <th>Department</th>
                    <th>Subject</th>
                    <th>Requestor</th>
                    <th>Status</th>
                    <th>Last Reply</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tickets as $ticket)
                    @php
                        $open = \App\Admin\Resources\TicketResource::getUrl('edit', ['record' => $ticket->id]);
                        $last = $ticket->messages->first()?->created_at ?? $ticket->updated_at;
                        $statusWord = ['open' => 'Open', 'replied' => 'Answered', 'closed' => 'Closed'][$ticket->status] ?? ucfirst($ticket->status);
                        $statusClass = ['open' => 'ao-st-open', 'replied' => 'ao-st-answered', 'closed' => 'ao-st-closed'][$ticket->status] ?? '';
                    @endphp
                    <tr>
                        <td class="ao-mu-check"><input type="checkbox" wire:model="selected.{{ $ticket->id }}"></td>
                        <td class="ao-st-flag">
                            @if ($ticket->assigned_to)
                                <span title="Assigned to {{ $ticket->assignedTo?->name }}">&#9873;</span>
                            @endif
                        </td>
                        <td>{{ $ticket->department ?: '—' }}</td>
                        <td class="ao-mu-left"><a href="{{ $open }}">#{{ $ticket->id }} - {{ $ticket->subject }}</a></td>
                        <td>
                            <a href="{{ $ticket->user_id ? \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $ticket->user_id]) : $open }}">
                                {{ trim(($ticket->user->first_name ?? '') . ' ' . ($ticket->user->last_name ?? '')) ?: ($ticket->user->email ?? '—') }}
                            </a>
                            @if ($ticket->assignedTo)
                                <i class="ao-st-operator">OPERATOR</i>
                            @endif
                        </td>
                        <td><span class="{{ $statusClass }}">{{ $statusWord }}</span></td>
                        <td>{{ $last?->diffForHumans(short: true) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="ao-st-bulk">
            With Selected:
            <button type="button" wire:click="ask('merge')">Merge</button>
            <button type="button" wire:click="ask('close')">Close</button>
            <button type="button" class="ao-st-danger" wire:click="ask('delete')">Delete</button>
            <button type="button" class="ao-st-danger" wire:click="ask('block')">Block Sender &amp; Delete</button>
        </div>

        <nav class="ao-mu-pages">
            <button type="button" wire:click="jump({{ $tickets->currentPage() - 1 }})"
                @disabled($tickets->onFirstPage())>&laquo; Previous Page</button>
            <span class="ao-mu-page-now">{{ $tickets->currentPage() }}</span>
            <button type="button" wire:click="jump({{ $tickets->currentPage() + 1 }})"
                @disabled(!$tickets->hasMorePages())>Next Page &raquo;</button>
        </nav>

        @if ($confirming)
            @php
                $question = [
                    'merge' => 'Are you sure you wish to merge the selected ticket(s)?',
                    'close' => 'Are you sure you wish to close the selected ticket(s)?',
                    'delete' => 'Are you sure you wish to delete the selected ticket(s)? This cannot be undone.',
                    'block' => 'Are you sure you wish to block the sender(s) and delete the selected ticket(s)? This cannot be undone.',
                ][$confirming];
            @endphp
            <div class="ao-mud-overlay" wire:click.self="$set('confirming', null)">
                <div class="ao-mud ao-mud-sm" role="alertdialog" aria-modal="true">
                    <div class="ao-mud-head">
                        Are you sure?
                        <button type="button" wire:click="$set('confirming', null)" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text"><p>{{ $question }}</p></div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="$set('confirming', null)">Cancel</button>
                            <button type="button" class="{{ in_array($confirming, ['delete', 'block'], true) ? 'ao-mud-delete' : 'ao-mud-save' }}" wire:click="run">OK</button>
                        </span>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <script>
        (() => {
            const root = document.currentScript.closest('.fi-page') ?? document;
            root.addEventListener('change', (event) => {
                if (!event.target.matches('[data-ao-check-all]')) return;
                for (const box of root.querySelectorAll('tbody input[type=checkbox]')) {
                    if (box.checked !== event.target.checked) {
                        box.checked = event.target.checked;
                        // Livewire binds checkboxes on change, so the tick must say so.
                        box.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            });
        })();
    </script>
</x-filament-panels::page>
