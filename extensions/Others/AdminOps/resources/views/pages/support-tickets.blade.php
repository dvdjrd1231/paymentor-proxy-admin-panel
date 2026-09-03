{{--
    Support Tickets, to the reference screenshots: Search/Filter and Auto Refresh tabs, the
    records line, With Selected — Merge, Close, Delete, Block Sender & Delete — above and
    below the navy grid, and the "Are you sure?" before anything destructive.
--}}
<x-filament-panels::page>
    <div class="ao-mu" @if ($refreshEvery > 0) wire:poll.{{ $refreshEvery * 60 }}s @endif>
        <div class="ao-tx-tabs">
            <button type="button" class="ao-mu-tab {{ $this->filter ? 'ao-on' : '' }}" wire:click="toggleFilter">
                Search/Filter
            </button>
            {{-- The reference's Auto Refresh tab: a real band now — the select drives the
                 page's own polling interval, and Never (the reference's default) stops it. --}}
            <button type="button" class="ao-mu-tab {{ $autoTab ? 'ao-on' : '' }}" wire:click="toggleAutoTab">
                Auto Refresh
            </button>
        </div>

        @if ($autoTab)
            <form class="ao-find ao-of" wire:submit.prevent="setAutoRefresh">
                <div class="ao-of-rows">
                    <div class="ao-of-row ao-of-row-single">
                        <label class="ao-of-label" for="ao-st-refresh">Auto Refresh Every</label>
                        <span class="ao-of-inline">
                            <select id="ao-st-refresh" class="ao-of-md" wire:model="refreshEvery">
                                <option value="0">Never</option>
                                <option value="1">1 Minute</option>
                                <option value="2">2 Minutes</option>
                                <option value="5">5 Minutes</option>
                                <option value="10">10 Minutes</option>
                            </select>
                            <button type="submit" class="ao-find-go">Set Auto Refresh</button>
                        </span>
                    </div>
                </div>
            </form>
        @endif

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
                {{-- The reference's Status is a real multi-select: a chip per picked view,
                     each removable, several OR'd together — not the sidebar's single pick.
                     See SupportTickets::$statusFilter / applyView(). --}}
                <label class="ao-stf-row ao-stf-row-top">
                    <span>Status</span>
                    <span class="ao-stf-chips">
                        @foreach ($statusFilter as $key)
                            <span class="ao-stf-chip">
                                {{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\SupportTickets::VIEWS[$key] ?? $key }}
                                <button type="button" wire:click="removeStatus('{{ $key }}')" aria-label="Remove {{ $key }}">&times;</button>
                            </span>
                        @endforeach
                        <select wire:change="addStatus($event.target.value)" class="ao-stf-chip-add">
                            <option value="">{{ $statusFilter === [] ? 'Any' : '+ Add status…' }}</option>
                            @foreach (\Paymenter\Extensions\Others\AdminOps\Admin\Pages\SupportTickets::VIEWS as $key => $label)
                                @unless (in_array($key, $statusFilter, true))
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endunless
                            @endforeach
                        </select>
                    </span>
                </label>
                <label class="ao-stf-row">
                    <span>Tags</span>
                    {{-- Real input, not disabled: Paymenter tickets carry no tag column,
                         so a search here always matches none — honest, not fake, and the
                         title says so; see SupportTickets::query(). --}}
                    <input type="text" wire:model="tags" placeholder="Any"
                        title="Paymenter tickets carry no tags — a search here will always match none">
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
