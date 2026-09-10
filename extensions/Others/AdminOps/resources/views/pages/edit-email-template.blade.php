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

        {{-- rich starts on: the reference opens with its toolbar showing. --}}
        <form wire:submit.prevent="save" x-data="{ rich: true }">
            <div class="ao-find ao-of ao-ete-band">
                {{-- The reference's band, row for row: From, Copy To, Blind Copy To,
                     Attachments, Plain-Text, Disable. The template's own name is the
                     page's subheading here, as it is on the reference. --}}
                <div class="ao-of-rows">
                    {{-- The reference sets From per template. Every system email here leaves
                         under one name and address, set once on General Settings, so these
                         show what will be used rather than offering an override that the
                         mailer would ignore. --}}
                    <div class="ao-of-row ao-of-row-single">
                        <span class="ao-of-label">From</span>
                        <span class="ao-of-inline ao-ete-from">
                            <input type="text" class="ao-of-lg" readonly
                                value="{{ config('settings.mail_from_name') ?: config('app.name') }}"
                                title="Every email leaves under one name and address, set on General Settings → Mail — not per template">
                            <input type="text" class="ao-of-lg" readonly
                                value="{{ config('settings.mail_from_address') }}"
                                title="Every email leaves under one name and address, set on General Settings → Mail — not per template">
                        </span>
                    </div>

                    <div class="ao-of-row ao-of-row-single">
                        <label class="ao-of-label" for="ao-ete-cc">Copy To</label>
                        <span class="ao-of-inline">
                            <input id="ao-ete-cc" class="ao-of-lg" type="text" wire:model="cc">
                            <i class="ao-ete-aside">Enter email addresses separated by a comma</i>
                        </span>
                    </div>

                    <div class="ao-of-row ao-of-row-single">
                        <label class="ao-of-label" for="ao-ete-bcc">Blind Copy To</label>
                        <span class="ao-of-inline">
                            <input id="ao-ete-bcc" class="ao-of-lg" type="text" wire:model="bcc">
                            <i class="ao-ete-aside">Enter email addresses separated by a comma</i>
                        </span>
                    </div>

                    {{-- The reference's next two rows. Neither has anything behind it here,
                         and each says why rather than looking live. --}}
                    <div class="ao-of-row ao-of-row-single">
                        <span class="ao-of-label">Attachments</span>
                        {{-- The reasons these two are inert live on their titles rather than
                             under them: the reference's rows are one line each, and a
                             paragraph of explanation per row was what made this band twice
                             the height of the one in the screenshots. --}}
                        <span class="ao-of-stack ao-ete-attach"
                            title="Not available: nothing is stored against a template to attach. An invoice reaches the client as a link to its own page, where the PDF is downloaded.">
                            <input type="file" disabled>
                            <button type="button" class="ao-of-go" disabled>&plus; Add More</button>
                        </span>
                    </div>

                    <div class="ao-of-row ao-of-row-single">
                        <span class="ao-of-label">Plain-Text</span>
                        <span class="ao-of-check"
                            title="Not available: each email is sent as HTML with a plain-text part alongside, so a client whose reader refuses HTML already gets the text.">
                            <input type="checkbox" disabled>
                            Check to send this email in Plain-Text format only
                        </span>
                    </div>

                    <div class="ao-of-row ao-of-row-single">
                        <span class="ao-of-label">Disable</span>
                        <span class="ao-of-check">
                            <input type="checkbox" wire:model="disabled">
                            Check to disable this email from being sent
                        </span>
                    </div>
                </div>
            </div>

            {{-- The reference heads the subject and body with which language version is
                 being edited, and hangs the editor toggle off the same line. --}}
            <div class="ao-ete-versionbar">
                {{-- One line, as the reference writes it: the name in bold, then the rule
                     after a dash. Languages are activated from the list page, which the
                     title says rather than a second sentence on the page. --}}
                <h3 class="ao-sub ao-ete-version"
                    title="Activate languages under Manage Languages on the Email Templates list">
                    <b>Default Version</b>
                    <span>- Used for the {{ $localeNames[config('app.locale', 'en')] ?? 'English' }} language
                        and any languages where email template translations are not defined</span>
                </h3>

                {{-- The reference's Enable/Disable Rich-Text Editor, in its place on this
                     line. It turns on a formatting toolbar that writes Markdown into the
                     same box rather than a WYSIWYG surface: these bodies carry live Blade
                     placeholders, and an editor that owns the HTML rewrites them as plain
                     text, which breaks every email that uses one. --}}
                <button type="button" class="ao-of-go ao-ete-rich" @click="rich = !rich"
                    :title="rich
                        ? 'Hide the formatting buttons'
                        : 'Show formatting buttons. They write Markdown into the box, so placeholders survive.'">
                    Enable/Disable Rich-Text Editor
                </button>
            </div>

            <div class="ao-ete-subject">
                <label for="ao-ete-subject">Subject:</label>
                <input id="ao-ete-subject" type="text" wire:model="subject">
            </div>

            <div class="ao-tx-tabs ao-ete-modes">
                <button type="button" class="ao-mu-tab {{ $mode === 'source' ? 'ao-on' : '' }}"
                    wire:click="$set('mode', 'source')">Source code</button>
                <button type="button" class="ao-mu-tab {{ $mode === 'preview' ? 'ao-on' : '' }}"
                    wire:click="$set('mode', 'preview')"
                    title="The rendered Markdown; placeholders show as tokens and are filled with the client's real values when the email sends">Preview</button>
            </div>

            @if ($mode === 'source')
                {{-- The same Markdown toolbar the ticket editors use, so a formatting button
                     writes into the box instead of taking it over. --}}
                <div class="ao-ont-toolbar ao-ete-toolbar" x-show="rich" x-cloak>
                    <button type="button" data-md="**" title="Bold"><b>B</b></button>
                    <button type="button" data-md="*" title="Italic"><i>I</i></button>
                    <button type="button" data-md-line="# " title="Heading"><b>H</b></button>
                    <button type="button" data-md-line="[Link](https://)" title="Link">&#128279;</button>
                    <button type="button" data-md-line="- " title="Bullet list">&#8226;&#8226;</button>
                    <button type="button" data-md-line="1. " title="Numbered list">1.</button>
                    <button type="button" data-md-line="> " title="Quote">&#10078;</button>
                </div>

                <textarea class="ao-ete-source" rows="18" wire:model="body" spellcheck="false"
                    data-ao-message
                    title="Markdown with Blade placeholders — @{{ $ip }} and friends are filled in when the email sends. The toolbar writes Markdown into this box; a WYSIWYG surface is not offered because owning the HTML means rewriting those placeholders as plain text and breaking them."></textarea>

                {{-- The reference closes the editor with a word count along its bottom edge.
                     Counted from the stored body, so it is the real length rather than a
                     number that only updates when the box is retyped. --}}
                <p class="ao-ete-count">{{ str_word_count(strip_tags($body)) }} words</p>
            @else
                <div class="ao-ete-preview">{!! $this->previewHtml() !!}</div>
            @endif

            {{-- One version per language Manage Languages has activated. Leaving a version
                 blank is how you say "no translation" — the default sends instead, which is
                 what the heading above promises. --}}
            @foreach ($locales as $code => $version)
                <h3 class="ao-sub ao-ete-version"
                    title="Leave both boxes empty and these clients are sent the default version">
                    <b>{{ $localeNames[$code] ?? \Illuminate\Support\Str::upper($code) }} Version</b>
                    <span>- Sent to clients whose profile language is
                        {{ $localeNames[$code] ?? \Illuminate\Support\Str::upper($code) }}</span>
                </h3>

                <div class="ao-of-row ao-of-row-single">
                    <label class="ao-of-label" for="ao-ete-subject-{{ $code }}">Subject</label>
                    <span><input id="ao-ete-subject-{{ $code }}" class="ao-of-xl" type="text"
                        wire:model="locales.{{ $code }}.subject" placeholder="Untranslated — the default subject sends"></span>
                </div>

                <textarea class="ao-ete-source" rows="12" spellcheck="false"
                    wire:model="locales.{{ $code }}.body"
                    placeholder="Untranslated — the default body sends"></textarea>
            @endforeach

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

    {{-- Same handler as the ticket editors: wrap or prefix the selection with Markdown and
         tell Livewire the box changed. --}}
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
