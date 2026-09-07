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
                {{-- The reference's six statuses, with its own colouring on the
                     attention-seeking two. Customer-Reply is derived (an open ticket
                     whose last word was the customer's) — see displayStatus(). --}}
                <select class="ao-et-status" wire:change="setStatus($event.target.value)">
                    @foreach (\Paymenter\Extensions\Others\AdminOps\Admin\Pages\EditTicket::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected($this->displayStatus() === $value)
                            @class(['ao-et-opt-orange' => $value === 'customer_reply', 'ao-et-opt-red' => $value === 'in_progress'])>{{ $label }}</option>
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
                        {{-- The reference's editor footer: a live lines/words count. --}}
                        <div class="ao-et-count" data-ao-count>lines: 0&nbsp;&nbsp;words: 0</div>
                    @endif
                </div>

                {{-- The reference boxes the selects and the action row into one grey
                     strip under the editor (Leandro's side-by-side, 2026-09-05). --}}
                <div class="ao-et-band">
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
                            <option value="{{ $admin['id'] }}">{{ $admin['label'] }}</option>
                        @endforeach
                    </select>
                    <select wire:model="priority" title="Set Priority">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                    <select wire:model="replyStatus" title="Status after this reply sends">
                        @foreach (\Paymenter\Extensions\Others\AdminOps\Admin\Pages\EditTicket::STATUSES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
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
                </div>

                @if ($errors->any())
                    <ul class="ao-anc-errors">
                        @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                    </ul>
                @endif
            </form>
        @elseif ($tab === 'note')
            {{-- The reference's Add Note is the same editor and band as Add Reply, its
                 fourth select reading "- Set Status -" and the button "Add Note". --}}
            <form wire:submit.prevent="addNote">
                <div class="ao-ont-editor">
                    <div class="ao-ont-toolbar">
                        <button type="button" data-md="**" title="Bold"><b>B</b></button>
                        <button type="button" data-md="*" title="Italic"><i>I</i></button>
                        <button type="button" data-md-line="# " title="Heading"><b>H</b></button>
                        <button type="button" data-md-line="[Link](https://)" title="Link">&#128279;</button>
                        <button type="button" data-md-line="- " title="Bullet list">&#8226;&#8226;</button>
                        <button type="button" data-md-line="1. " title="Numbered list">1.</button>
                        <button type="button" data-md-line="> " title="Quote">&#10078;</button>
                    </div>
                    <textarea class="ao-et-editor" rows="10" wire:model="note" data-ao-message
                        placeholder="Staff-only — the client never sees notes"></textarea>
                    <div class="ao-et-count" data-ao-count>lines: 0&nbsp;&nbsp;words: 0</div>
                </div>

                <div class="ao-et-band">
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
                                <option value="{{ $admin['id'] }}">{{ $admin['label'] }}</option>
                            @endforeach
                        </select>
                        <select wire:model="priority" title="Set Priority">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                        <select wire:model="noteStatus" title="Set Status — empty leaves it unchanged">
                            <option value="">- Set Status -</option>
                            @foreach (\Paymenter\Extensions\Others\AdminOps\Admin\Pages\EditTicket::STATUSES as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ao-et-actionrow">
                        <span></span>
                        <span class="ao-et-actions-right">
                            <label class="ao-of-check">
                                <input type="checkbox" wire:model="returnToList"> Return to Ticket List
                            </label>
                            <button type="submit" class="ao-find-go">&#8617; Add Note</button>
                        </span>
                    </div>
                </div>
                @if ($errors->any())
                    <ul class="ao-anc-errors">
                        @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                    </ul>
                @endif
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
            {{-- The reference's own empty-state sentence, verbatim. --}}
            <p class="ao-gs-empty" title="Paymenter tickets have no custom-field system — the reference shows this same empty state on an install with none configured">
                No Custom Fields Setup for this Department
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
            {{-- The reference's two columns: Department / Subject / Status /
                 CC Recipients / Prevent Client Closure on the left, Client Name /
                 Assigned To / Priority / Merge Ticket on the right. --}}
            <form class="ao-find ao-of" wire:submit.prevent="saveOptions">
                <div class="ao-of-rows">
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-et-dept">Department</label>
                        <span><select id="ao-et-dept" class="ao-of-md" wire:model="department">
                            <option value="">—</option>
                            @foreach ($departments as $dept)
                                <option value="{{ $dept }}">{{ $dept }}</option>
                            @endforeach
                        </select></span>
                        {{-- The reference's Client Name is a picker (its own is a
                             type-to-search box) — the ticket's owner, correctable. --}}
                        <label class="ao-of-label" for="ao-et-client">Client Name</label>
                        <span><select id="ao-et-client" class="ao-of-md" wire:model="clientId">
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}">
                                    {{ trim($client->first_name . ' ' . $client->last_name) ?: $client->email }} - #{{ $client->id }}
                                </option>
                            @endforeach
                        </select></span>
                    </div>
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-et-subj">Subject</label>
                        <span><input id="ao-et-subj" class="ao-of-xl" type="text" wire:model="subject"></span>
                        <label class="ao-of-label" for="ao-et-assign">Assigned To</label>
                        <span><select id="ao-et-assign" class="ao-of-md" wire:model="assignedTo">
                            <option value="">None</option>
                            @foreach ($admins as $admin)
                                <option value="{{ $admin['id'] }}">{{ $admin['label'] }}</option>
                            @endforeach
                        </select></span>
                    </div>
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-et-optst">Status</label>
                        <span><select id="ao-et-optst" class="ao-of-md" wire:model="optStatus">
                            @foreach (\Paymenter\Extensions\Others\AdminOps\Admin\Pages\EditTicket::STATUSES as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
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
                        <label class="ao-of-label" for="ao-et-cc">CC Recipients</label>
                        <span><input id="ao-et-cc" class="ao-of-xl" type="text" wire:model="ccRecipients"
                            placeholder="None" title="Comma-separated — each address gets a copy of every staff reply"></span>
                        <label class="ao-of-label" for="ao-et-merge">Merge Ticket</label>
                        <span class="ao-of-inline">
                            <input id="ao-et-merge" class="ao-of-sm" type="text" inputmode="numeric" wire:model="mergeId">
                            <i>(# to combine)</i>
                        </span>
                    </div>
                    <div class="ao-of-row">
                        <span class="ao-of-label">Prevent Client Closure</span>
                        <label class="ao-of-check">
                            <input type="checkbox" wire:model="preventClosure">
                            Check to stop the client from closing this support ticket.
                        </label>
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
                </div>
                @if ($errors->any())
                    <ul class="ao-anc-errors">
                        @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                    </ul>
                @endif
                {{-- ao-et-actions rather than ao-of-buttons: the three buttons come from
                     three different button classes and only line up when the row sets one
                     height and one baseline for all of them. --}}
                <div class="ao-et-actions">
                    <button type="submit" class="ao-find-go">&#128190; Save Changes</button>
                    <button type="button" class="ao-of-go" wire:click="$set('tab', 'reply')">Cancel Changes</button>
                    <button type="button" class="ao-eo-delete" wire:click="$set('confirmingDelete', 'yes')">Delete Ticket</button>
                </div>
            </form>
        @else
            {{-- The reference's Log: a records strip, Date | Requested Action in plain
                 sentences, and the pager. --}}
            <p class="ao-et-showing">Showing 1 to {{ $logRows->count() }} of {{ $logRows->count() }} total</p>
            <table class="ao-mu-grid">
                <thead>
                    <tr><th>Date</th><th>Requested Action</th></tr>
                </thead>
                <tbody>
                    @forelse ($logRows as $row)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($row['at'])->format('m/d/Y H:i') }}</td>
                            <td class="ao-mu-left">{{ $row['action'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="ao-mu-none ao-mu-left">No Records Found</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="ao-mu-pager">
                <button type="button" disabled>&laquo; Previous</button>
                <button type="button" disabled>Next &raquo;</button>
            </div>
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

            {{-- The reference's editor footer counter, kept live as the text changes. --}}
            const recount = () => {
                const box = root.querySelector('[data-ao-message]');
                const out = root.querySelector('[data-ao-count]');
                if (!box || !out) return;
                const text = box.value;
                const lines = text === '' ? 0 : text.split('\n').length;
                const words = (text.match(/\S+/g) || []).length;
                out.textContent = 'lines: ' + lines + '  words: ' + words;
            };
            root.addEventListener('input', (event) => {
                if (event.target.matches('[data-ao-message]')) recount();
            });
        })();
    </script>
</x-filament-panels::page>
