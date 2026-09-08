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
                    {{-- The reference sets From per template. Here every system email
                         leaves under one name and address, set once on General Settings,
                         so this shows what will be used rather than offering an override
                         that would be ignored. --}}
                    <div class="ao-of-row ao-of-row-single">
                        <span class="ao-of-label">From</span>
                        <span class="ao-eo-fact">
                            {{ config('settings.mail_from_name') ?: config('app.name') }}
                            &lt;{{ config('settings.mail_from_address') ?: '—' }}&gt;
                            <i>Set for the whole store on
                                <a class="ao-link" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\GeneralSettings::getUrl() }}">General Settings → Mail</a>.</i>
                        </span>
                    </div>

                    {{-- The reference heads the subject and body with which language version
                         is being edited. There is one here — Manage Languages says the same —
                         so this names it rather than implying a picker that is not there. --}}
                    <h3 class="ao-sub ao-ete-version">
                        Default Version
                        <i>The only version: this template is sent to every customer, whatever
                            language they read. See Manage Languages on the
                            <a class="ao-link" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\EmailTemplates::getUrl() }}">templates list</a>.</i>
                    </h3>

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

                    {{-- The reference's last two rows on this band. Neither has a column
                         behind it, and each says why rather than looking live. --}}
                    <div class="ao-of-row ao-of-row-single">
                        <span class="ao-of-label">Attachments</span>
                        <span class="ao-of-inline ao-gs-off">
                            <input type="file" disabled title="Notification emails carry no attachments on this platform">
                            <i>Not available: nothing is stored against a template to attach. An invoice
                                reaches the client as a link to its own page, where the PDF is downloaded.</i>
                        </span>
                    </div>

                    <div class="ao-of-row ao-of-row-single">
                        <span class="ao-of-label">Plain-Text</span>
                        <span class="ao-of-check ao-gs-off">
                            <input type="checkbox" disabled title="Every email already goes out with a plain-text part beside the HTML">
                            Check to send this email in Plain-Text format only
                            <i>Not available: each email is sent as HTML with a plain-text part alongside, so a
                                client whose reader refuses HTML already gets the text.</i>
                        </span>
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

        {{-- The reference's Available Merge Fields panel. These are read from what this
             template can actually resolve, so a field listed here is one the event carries
             — offering the whole platform's vocabulary would list fields that render empty. --}}
        @php $merge = $this->mergeFields(); @endphp

        <h3 class="ao-sub">Available Merge Fields</h3>

        <div class="ao-anc-card ao-ete-merge">
            <div class="ao-ete-merge-col">
                <h4 class="ao-ano-heading">Fields</h4>
                @forelse ($merge['fields'] as $field)
                    <p><code class="ao-ete-token">{{ $field }}</code></p>
                @empty
                    <p class="ao-cpg-muted">This template takes no fields.</p>
                @endforelse

                @if ($merge['links'])
                    <h4 class="ao-ano-heading">Links</h4>
                    @foreach ($merge['links'] as $link)
                        <p><code class="ao-ete-token">{{ $link }}</code></p>
                    @endforeach
                @endif
            </div>

            <div class="ao-ete-merge-col">
                <h4 class="ao-ano-heading">Conditional Display</h4>
                <p>Show text only when something is true:</p>
                <pre class="ao-ete-snippet">&#64;if ($invoice-&gt;status === 'paid')
    Thank you — nothing further is owed.
&#64;else
    This invoice is still open.
&#64;endif</pre>

                <h4 class="ao-ano-heading">Looping through data</h4>
                <p>Repeat a block for each item:</p>
                <pre class="ao-ete-snippet">&#64;foreach ($invoice-&gt;items as $item)
    &#123;&#123; $item-&gt;description &#125;&#125;: &#123;&#123; $item-&gt;formattedPrice &#125;&#125;
&#64;endforeach</pre>

                <p class="ao-cp-note">
                    Bodies are Markdown with Blade, so the directives are Blade's own rather than
                    the reference's Smarty tags. Anything a field does not carry renders as nothing
                    rather than as an error.
                </p>
            </div>
        </div>
    </div>
</x-filament-panels::page>
