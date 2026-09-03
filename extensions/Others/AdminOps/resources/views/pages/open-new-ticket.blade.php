{{--
    Open New Ticket, to the reference screenshot: client block with the services table
    under it, message box with its little toolbar and Preview, Insert Knowledgebase Link /
    Insert Predefined Reply, attachments, and the one blue + Open Ticket button.
--}}
<x-filament-panels::page>
    <form class="ao-mu ao-ont" wire:submit.prevent="create">
        <div class="ao-anc-card">
            <label class="ao-anc-row">
                <span>Client</span>
                <select class="ao-w-40" wire:model.live="client" required>
                    <option value="">Start Typing to Search Clients</option>
                    @foreach ($clients as $row)
                        <option value="{{ $row->id }}">
                            {{ trim($row->first_name . ' ' . $row->last_name) ?: $row->email }} - #{{ $row->id }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="ao-anc-row">
                <span>Name</span>
                <input type="text" class="ao-w-40" value="{{ $selectedUser ? trim($selectedUser->first_name . ' ' . $selectedUser->last_name) : '' }}"
                    placeholder="Chosen by the client picker" readonly>
            </label>
            <div class="ao-anc-row">
                <span>Email Address</span>
                <span class="ao-anc-field">
                    <input type="text" class="ao-w-40" value="{{ $selectedUser?->email }}" placeholder="Chosen by the client picker" readonly>
                    <label class="ao-ont-send"><input type="checkbox" wire:model="sendEmail"> Send Email</label>
                </span>
            </div>
            <label class="ao-anc-row">
                <span>CC Recipients</span>
                <input type="text" class="ao-w-40" placeholder="Start Typing to Add or Select Recipient" disabled
                    title="Paymenter tickets have no CC list — the client and staff are notified">
            </label>
            <label class="ao-anc-row">
                <span>Subject</span>
                <input type="text" wire:model="subject" placeholder="What is this ticket about?" required>
            </label>
            <div class="ao-anc-row">
                <span>Department</span>
                <span class="ao-anc-field ao-ont-dept">
                    <select wire:model="department">
                        <option value="">—</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department }}">{{ $department }}</option>
                        @endforeach
                    </select>
                    <b>Priority</b>
                    <select wire:model="priority" class="ao-ont-priority">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </span>
            </div>
        </div>

        {{-- The reference's services strip: the picked client's products, or its ask-first line. --}}
        <table class="ao-mu-grid ao-ont-services">
            <thead>
                <tr>
                    <th></th>
                    <th>Product/Service</th>
                    <th>Amount</th>
                    <th>Billing Cycle</th>
                    <th>Signup Date</th>
                    <th>Next Due Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                {{-- The reference's radio column: the ticket is opened about one of the
                     client's services, or explicitly about None (issue #20). --}}
                @if ($services->isNotEmpty())
                    <tr>
                        <td class="ao-ont-radio"><input type="radio" name="ont-service" value="" wire:model="service" aria-label="No related service"></td>
                        <td class="ao-mu-left" colspan="6">None</td>
                    </tr>
                @endif
                @forelse ($services as $service)
                    <tr>
                        <td class="ao-ont-radio"><input type="radio" name="ont-service" value="{{ $service->id }}" wire:model="service" aria-label="Relate to service #{{ $service->id }}"></td>
                        <td class="ao-mu-left">{{ $service->product?->name ?? '—' }}</td>
                        <td>${{ number_format((float) $service->price, 2) }} {{ $service->currency_code }}</td>
                        <td>{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ProductsServices::cycle($service) }}</td>
                        <td>{{ $service->created_at?->format('m/d/Y') }}</td>
                        <td>{{ $service->expires_at?->format('m/d/Y') ?? '-' }}</td>
                        <td><span class="ao-mu-status ao-mu-st-{{ $service->status }}">{{ ucfirst($service->status) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="ao-mu-none">
                        {{ $client ? 'This client has no services' : 'Please select a client to view the related services' }}
                    </td></tr>
                @endforelse
            </tbody>
        </table>

        {{-- The reference's button under the strip: the full services screen, filtered to
             the picked client. --}}
        <div class="ao-ont-viewall">
            <a class="ao-cq-addline" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ProductsServices::getUrl($selectedUser ? ['client' => $selectedUser->email] : []) }}">
                &#9776; View All Services
            </a>
        </div>

        {{-- The message box: the reference's toolbar buttons write markdown, and Preview
             renders it server-side — the same markdown tickets already speak. --}}
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
                <textarea rows="12" wire:model="message" data-ao-message
                    placeholder="Write the first message of the ticket — markdown works here" required></textarea>
            @endif
        </div>

        <div class="ao-ont-inserts">
            <button type="button" class="ao-cp-link" wire:click="$set('inserting', 'kb')">Insert Knowledgebase Link</button>
            <button type="button" class="ao-cp-link" wire:click="$set('inserting', 'reply')">Insert Predefined Reply</button>
        </div>

        <div class="ao-anc-card ao-ont-attach">
            <div class="ao-anc-row">
                <span>Attachments<br><i>Max file size: 10MB</i></span>
                <span class="ao-anc-field">
                    <input type="file" wire:model="attachments" multiple data-ao-attach>
                    {{-- The reference's Add More reopens the picker; the input is already
                         multiple, so every pick adds to the set. --}}
                    <button type="button" class="ao-cq-addline"
                        onclick="this.closest('.ao-anc-field').querySelector('[data-ao-attach]').click()">
                        <span class="ao-ont-plus">&#10133;</span> Add More
                    </button>
                </span>
            </div>
            @error('attachments.*') <p class="ao-anc-errors">{{ $message }}</p> @enderror
        </div>

        @if ($errors->any())
            <ul class="ao-anc-errors">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <div class="ao-ont-submit">
            <button type="submit" class="ao-find-go" wire:loading.attr="disabled">+ Open Ticket</button>
        </div>

        @if ($inserting)
            <div class="ao-mud-overlay" wire:click.self="$set('inserting', null)">
                <div class="ao-mud ao-mud-sm" role="dialog" aria-modal="true">
                    <div class="ao-mud-head">
                        {{ $inserting === 'kb' ? 'Insert Knowledgebase Link' : 'Insert Predefined Reply' }}
                        <button type="button" wire:click="$set('inserting', null)" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text ao-ont-pick">
                        @if ($inserting === 'kb')
                            @forelse ($kbArticles as $article)
                                <button type="button" class="ao-cp-link"
                                    wire:click="insert('{{ addslashes('[' . $article->title . '](' . url('/knowledgebase/' . ($article->slug ?? $article->id)) . ')') }}')">
                                    {{ $article->title }}
                                </button>
                            @empty
                                <p>No knowledgebase articles yet.</p>
                            @endforelse
                        @else
                            @forelse ($cannedReplies as $reply)
                                <button type="button" class="ao-cp-link"
                                    wire:click="insert('{{ addslashes($reply->body) }}')">
                                    {{ $reply->title }}
                                </button>
                            @empty
                                <p>No predefined replies yet — add them under Support → Predefined Replies.</p>
                            @endforelse
                        @endif
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="$set('inserting', null)">Close</button>
                        </span>
                    </div>
                </div>
            </div>
        @endif
    </form>

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
                // Livewire hears textareas on input.
                box.dispatchEvent(new Event('input', { bubbles: true }));
                box.focus();
            });
        })();
    </script>
</x-filament-panels::page>
