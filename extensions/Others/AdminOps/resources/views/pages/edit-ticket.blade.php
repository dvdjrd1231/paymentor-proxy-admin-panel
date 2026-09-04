{{--
    The reference's support-ticket screen: the "#id — subject" strip with the status
    select and Close, the tab row, the reply editor over the Set Department / Set
    Assignment / Set Priority / status selects, and the thread beneath, newest first.
--}}
<x-filament-panels::page>
    <div class="ao-mu ao-et">
        <div class="ao-et-head">
            <h3 class="ao-et-title">
                #{{ $ticket->id }} - {{ $ticket->subject }}
                <select class="ao-et-status" wire:change="setStatus($event.target.value)">
                    @foreach (['open' => 'Open', 'replied' => 'Answered', 'closed' => 'Closed'] as $value => $label)
                        <option value="{{ $value }}" @selected($ticket->status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </h3>
            @if ($lastReplyAgo)
                <span class="ao-et-lastreply">Last Reply: {{ $lastReplyAgo }}</span>
            @endif
        </div>
        <p class="ao-et-closeline">
            @if ($ticket->status !== 'closed')
                <button type="button" class="ao-cp-link" wire:click="setStatus('closed')">Close</button>
            @else
                <button type="button" class="ao-cp-link" wire:click="setStatus('open')">Reopen</button>
            @endif
        </p>

        {{-- The reference's seven tabs, in its order. --}}
        <div class="ao-tx-tabs">
            @foreach (['reply' => 'Add Reply', 'note' => 'Add Note', 'custom' => 'Custom Fields', 'other' => 'Other Tickets', 'clientlog' => 'Client Log', 'options' => 'Options', 'log' => 'Log'] as $key => $label)
                <button type="button" class="ao-mu-tab {{ $tab === $key ? 'ao-on' : '' }}" wire:click="$set('tab', '{{ $key }}')">{{ $label }}</button>
            @endforeach
        </div>

        @if ($tab === 'reply')
            <form wire:submit.prevent="sendReply">
                {{-- The reference's toolbar and blue Preview — the buttons write
                     markdown, Preview renders it server-side (same as Open New Ticket). --}}
                <div class="ao-ont-editor">
                    <div class="ao-ont-toolbar">
                        <button type="button" data-md="**" title="Bold"><b>B</b></button>
                        <button type="button" data-md="*" title="Italic"><i>I</i></button>
                        <button type="button" data-md-line="# " title="Heading"><b>H</b></button>
                        <button type="button" data-md-line="[Link](https://)" title="Link">&#128279;</button>
                        <button type="button" data-md-line="- " title="Bullet list">&#8226;&#8226;</button>
                        <button type="button" data-md-line="1. " title="Numbered list">1.</button>
                        <button type="button" data-md-line="> " title="Quote">&#10078;</button>
                        <button type="button" class="ao-ont-preview {{ $preview ? 'ao-on' : '' }}"
                            wire:click="$toggle('preview')" title="Preview">&#128269; Preview</button>
                    </div>
                    @if ($preview)
                        <div class="ao-ont-rendered">{!! $rendered !!}</div>
                    @else
                        <textarea class="ao-et-editor" rows="10" wire:model="reply" data-ao-message
                            placeholder="Write the reply — markdown works here"></textarea>
                    @endif
                </div>

                <div class="ao-et-setrow">
                    <select wire:model="department" title="Set Department">
                        <option value="">- Set Department -</option>
                        @foreach ($departments as $dept)
                            <option value="{{ $dept }}">{{ $dept }}</option>
                        @endforeach
                    </select>
                    <select wire:model="assignedTo" title="Set Assignment">
                        <option value="">- Set Assignment -</option>
                        @foreach ($admins as $admin)
                            <option value="{{ $admin->id }}">{{ trim($admin->first_name . ' ' . $admin->last_name) ?: $admin->email }}</option>
                        @endforeach
                    </select>
                    <select wire:model="priority" title="Set Priority">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                    <select wire:model="replyStatus" title="Status after this reply sends">
                        <option value="replied">Answered</option>
                        <option value="open">Open</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>

                <div class="ao-et-actionrow">
                    <span class="ao-et-actions-left">
                        <label class="ao-of-go ao-et-attach">
                            Attach Files
                            <input type="file" multiple wire:model="attachments" class="ao-et-attach-input">
                        </label>
                        @if ($canned->isNotEmpty())
                            <select class="ao-et-canned" wire:change="insertCanned($event.target.value); $event.target.value = ''">
                                <option value="">Insert Predefined Reply</option>
                                @foreach ($canned as $response)
                                    <option value="{{ $response->id }}">{{ $response->title }}</option>
                                @endforeach
                            </select>
                        @endif
                        @if ($attachments)
                            <i>{{ count($attachments) }} file(s) ready</i>
                        @endif
                    </span>
                    <span class="ao-et-actions-right">
                        <label class="ao-of-check">
                            <input type="checkbox" wire:model="returnToList"> Return to Ticket List
                        </label>
                        <button type="submit" class="ao-find-go">&#8617; Reply</button>
                    </span>
                </div>

                @if ($errors->any())
                    <ul class="ao-anc-errors">
                        @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                    </ul>
                @endif
            </form>
        @elseif ($tab === 'note')
            <form wire:submit.prevent="addNote">
                <textarea class="ao-et-editor" rows="6" wire:model="note"
                    placeholder="Staff-only — the client never sees notes"></textarea>
                <div class="ao-et-actionrow">
                    <span></span>
                    <button type="submit" class="ao-find-go">Add Note</button>
                </div>
            </form>
            @foreach ($notes as $row)
                <div class="ao-et-note">
                    <div class="ao-et-msg-head">
                        <b>{{ trim(($row->author->first_name ?? '') . ' ' . ($row->author->last_name ?? '')) ?: ($row->author->email ?? 'Staff') }}</b>
                        <span>{{ $row->created_at?->format('l jS F Y \a\t H:i') }}</span>
                    </div>
                    <p>{{ $row->body }}</p>
                </div>
            @endforeach
        @elseif ($tab === 'custom')
            {{-- The reference's tab, honestly empty: Paymenter tickets carry no custom
                 field definitions, so there is nothing to fill in. --}}
            <p class="ao-gs-empty" title="Paymenter tickets have no custom-field system — the reference shows this same empty state on an install with none configured">
                No Custom Fields Found
            </p>
        @elseif ($tab === 'clientlog')
            {{-- The reference's Client Log: what this ticket's client has been doing,
                 from the same audit trail the Client Profile's Log tab reads. --}}
            <table class="ao-mu-grid">
                <thead>
                    <tr><th>Date</th><th>Event</th><th>Record</th><th>Changes</th></tr>
                </thead>
                <tbody>
                    @forelse ($clientLogRows as $row)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($row->created_at)->format('m/d/Y H:i') }}</td>
                            <td>{{ ucfirst($row->event) }}</td>
                            <td class="ao-mu-left">{{ class_basename($row->auditable_type) }} #{{ $row->auditable_id }}</td>
                            <td class="ao-mu-left"><code>{{ str($row->new_values)->limit(100) }}</code></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="ao-mu-none ao-mu-left">No Records Found</td></tr>
                    @endforelse
                </tbody>
            </table>
        @elseif ($tab === 'other')
            <table class="ao-mu-grid">
                <thead>
                    <tr><th>ID</th><th>Subject</th><th>Status</th><th>Last Updated</th></tr>
                </thead>
                <tbody>
                    @forelse ($otherTickets as $other)
                        <tr>
                            <td><a class="ao-link" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\EditTicket::getUrl(['record' => $other->id]) }}">{{ $other->id }}</a></td>
                            <td class="ao-mu-left"><a class="ao-link" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\EditTicket::getUrl(['record' => $other->id]) }}">{{ $other->subject }}</a></td>
                            <td>{{ ['open' => 'Open', 'replied' => 'Answered', 'closed' => 'Closed'][$other->status] ?? ucfirst($other->status) }}</td>
                            <td>{{ $other->updated_at?->format('m/d/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="ao-mu-none ao-mu-left">No Records Found</td></tr>
                    @endforelse
                </tbody>
            </table>
        @elseif ($tab === 'options')
            <form class="ao-find ao-of" wire:submit.prevent="saveOptions">
                <div class="ao-of-rows">
                    <div class="ao-of-row ao-of-row-single">
                        <label class="ao-of-label" for="ao-et-subj">Subject</label>
                        <span><input id="ao-et-subj" class="ao-of-xl" type="text" wire:model="subject"></span>
                    </div>
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-et-dept">Department</label>
                        <span><select id="ao-et-dept" class="ao-of-md" wire:model="department">
                            <option value="">—</option>
                            @foreach ($departments as $dept)
                                <option value="{{ $dept }}">{{ $dept }}</option>
                            @endforeach
                        </select></span>
                        <label class="ao-of-label" for="ao-et-prio">Priority</label>
                        <span><select id="ao-et-prio" class="ao-of-sm" wire:model="priority">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select></span>
                    </div>
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-et-assign">Assigned To</label>
                        <span><select id="ao-et-assign" class="ao-of-md" wire:model="assignedTo">
                            <option value="">None</option>
                            @foreach ($admins as $admin)
                                <option value="{{ $admin->id }}">{{ trim($admin->first_name . ' ' . $admin->last_name) ?: $admin->email }}</option>
                            @endforeach
                        </select></span>
                        <span class="ao-of-label">Related Service</span>
                        <span class="ao-eo-fact">
                            @if ($ticket->service)
                                <a class="ao-link" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::serviceUrl($ticket->service) }}">
                                    #{{ $ticket->service->id }} · {{ $ticket->service->product?->name }}
                                </a>
                            @else
                                None
                            @endif
                        </span>
                    </div>
                    <div class="ao-of-row">
                        <span class="ao-of-label">Requestor</span>
                        <span class="ao-eo-fact">
                            <a class="ao-link" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $ticket->user_id]) }}">
                                {{ trim(($ticket->user->first_name ?? '') . ' ' . ($ticket->user->last_name ?? '')) ?: ($ticket->user->email ?? '—') }}
                            </a>
                        </span>
                        <span class="ao-of-label"></span>
                        <span></span>
                    </div>
                </div>
                <div class="ao-of-buttons">
                    <button type="submit" class="ao-find-go">Save Changes</button>
                    <button type="button" class="ao-eo-delete" wire:click="$set('confirmingDelete', 'yes')">Delete Ticket</button>
                </div>
            </form>
        @else
            <table class="ao-mu-grid">
                <thead>
                    <tr><th>Date</th><th>Event</th><th>Changes</th></tr>
                </thead>
                <tbody>
                    @forelse ($logRows as $row)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($row->created_at)->format('m/d/Y H:i') }}</td>
                            <td>{{ ucfirst($row->event) }}</td>
                            <td class="ao-mu-left"><code>{{ str($row->new_values)->limit(120) }}</code></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="ao-mu-none ao-mu-left">No Records Found</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endif

        {{-- The thread, newest first, as the reference stacks it under the editor. --}}
        @foreach ($messages as $message)
            @php $isStaff = $message->user?->role_id !== null; @endphp
            <div class="ao-et-msg {{ $isStaff ? 'ao-et-msg-staff' : '' }}">
                <div class="ao-et-msg-side">
                    <b>{{ trim(($message->user->first_name ?? '') . ' ' . ($message->user->last_name ?? '')) ?: ($message->user->email ?? '—') }}</b>
                    @if ($isStaff)
                        <span class="ao-et-operator">OPERATOR</span>
                    @endif
                    {{-- The reference's Edit + Delete pair under the poster. --}}
                    <span class="ao-et-msg-btns">
                        <button type="button" class="ao-of-go ao-et-msg-edit"
                            wire:click="startEditMessage({{ $message->id }})">Edit</button>
                        <button type="button" class="ao-eo-delete ao-et-msg-delete"
                            wire:click="deleteMessage({{ $message->id }})"
                            wire:confirm="Delete this message?">Delete</button>
                    </span>
                </div>
                <div class="ao-et-msg-body">
                    <div class="ao-et-msg-posted">Posted on {{ $message->created_at?->format('l jS F Y \a\t H:i') }}</div>
                    @if ($editingMessage === $message->id)
                        <textarea class="ao-et-editor" rows="5" wire:model="editingText"></textarea>
                        <div class="ao-et-msg-editrow">
                            <button type="button" class="ao-find-go" wire:click="saveMessage">Save</button>
                            <button type="button" class="ao-of-go" wire:click="$set('editingMessage', null)">Cancel</button>
                        </div>
                    @else
                        <div class="ao-et-msg-text">{!! \Illuminate\Support\Str::markdown(e($message->message)) !!}</div>
                    @endif
                    @foreach ($message->attachments as $attachment)
                        <p class="ao-et-msg-file">&#128206; {{ $attachment->filename }}</p>
                    @endforeach
                </div>
            </div>
        @endforeach

        @if ($confirmingDelete)
            <div class="ao-mud-overlay" wire:click.self="$set('confirmingDelete', null)">
                <div class="ao-mud ao-mud-sm" role="alertdialog" aria-modal="true">
                    <div class="ao-mud-head">
                        Are you sure?
                        <button type="button" wire:click="$set('confirmingDelete', null)" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text">
                        <p>Delete ticket #{{ $ticket->id }} and its whole thread?</p>
                        <p>This cannot be undone.</p>
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="$set('confirmingDelete', null)">Cancel</button>
                            <button type="button" class="ao-mud-delete" wire:click="runDeleteTicket">Delete</button>
                        </span>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- The toolbar's markdown insertion — the same handler Open New Ticket carries. --}}
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
</x-filament-panels::page>
