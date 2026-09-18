{{-- WHMCS's Send Email Message: From and Recipients stated rather than asked for, then the
     fields staff actually change before it goes out. --}}
<x-filament-panels::page>
    <form class="ao-mu" wire:submit.prevent="send">
        @include('adminops::partials.validation-alert')

        <h4 class="ao-bt-h">Send Email Message</h4>

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
                    <i class="ao-anc-hint">Emails are sent individually so addresses are not seen by others.</i>
                </span>
            </div>

            <label class="ao-anc-row">
                <span>CC</span>
                <span class="ao-anc-field">
                    <input type="text" wire:model="cc">
                    <i class="ao-anc-hint">Comma separate emails</i>
                </span>
            </label>

            <label class="ao-anc-row">
                <span>BCC</span>
                <span class="ao-anc-field">
                    <input type="text" wire:model="bcc">
                    <i class="ao-anc-hint">Comma separate emails</i>
                </span>
            </label>

            <label class="ao-anc-row">
                <span>Subject</span>
                <input type="text" wire:model="subject" required>
            </label>

            <label class="ao-anc-row">
                <span>Message</span>
                <textarea class="ao-sm-body" wire:model="body" rows="16" required></textarea>
            </label>

            <label class="ao-anc-row">
                <span>Attachments</span>
                <span class="ao-anc-field">
                    <input type="file" wire:model="attachments" multiple>
                    <i class="ao-anc-hint">Up to 100 MB each.</i>
                    <span wire:loading wire:target="attachments" class="ao-anc-hint">Uploading…</span>
                </span>
            </label>
        </div>

        @if ($preview)
            {{-- The reference's Message Preview: what the client will actually receive. --}}
            <div class="ao-anc-card ao-sm-preview">
                <b>{{ $subject }}</b>
                <div class="ao-sm-preview-body">{!! nl2br(e($body)) !!}</div>
            </div>
        @endif

        <div class="ao-bt-save">
            <button type="button" class="ao-pg-btn" wire:click="togglePreview">
                {{ $preview ? 'Hide Preview' : 'Message Preview' }}
            </button>
            <button type="submit" class="ao-pg-btn ao-bt-primary"
                wire:loading.attr="disabled" wire:target="send">Send Message</button>
        </div>
    </form>
</x-filament-panels::page>
