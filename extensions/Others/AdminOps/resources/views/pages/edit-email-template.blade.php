{{--
    The reference's template editor (issue #48): the settings band — Template Name,
    Subject, Copy To, Blind Copy To, Disable — then the body with the reference's own
    Source / Preview toggle. Source is the editor on purpose: these bodies carry live
    Blade placeholders a WYSIWYG would corrupt (see the page class).
--}}
<x-filament-panels::page>
    <div class="ao-mu ao-eo">
        <div class="ao-tx-tabs">
            <a class="ao-mu-tab" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\EmailTemplates::getUrl() }}">&laquo; Back to List</a>
        </div>

        <form wire:submit.prevent="save">
            <div class="ao-find ao-of">
                <div class="ao-of-rows">
                    <div class="ao-of-row">
                        <span class="ao-of-label">Template Name</span>
                        <span class="ao-eo-fact"
                            title="Derived from the template's key — the key is what the system sends by, so the name follows it">
                            {{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\EmailTemplates::label($template) }}
                        </span>
                        <span class="ao-of-label">Disable</span>
                        <span class="ao-of-check">
                            <input type="checkbox" wire:model="disabled">
                            Tick to prevent this email being sent
                        </span>
                    </div>
                    <div class="ao-of-row ao-of-row-single">
                        <label class="ao-of-label" for="ao-ete-subject">Subject</label>
                        <span><input id="ao-ete-subject" class="ao-of-xl" type="text" wire:model="subject"></span>
                    </div>
                    <div class="ao-of-row ao-of-row-single">
                        <label class="ao-of-label" for="ao-ete-cc">Copy To</label>
                        <span class="ao-of-inline">
                            <input id="ao-ete-cc" class="ao-of-xl" type="text" wire:model="cc"
                                placeholder="Enter email addresses separated by a comma">
                        </span>
                    </div>
                    <div class="ao-of-row ao-of-row-single">
                        <label class="ao-of-label" for="ao-ete-bcc">Blind Copy To</label>
                        <span><input id="ao-ete-bcc" class="ao-of-xl" type="text" wire:model="bcc"
                            placeholder="Enter email addresses separated by a comma"></span>
                    </div>
                </div>
            </div>

            <div class="ao-tx-tabs ao-ete-modes">
                <button type="button" class="ao-mu-tab {{ $mode === 'source' ? 'ao-on' : '' }}"
                    wire:click="$set('mode', 'source')">Source code</button>
                <button type="button" class="ao-mu-tab {{ $mode === 'preview' ? 'ao-on' : '' }}"
                    wire:click="$set('mode', 'preview')"
                    title="The rendered Markdown; placeholders show as tokens and are filled with the client's real values when the email sends">Preview</button>
            </div>

            @if ($mode === 'source')
                <textarea class="ao-ete-source" rows="18" wire:model="body" spellcheck="false"></textarea>
                <p class="ao-ete-hint">
                    Markdown with Blade placeholders — <code>&#123;&#123; $ip &#125;&#125;</code> and friends are filled
                    in when the email sends. A rich-text editor is deliberately not offered: it would
                    rewrite the placeholders as ordinary text and break them.
                </p>
            @else
                <div class="ao-ete-preview">{!! $this->previewHtml() !!}</div>
            @endif

            @if ($errors->any())
                <ul class="ao-anc-errors">
                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            @endif

            <div class="ao-of-buttons">
                <button type="submit" class="ao-find-go">Save Changes</button>
                <a class="ao-of-go" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\EmailTemplates::getUrl() }}">Cancel Changes</a>
            </div>
        </form>
    </div>
</x-filament-panels::page>
