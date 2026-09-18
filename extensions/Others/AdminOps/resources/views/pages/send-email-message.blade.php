{{-- WHMCS's Send Email Message (sendmessage.php), field for field: who it is from and who
     it matched, the editor with its toolbar, attachments, Save Message, and the merge fields
     staff write against. --}}
<x-filament-panels::page>
    <form class="ao-mu ao-sm" wire:submit.prevent="send">
        @include('adminops::partials.validation-alert')

        <div class="ao-anc-card">
            <label class="ao-anc-row">
                <span>From</span>
                <span class="ao-anc-field ao-sm-from">
                    <input type="text" class="ao-of-md" value="{{ $this->fromName() }}" disabled>
                    <input type="text" class="ao-of-lg" value="{{ $this->fromAddress() }}" disabled>
                </span>
            </label>

            {{-- The reference states who it matched rather than offering a picker: the
                 message belongs to one client, and a resend must go back to them. --}}
            <div class="ao-anc-row">
                <span>Recipients</span>
                <span class="ao-anc-field ao-sm-to">
                    <span class="ao-sm-box">
                        @if ($who = $this->recipient())
                            <b>1 recipient matched sending criteria</b>
                            <span>{{ trim(($who->first_name ?? '') . ' ' . ($who->last_name ?? '')) ?: $who->email }}
                                &lt;{{ $who->email }}&gt;</span>
                        @else
                            <b>No recipient</b>
                            <span>Open this from a client profile.</span>
                        @endif
                    </span>
                    <i class="ao-anc-hint">Emails are sent individually<br>so email addresses won't be seen by others</i>
                </span>
            </div>

            <label class="ao-anc-row">
                <span>CC</span>
                <span class="ao-anc-field ao-sm-cc">
                    <input type="text" wire:model="cc">
                    <i class="ao-anc-hint">Comma separate emails</i>
                </span>
            </label>

            <label class="ao-anc-row">
                <span>BCC</span>
                <span class="ao-anc-field ao-sm-cc">
                    <input type="text" wire:model="bcc">
                    <i class="ao-anc-hint">Comma separate emails</i>
                </span>
            </label>

            <label class="ao-anc-row">
                <span>Subject</span>
                <input type="text" wire:model="subject" required>
            </label>
        </div>

        {{-- The editor. Rich text is a toolbar over a contenteditable pane, which keeps the
             reference's formatting without pulling a third-party editor into the admin; the
             toggle below swaps it for the raw text, as the reference's does. --}}
        <div class="ao-sm-editor">
            @if ($richText)
                <div class="ao-sm-toolbar" x-data="{
                        run(cmd, value = null) {
                            this.$refs.pane.focus();
                            document.execCommand(cmd, false, value);
                            this.sync();
                        },
                        link() {
                            const url = window.prompt('Link address');
                            if (url) this.run('createLink', url);
                        },
                        sync() { $wire.set('body', this.$refs.pane.innerHTML, false); },
                    }">
                    <div class="ao-sm-tools">
                        <select x-on:change="run('formatBlock', $event.target.value)">
                            <option value="P">Paragraph</option>
                            <option value="H1">Heading 1</option>
                            <option value="H2">Heading 2</option>
                            <option value="H3">Heading 3</option>
                        </select>
                        <button type="button" x-on:click="run('bold')" title="Bold"><b>B</b></button>
                        <button type="button" x-on:click="run('italic')" title="Italic"><i>I</i></button>
                        <button type="button" x-on:click="run('underline')" title="Underline"><u>U</u></button>
                        <button type="button" x-on:click="run('strikeThrough')" title="Strikethrough"><s>S</s></button>
                        <span class="ao-sm-sep"></span>
                        <button type="button" x-on:click="run('insertUnorderedList')" title="Bulleted list">&bull; List</button>
                        <button type="button" x-on:click="run('insertOrderedList')" title="Numbered list">1. List</button>
                        <button type="button" x-on:click="run('outdent')" title="Outdent">&larr;</button>
                        <button type="button" x-on:click="run('indent')" title="Indent">&rarr;</button>
                        <span class="ao-sm-sep"></span>
                        <button type="button" x-on:click="link()" title="Insert link">Link</button>
                        <button type="button" x-on:click="run('unlink')" title="Remove link">Unlink</button>
                        <button type="button" x-on:click="run('removeFormat')" title="Clear formatting">Clear</button>
                        <span class="ao-sm-sep"></span>
                        <button type="button" x-on:click="run('undo')" title="Undo">&#8630;</button>
                        <button type="button" x-on:click="run('redo')" title="Redo">&#8631;</button>
                    </div>

                    {{-- wire:ignore so Livewire does not re-render the pane under the cursor
                         while it is being typed in; the value is pushed on input instead. --}}
                    <div class="ao-sm-pane" x-ref="pane" contenteditable="true" wire:ignore
                        x-on:input="sync()" x-on:blur="sync()">{!! $body !!}</div>
                </div>
            @else
                <textarea class="ao-sm-body" wire:model="body" rows="18" required></textarea>
            @endif
        </div>

        <div class="ao-sm-underbar">
            <button type="button" class="ao-pg-btn" wire:click="togglePreview">
                {{ $preview ? 'Hide Preview' : 'Message Preview' }}
            </button>
            <button type="button" class="ao-pg-btn" wire:click="toggleRichText">
                Enable/Disable Rich-Text Editor
            </button>
        </div>

        @if ($preview)
            {{-- What the client will actually receive: merge fields filled in, not the
                 tokens as typed. --}}
            <div class="ao-anc-card ao-sm-preview">
                <b>{{ $this->merge($subject, $this->recipient()) }}</b>
                <div class="ao-sm-preview-body">
                    @if ($richText)
                        {!! $this->rendered() !!}
                    @else
                        {!! nl2br(e($this->rendered())) !!}
                    @endif
                </div>
            </div>
        @endif

        <div class="ao-anc-card">
            {{-- The reference stacks one Choose File per attachment, with Add More opening
                 the next row beneath the first. --}}
            <div class="ao-anc-row">
                <span>Attachments</span>
                <span class="ao-anc-field ao-sm-att">
                    @for ($i = 0; $i < $attachmentRows; $i++)
                        <span class="ao-sm-att-row">
                            <input type="file" wire:model="attachments.{{ $i }}">
                            @if ($i === 0)
                                <button type="button" class="ao-sm-addmore" wire:click="addAttachmentRow">
                                    <x-filament::icon icon="ri-add-circle-fill" class="ao-sm-addmore-ic" />
                                    Add More
                                </button>
                            @endif
                        </span>
                    @endfor
                    <span wire:loading wire:target="attachments" class="ao-anc-hint">Uploading…</span>
                    <i class="ao-anc-hint">Up to 100 MB each.</i>
                </span>
            </div>

            <label class="ao-anc-row">
                <span>Save Message</span>
                <span class="ao-anc-field ao-sm-save">
                    <label class="ao-of-check">
                        <input type="checkbox" wire:model.live="saveMessage">
                        Check to save and enter save name:
                    </label>
                    {{-- Enabled and white, as the reference draws it: staff type the name
                         first and tick afterwards as often as the other way round. --}}
                    <input type="text" class="ao-sm-savename" wire:model="saveName">
                </span>
            </label>
        </div>

        <div class="ao-pr-center ao-sm-send">
            <button type="submit" class="ao-pg-btn ao-bt-primary"
                wire:loading.attr="disabled" wire:target="send">Send Message &raquo;</button>
        </div>

        {{-- The reference lists what can be written into a message. Every token here is
             filled on send; one with nothing behind it becomes empty rather than going out
             as literal text. {@see SendEmailMessage::MERGE_FIELDS} --}}
        <h4 class="ao-bt-h">Available Merge Fields</h4>

        <div class="ao-anc-card ao-sm-merge">
            <table class="ao-mu-grid">
                <thead>
                    <tr><th class="ao-mu-left">Client Related</th><th class="ao-mu-left">Tag</th></tr>
                </thead>
                <tbody>
                    @foreach (\Paymenter\Extensions\Others\AdminOps\Admin\Pages\SendEmailMessage::MERGE_FIELDS as $token => $label)
                        <tr>
                            <td class="ao-mu-left">{{ $label }}</td>
                            <td class="ao-mu-left"><code>{{ '{$' . $token . '}' }}</code></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="ao-anc-card ao-sm-load">
            <label class="ao-anc-row">
                <span>Load Saved Message</span>
                <span class="ao-anc-field ao-sm-loadrow">
                    <select wire:model="loadName">
                        <option value="">Choose...</option>
                        @foreach ($this->savedMessages() as $saved)
                            <option value="{{ $saved->name }}">{{ $saved->name }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="ao-pg-btn" wire:click="loadSavedMessage">Load Message</button>
                </span>
            </label>
        </div>
    </form>
</x-filament-panels::page>
