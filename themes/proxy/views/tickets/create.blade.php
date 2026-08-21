{{--
    Create Ticket — reframed into the Six page/panel structure.

    The interactive pieces (Paymenter form components, the EasyMDE editor and the
    Livewire drag-and-drop upload) are kept exactly as the default theme renders
    them, so bindings and the editor keep working. Only the surrounding container
    changes: dark bg-primary-800 box → light Six panel.
--}}
{{-- Laid out as the reference portal's Open Ticket form: the signed-in identity shown but
     locked, then the subject, then Department / Related Service / Priority across one row.

     The grid is wf-* rather than Tailwind's `grid-cols-2` / `col-span-2`, which is what was
     here before: this theme does not load the Tailwind bundle, so those class names carried
     no styling and every field stacked full width. --}}
<div class="wf-page">
    <div class="wf-title">
        <h1>{{ __('theme.open_ticket') }}</h1>
    </div>
    <hr class="wf-title-rule">

    <div class="wf-crumb">
        <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a>
        <span>/</span><a href="{{ route('dashboard') }}" wire:navigate>{{ __('theme.client_area') }}</a>
        <span>/</span><a href="{{ route('tickets') }}" wire:navigate>{{ __('theme.my_tickets') }}</a>
        <span>/</span>{{ __('ticket.create_ticket') }}
    </div>

    <p>{{ __('theme.ticket_intro') }}</p>

    <div class="wf-panel">
        <div class="wf-panel-heading">{{ __('ticket.ticket_details') }}</div>
        <div class="wf-panel-body">
            {{-- Who the ticket comes from. Read-only: it is taken from the session, and an
                 editable copy would only invite a mismatch with the account it is filed on. --}}
            <div class="wf-grid">
                <div class="wf-field">
                    <label for="ticket-name">{{ __('theme.name') }}</label>
                    <input id="ticket-name" type="text" class="wf-input wf-input--locked"
                           value="{{ Auth::user()->name }}" readonly>
                </div>
                <div class="wf-field">
                    <label for="ticket-email">{{ __('theme.email_address') }}</label>
                    <input id="ticket-email" type="email" class="wf-input wf-input--locked"
                           value="{{ Auth::user()->email }}" readonly>
                </div>
            </div>

            <x-form.input wire:model="subject" label="{{ __('ticket.subject') }}" name="subject" required />

            <div class="wf-grid-3">
                @if (count($departments) > 0)
                    <x-form.select wire:model="department" label="{{ __('ticket.department') }}" name="department" required>
                        <option value="">{{ __('ticket.select_department') }}</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department }}">{{ $department }}</option>
                        @endforeach
                    </x-form.select>
                @endif
                <x-form.select wire:model="service" label="{{ __('ticket.service') }}" name="service">
                    <option value="">{{ __('ticket.select_service') }}</option>
                    @foreach ($services as $product)
                        <option value="{{ $product->id }}">{{ $product->product->name }} ({{ ucfirst($product->status) }})
                            @if ($product->expires_at)
                                - {{ $product->expires_at->format('Y-m-d') }}
                            @endif
                        </option>
                    @endforeach
                </x-form.select>
                {{-- High first, as the reference orders it. --}}
                <x-form.select wire:model="priority" label="{{ __('ticket.priority') }}" name="priority" required>
                    <option value="high">{{ __('ticket.high') }}</option>
                    <option value="medium" selected>{{ __('ticket.medium') }}</option>
                    <option value="low">{{ __('ticket.low') }}</option>
                </x-form.select>
            </div>

            <div>
                    <div class="mt-4">
                        <form wire:submit.prevent="create" wire:ignore>
                            <label for="editor" class="block text-sm font-medium">{{ __('ticket.reply') }}</label>
                            <textarea id="editor" placeholder="Initial message"></textarea>

                            <label for="attachments" class="block text-sm font-medium mt-2">{{ __('ticket.attachments') }}</label>
                            <div x-data="{
                                    drop: false,
                                    selectedFiles: [],
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
                                            if (value.length == 0) { this.selectedFiles = []; }
                                        });
                                    }
                                }">
                                <div class="flex justify-center rounded-lg border border-dashed px-6 py-2"
                                    style="background:var(--wf-section); border-color:var(--wf-border)"
                                    @dragover.prevent="drop = true" @dragleave.prevent="drop = false"
                                    @drop.prevent="handleDrop($event)">
                                    <div class="text-center">
                                        <template x-if="selectedFiles.length === 0">
                                            <div>
                                                <div class="flex text-sm">
                                                    <label for="attachments" class="relative cursor-pointer rounded-md font-semibold"
                                                        style="color:var(--brand)">
                                                        <span>{{ __('ticket.upload_attachments') }}</span>
                                                    </label>
                                                    <p class="pl-1">{{ __('ticket.or_drag_and_drop') }}</p>
                                                </div>
                                                <p class="text-xs" style="color:var(--wf-muted)">{{ __('ticket.files_max') }}</p>
                                            </div>
                                        </template>
                                        <div x-show="selectedFiles.length > 0">
                                            <h4 class="text-sm font-semibold">{{ __('ticket.selected_files') }}:</h4>
                                            <div class="flex flex-wrap items-center justify-center gap-2 mt-1">
                                                <template x-for="file in selectedFiles" :key="file.name">
                                                    <div class="text-sm rounded-md flex items-center gap-2 p-1 py-0 w-fit"
                                                        style="background:var(--wf-section)">
                                                        <span class="flex-1" x-text="file.name"></span>
                                                        <button type="button" class="text-lg h-fit" style="color:var(--brand)"
                                                            @click="selectedFiles = selectedFiles.filter(f => f !== file)">&times;</button>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <input id="attachments" type="file" multiple name="attachments[]" class="sr-only"
                                    wire:model.live="attachments" x-ref="fileInput"
                                    @change="selectedFiles = Array.from($event.target.files)" />
                            </div>

                            <button type="submit" class="wf-btn mt-3" style="float:right">{{ __('ticket.create') }}</button>
                        </form>
                        <x-easymde-editor />
                    </div>
                </div>
        </div>
    </div>
</div>
