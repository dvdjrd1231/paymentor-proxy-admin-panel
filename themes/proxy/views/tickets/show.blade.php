{{-- Ticket thread — WHMCS "Six" style. The reply form's Alpine/Livewire upload block is
     kept identical to the default theme so file uploads keep working; only the layout
     and chrome are restyled. --}}
<div class="wf-page">
    <div class="wf-pagehead">
        <h1>{{ __('theme.ticket_word') }} #{{ $ticket->id }}</h1>
        <p>{{ $ticket->subject }}</p>
    </div>

    <div class="wf-layout wf-layout--reverse">
        {{-- ── Conversation + reply ────────────────────────────────────── --}}
        <div>
            <div class="wf-panel">
                <div class="wf-panel-heading">{{ __('theme.conversation') }}</div>
                <div class="wf-panel-body wf-thread" wire:poll.10s>
                    @foreach ($ticket->messages()->with('user')->get() as $message)
                        @php $isStaff = $message->user_id !== $ticket->user_id; @endphp
                        <div class="wf-msg {{ $isStaff ? 'wf-msg--staff' : '' }}"
                            @if ($loop->last) x-data x-init="$nextTick(() => $el.scrollIntoView({ block: 'end' }))" @endif>
                            <div class="wf-msg-head">
                                <span class="wf-msg-who">{{ $message->user->name }}</span>
                                <span class="wf-msg-when">{{ $message->created_at->diffForHumans() }}</span>
                            </div>
                            <div class="wf-msg-body">
                                <div class="prose dark:prose-invert break-words max-w-full">
                                    {!! Str::markdown($message->message, [
                                        'html_input' => 'escape',
                                        'allow_unsafe_links' => false,
                                        'renderer' => ['soft_break' => '<br>'],
                                    ]) !!}
                                </div>
                                @if ($message->attachments->count())
                                    <div class="flex flex-wrap gap-x-2">
                                        @foreach($message->attachments as $attachment)
                                            <div class="mt-2">
                                                <a href="{{ route('tickets.attachments.show', $attachment) }}"
                                                    class="text-sm rounded-md bg-gray-100 flex items-center dark:bg-gray-800 p-1 w-fit">
                                                    @if($attachment->canPreview())
                                                        <img src="{{ route('tickets.attachments.show', $attachment) }}"
                                                            alt="{{ $attachment->filename }}" class="max-w-full">
                                                    @else
                                                        <x-ri-attachment-2 class="inline-block mr-1 size-4" />
                                                        {{ $attachment->filename }}
                                                    @endif
                                                </a>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="wf-panel">
                <div class="wf-panel-heading">{{ __('ticket.reply') }}</div>
                <div class="wf-panel-body">
                    <form wire:submit.prevent="save">
                        <div wire:ignore>
                            <textarea id="editor"></textarea>
                        </div>

                        <label for="attachments" class="block text-sm font-medium mt-2">
                            {{ __('ticket.attachments') }}
                        </label>
                        <div x-data="{
                            drop: false,
                            selectedFiles: [],
                            progress: 0,
                            uploading: false,
                            handleDrop(event) {
                                this.drop = false;
                                if (event.dataTransfer.files && event.dataTransfer.files.length > 0) {
                                    this.selectedFiles = Array.from(event.dataTransfer.files);
                                    this.$refs.fileInput.files = event.dataTransfer.files;
                                    this.$refs.fileInput.dispatchEvent(new Event('change'));
                                }
                            },
                            init() {
                                this.$watch('$wire.attachments', (value) => {
                                    if (value.length == 0) {
                                        this.selectedFiles = [];
                                    }
                                });
                            }
                        }"
                            x-on:livewire-upload-start="uploading = true"
                            x-on:livewire-upload-finish="uploading = false; progress = 0;"
                            x-on:livewire-upload-progress="progress = $event.detail.progress"
                            x-on:livewire-upload-error="uploading = false; selectedFiles = []; progress = 0"
                            x-on:livewire-upload-cancel="uploading = false; progress = 0;">
                            <div class="wf-drop" @dragover.prevent="drop = true" @dragleave.prevent="drop = false"
                                @drop.prevent="handleDrop($event)" :class="{'is-over': drop}">
                                <div x-show="uploading" class="w-full text-center">
                                    <div class="mb-2 text-sm font-medium">
                                        {{ __('ticket.uploading_files') }}... (<span x-text="progress"></span>%)
                                    </div>
                                    <div class="wf-meter"><div class="wf-meter-bar" :style="{ width: `${progress}%` }"></div></div>
                                </div>
                                <template x-if="selectedFiles.length === 0 && !uploading">
                                    <div class="text-center">
                                        <label for="attachments" class="wf-drop-cta">{{ __('ticket.upload_attachments') }}</label>
                                        <span class="wf-drop-hint">{{ __('ticket.or_drag_and_drop') }}</span>
                                        <p class="wf-drop-hint">{{ __('ticket.files_max') }}</p>
                                    </div>
                                </template>
                                <div x-show="selectedFiles.length > 0 && !uploading" class="mt-2">
                                    <h4 class="text-sm font-semibold">{{ __('ticket.selected_files') }}:</h4>
                                    <div class="flex flex-wrap items-center justify-center gap-2 mt-1">
                                        <template x-for="file in selectedFiles" :key="file.name">
                                            <div class="text-sm rounded-md bg-gray-100 flex items-center gap-2 dark:bg-gray-800 p-1 py-0 w-fit">
                                                <span class="flex-1" x-text="file.name"></span>
                                                <button type="button" class="text-red-500 hover:text-red-700 text-lg h-fit"
                                                    @click="selectedFiles = selectedFiles.filter(f => f !== file); $refs.fileInput.value = ''">&times;</button>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                            <input id="attachments" type="file" multiple name="attachments[]" class="sr-only"
                                wire:model.live="attachments" x-ref="fileInput"
                                @change="selectedFiles = Array.from($event.target.files)" />
                        </div>
                        @error('attachments.*')
                            <p class="wf-error">{{ $message }}</p>
                        @enderror

                        <div class="wf-actions">
                            <button type="submit" class="wf-btn" wire:target="save">{{ __('ticket.reply') }}</button>
                            @if (!config('settings.ticket_client_closing_disabled', false) && $ticket->status !== 'closed')
                                <button type="button" class="wf-btn wf-btn--danger"
                                    x-on:click.prevent="$store.confirmation.confirm({
                                        title: '{{ __('ticket.close_ticket') }}',
                                        message: '{{ __('ticket.close_ticket_confirmation') }}',
                                        confirmText: '{{ __('common.confirm') }}',
                                        cancelText: '{{ __('common.cancel') }}',
                                        callback: () => $wire.closeTicket()
                                    })">
                                    {{ __('ticket.close_ticket') }}
                                </button>
                            @endif
                        </div>
                    </form>
                    <x-easymde-editor />
                </div>
            </div>
        </div>
        {{-- ── Sidebar: ticket details ─────────────────────────────────── --}}
        <div>
            <div class="wf-panel">
                <div class="wf-panel-heading">{{ __('ticket.ticket_details') }}</div>
                <table class="wf-table wf-table--kv">
                    <tbody>
                        <tr>
                            <th>{{ __('ticket.subject') }}</th>
                            <td>{{ $ticket->subject }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('ticket.status') }}</th>
                            <td>
                                @php
                                    $tone = match (strtolower($ticket->status)) {
                                        'open' => 'wf-label--success',
                                        'closed' => 'wf-label--danger',
                                        default => 'wf-label--warning',
                                    };
                                @endphp
                                <span class="wf-label {{ $tone }}">{{ ucfirst($ticket->status) }}</span>
                            </td>
                        </tr>
                        <tr>
                            <th>{{ __('ticket.priority') }}</th>
                            <td>{{ ucfirst($ticket->priority) }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('ticket.created_at') }}</th>
                            <td>{{ $ticket->created_at->diffForHumans() }}</td>
                        </tr>
                        @if ($ticket->department)
                            <tr>
                                <th>{{ __('ticket.department') }}</th>
                                <td>{{ $ticket->department }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
