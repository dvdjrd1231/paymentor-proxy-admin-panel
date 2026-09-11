{{--
    The reference's template editor (issue #48): the settings band — Template Name,
    Subject, Copy To, Blind Copy To, Disable — then the body with the reference's own
    Source / Preview toggle. Source is the editor on purpose: these bodies carry live
    Blade placeholders a WYSIWYG would corrupt (see the page class).
--}}
<x-filament-panels::page>
    {{-- Not .ao-eo: that class carries the order screen's own density, which squeezed
         this band's rows well below the reference's. --}}
    <div class="ao-mu ao-ete">
        {{-- No Back-to-List strip: the reference has none, and the rail's Email
             Templates entry is the way back it uses. --}}

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
                        {{-- One Choose File per row and Add More for the next, as the
                             reference does it. Every email built from this template carries
                             these; AdminOps::attachTemplateFiles() puts them on the message
                             as it goes out. --}}
                        <span class="ao-of-stack ao-ete-attach">
                            @foreach ($attachments as $index => $pending)
                                <input type="file" wire:key="ete-file-{{ $index }}"
                                    wire:model="attachments.{{ $index }}">
                            @endforeach
                            @error('attachments.*') <i class="ao-anc-errors">{{ $message }}</i> @enderror

                            {{-- The reference has one button here and takes the files with
                                 Save Changes; a second, primary-coloured Upload beside it was
                                 ours, and it is what threw the row out. --}}
                            <span class="ao-ete-attach-acts">
                                <button type="button" class="ao-of-go" wire:click="addAttachmentRow">&plus; Add More</button>
                            </span>

                            @if ($storedAttachments->isNotEmpty())
                                <span class="ao-ete-attach-list">
                                    @foreach ($storedAttachments as $file)
                                        <span class="ao-stf-chip">
                                            <span>{{ $file->filename }}
                                                ({{ $file->filesize > 1048576
                                                    ? number_format($file->filesize / 1048576, 1) . ' MB'
                                                    : max(1, (int) round($file->filesize / 1024)) . ' KB' }})</span>
                                            <button type="button" wire:click="removeAttachment({{ $file->id }})"
                                                wire:confirm="Remove {{ $file->filename }} from this template?"
                                                title="Remove">&times;</button>
                                        </span>
                                    @endforeach
                                </span>
                            @endif
                        </span>
                    </div>

                    <div class="ao-of-row ao-of-row-single">
                        <span class="ao-of-label">Plain-Text</span>
                        <span class="ao-of-check"
                            title="The HTML part is dropped at send time and the text taken from it goes out on its own">
                            <input type="checkbox" wire:model="plainText">
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

            {{-- One bordered box, as the reference frames its editor: toolbar, writing
                 surface and the status strip inside a single frame. The reference has no
                 tab strip above it — its source and preview are toolbar buttons, so ours
                 are too. --}}
            <div class="ao-ete-box" x-data="{ menu: null }" @click.outside="menu = null">
                {{-- The reference's menu bar and its two toolbar rows. Every control writes
                     Markdown into the box below rather than taking the surface over: these
                     bodies carry live Blade (@verbatim{{ $invoice->number }}@endverbatim,
                     @verbatim@foreach@endverbatim) and an editor that owns the HTML rewrites
                     those as plain text, which silently breaks the email. --}}
                <div class="ao-ete-menubar" x-show="rich" x-cloak>
                    @foreach ([
                        'File' => [['Save', 'save', null], ['Print…', 'print', null]],
                        'Edit' => [['Undo', 'undo', null], ['Redo', 'redo', null], ['Select all', 'selectall', null]],
                        'View' => [['Source code', 'source', null], ['Preview', 'preview', null], ['Fullscreen', 'fullscreen', null]],
                        'Insert' => [['Link', null, '[Link](https://)'], ['Image', null, '![alt](https://)'], ['Horizontal rule', null, '---'], ['Special character…', 'omega', null]],
                        'Format' => [['Bold', null, '**'], ['Italic', null, '*'], ['Strikethrough', null, '~~'], ['Code', null, '`'], ['Clear formatting', 'clearfmt', null]],
                        'Table' => [['Insert table', null, "| Column | Column |\n| --- | --- |\n| Cell | Cell |"]],
                        'Help' => [['Markdown guide', 'help', null]],
                    ] as $label => $entries)
                        <div class="ao-ete-menu">
                            <button type="button" @click.stop="menu = (menu === @js($label) ? null : @js($label))"
                                :class="{ 'ao-on': menu === @js($label) }">{{ $label }}</button>
                            <ul x-show="menu === @js($label)" x-cloak>
                                @foreach ($entries as [$item, $act, $md])
                                    <li><button type="button" @click="menu = null"
                                        @if ($act) data-ao-act="{{ $act }}" @endif
                                        @if ($md) data-md-line="{{ $md }}" @endif>{{ $item }}</button></li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>

                {{-- Row for row, in the reference's order (configemailtemplates.php):
                     block, font, size | B I S U | colour, highlight | link, unlink |
                     bullets, numbers. Markdown carries no colour, so those two write the
                     HTML span Markdown passes through untouched. --}}
                <div class="ao-ont-toolbar ao-ete-toolbar" x-show="rich" x-cloak>
                    <select class="ao-ete-sel" data-ao-block title="Paragraph format">
                        <option value="">Paragraph</option>
                        <option value="# ">Heading 1</option>
                        <option value="## ">Heading 2</option>
                        <option value="### ">Heading 3</option>
                    </select>
                    {{-- Markdown carries no typeface, and the sent email takes its face
                         from the mail template — so these show the face that will be used
                         rather than offering a choice the message cannot keep. --}}
                    <select class="ao-ete-sel" disabled title="The email's typeface comes from the mail template, not from this box">
                        <option>Helvetica</option>
                    </select>
                    <select class="ao-ete-sel ao-ete-sel-sm" disabled title="The email's size comes from the mail template, not from this box">
                        <option>11pt</option>
                    </select>
                    <span class="ao-rte-sep"></span>
                    <button type="button" data-md="**" title="Bold"><b>B</b></button>
                    <button type="button" data-md="*" title="Italic"><i>I</i></button>
                    <button type="button" data-md="~~" title="Strikethrough"><s>S</s></button>
                    {{-- Markdown has no underline, so this writes the HTML tag around the
                         selection — Markdown passes raw HTML through untouched. --}}
                    <button type="button" data-md-pair="&lt;u&gt;|&lt;/u&gt;" title="Underline"><u>U</u></button>
                    <span class="ao-rte-sep"></span>
                    <button type="button" data-ao-act="forecolor" title="Text colour"><span class="ao-ete-fore">A</span></button>
                    <button type="button" data-ao-act="backcolor" title="Background colour"><span class="ao-ete-back">A</span></button>
                    <span class="ao-rte-sep"></span>
                    <button type="button" data-md-line="[Link](https://)" title="Insert link">&#128279;</button>
                    <button type="button" data-ao-act="unlink" title="Remove the link around the selection">&#9986;&#65038;</button>
                    <span class="ao-rte-sep"></span>
                    <button type="button" data-md-line="- " title="Bullet list">&#8226;&#8226;</button>
                    <button type="button" data-md-line="1. " title="Numbered list">1.</button>
                </div>

                {{-- The reference's second row, same order: outdent, indent | quote |
                     undo, redo | cut, copy, paste, paste as text | table | rule |
                     character | image | media | print | ltr, rtl | fullscreen | help |
                     source | clear formatting. --}}
                <div class="ao-ont-toolbar ao-ete-toolbar ao-ete-toolbar2" x-show="rich" x-cloak>
                    <button type="button" data-ao-act="outdent" title="Outdent">&#8676;</button>
                    <button type="button" data-md-line="    " title="Indent">&#8677;</button>
                    <span class="ao-rte-sep"></span>
                    <button type="button" data-md-line="> " title="Quote">&#10078;</button>
                    <span class="ao-rte-sep"></span>
                    <button type="button" data-ao-act="undo" title="Undo">&#8630;</button>
                    <button type="button" data-ao-act="redo" title="Redo">&#8631;</button>
                    <span class="ao-rte-sep"></span>
                    <button type="button" data-ao-act="cut" title="Cut">&#9986;</button>
                    <button type="button" data-ao-act="copy" title="Copy">&#10697;</button>
                    <button type="button" data-ao-act="paste" title="Paste">&#128203;</button>
                    <button type="button" data-ao-act="pastetext" title="Paste as plain text">&#128462;</button>
                    <span class="ao-rte-sep"></span>
                    <button type="button" data-md-line="| Column | Column |&#10;| --- | --- |&#10;| Cell | Cell |" title="Insert table">&#9638;</button>
                    <span class="ao-rte-sep"></span>
                    <button type="button" data-md-line="---" title="Horizontal rule">&#8213;</button>
                    <button type="button" data-ao-act="omega" title="Special character">&Omega;</button>
                    <button type="button" data-md-line="![alt](https://)" title="Insert image">&#128444;</button>
                    <button type="button" data-ao-act="media" title="Insert media">&#9654;</button>
                    <button type="button" data-ao-act="print" title="Print">&#128424;</button>
                    <span class="ao-rte-sep"></span>
                    <button type="button" data-ao-act="ltr" title="Left to right">&#182;</button>
                    <button type="button" data-ao-act="rtl" title="Right to left">&#8267;</button>
                    <span class="ao-rte-sep"></span>
                    <button type="button" data-ao-act="fullscreen" title="Fullscreen">&#9974;</button>
                    <button type="button" data-ao-act="help" title="Markdown guide">?</button>
                    {{-- The reference's `<>` - source against rendered. --}}
                    @if ($mode === 'source')
                        <button type="button" class="ao-ete-mode" wire:click="$set('mode', 'preview')"
                            title="The rendered Markdown; placeholders show as tokens and are filled with the client's real values when the email sends">&lt;&gt;</button>
                    @else
                        <button type="button" class="ao-ete-mode ao-on" wire:click="$set('mode', 'source')"
                            title="Back to the Markdown source">&lt;&gt;</button>
                    @endif
                    <button type="button" data-ao-act="clearfmt" title="Clear formatting">&#8455;x</button>
                </div>

                {{-- Its own strip under the toolbar, padded like the rows above it: bare
                     on the box it sat 12px left of every control and read as detached. --}}
                <div class="ao-ete-modebar">
                    @if ($mode === 'source')
                        <button type="button" class="ao-ete-mode" wire:click="$set('mode', 'preview')"
                            title="The rendered Markdown; placeholders show as tokens and are filled with the client's real values when the email sends">&#128065; Preview</button>
                    @else
                        <button type="button" class="ao-ete-mode ao-on" wire:click="$set('mode', 'source')"
                            title="Back to the Markdown source">&lt;&gt; Source code</button>
                    @endif
                </div>

                @if ($mode === 'source')

                    <textarea class="ao-ete-source" rows="18" wire:model="body" spellcheck="false"
                        data-ao-message
                        title="Markdown with Blade placeholders — @{{ $ip }} and friends are filled in when the email sends. The toolbar writes Markdown into this box; a WYSIWYG surface is not offered because owning the HTML means rewriting those placeholders as plain text and breaking them."></textarea>
                @else
                    <div class="ao-ete-preview">{!! $this->previewHtml() !!}</div>
                @endif

                {{-- The reference closes the editor with a word count along its bottom edge.
                     Counted from the stored body, so it is the real length rather than a
                     number that only updates when the box is retyped. --}}
                <p class="ao-ete-count">{{ str_word_count(strip_tags($body)) }} words</p>
            </div>

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

            <div class="ao-of-buttons ao-ete-save">
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
                {{-- The reference groups its tags by what they hang off - Client Related,
                     then the record the email is about - and lists the whole vocabulary,
                     not only the tags the current body happens to use. --}}
                @forelse ($merge['groups'] as $group)
                    <h4 class="ao-ano-heading">{{ $group['heading'] }}</h4>
                    @foreach ($group['rows'] as $row)
                        <p class="ao-ete-mrow"><span>{{ $row['label'] }}</span><code class="ao-ete-token">{{ $row['token'] }}</code></p>
                    @endforeach
                @empty
                    <p class="ao-cpg-muted">This template takes no fields.</p>
                @endforelse

                @if ($merge['links'])
                    <h4 class="ao-ano-heading">Links</h4>
                    @foreach ($merge['links'] as $link)
                        <p class="ao-ete-mrow"><span>{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\EditEmailTemplate::labelFor($link) }}</span><code class="ao-ete-token">{{ $link }}</code></p>
                    @endforeach
                @endif
            </div>

            <div class="ao-ete-merge-col">
                <h4 class="ao-ano-heading">Conditional Display</h4>
                <p>Show text only when something is true:</p>
                <p class="ao-ete-example">&#64;if ($invoice-&gt;status === 'paid')<br>
                    Thank you — nothing further is owed.<br>
                    &#64;else<br>
                    This invoice is still open.<br>
                    &#64;endif</p>

                <h4 class="ao-ano-heading">Looping through data</h4>
                <p>Repeat a block for each item:</p>
                <p class="ao-ete-example">&#64;foreach ($invoice-&gt;items as $item)<br>
                    &#123;&#123; $item-&gt;description &#125;&#125;: &#123;&#123; $item-&gt;formattedPrice &#125;&#125;<br>
                    &#64;endforeach</p>

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
                const button = event.target.closest('[data-md], [data-md-line], [data-md-pair]');
                if (!button) return;
                const box = root.querySelector('[data-ao-message]');
                if (!box) return;
                const [start, end] = [box.selectionStart, box.selectionEnd];
                const picked = box.value.slice(start, end);
                let text;
                if (button.dataset.mdPair !== undefined) {
                    {{-- An opener and a closer that differ, split on the pipe: <u>|</u>. --}}
                    const [open, close] = button.dataset.mdPair.split('|');
                    text = box.value.slice(0, start) + open + (picked || 'text') + close + box.value.slice(end);
                } else if (button.dataset.md !== undefined) {
                    const wrap = button.dataset.md;
                    text = box.value.slice(0, start) + wrap + (picked || 'text') + wrap + box.value.slice(end);
                } else {
                    text = box.value.slice(0, start) + '\n' + button.dataset.mdLine + picked + box.value.slice(end);
                }
                box.value = text;
                box.dispatchEvent(new Event('input', { bubbles: true }));
                box.focus();
            });

            {{-- The toolbar's non-inserting controls. undo/redo go through execCommand so
                 the textarea's own history is used rather than a second one of ours. --}}
            root.addEventListener('click', (event) => {
                const button = event.target.closest('[data-ao-act]');
                if (!button) return;
                const box = root.querySelector('[data-ao-message]');
                const act = button.dataset.aoAct;

                if (act === 'print') return window.print();
                if (act === 'help') return window.open('https://www.markdownguide.org/basic-syntax/', '_blank', 'noopener');
                if (act === 'fullscreen') {
                    const frame = button.closest('.ao-ete-box');
                    return frame.classList.toggle('ao-ete-full');
                }
                if (!box) return;
                box.focus();

                if (act === 'undo' || act === 'redo' || act === 'selectall') {
                    if (act === 'selectall') return box.select();
                    return document.execCommand(act);
                }

                if (act === 'cut' || act === 'copy') {
                    const picked = box.value.slice(box.selectionStart, box.selectionEnd);
                    if (!picked) return;
                    navigator.clipboard?.writeText(picked);
                    if (act === 'cut') {
                        document.execCommand('insertText', false, '');
                        box.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                    return;
                }

                if (act === 'unlink') {
                    const [start, end] = [box.selectionStart, box.selectionEnd];
                    if (start === end) return;
                    // [label](url) -> label, and <u>x</u> -> x. The label is kept.
                    const plain = box.value.slice(start, end)
                        .replace(/\[([^\]]*)\]\([^)]*\)/g, '$1')
                        .replace(/<\/?u>/g, '');
                    document.execCommand('insertText', false, plain);
                    return box.dispatchEvent(new Event('input', { bubbles: true }));
                }

                if (act === 'outdent') {
                    const at = box.selectionStart;
                    const from = box.value.lastIndexOf('\n', at - 1) + 1;
                    let to = box.value.indexOf('\n', at);
                    if (to === -1) to = box.value.length;
                    const line = box.value.slice(from, to).replace(/^ {1,4}/, '');
                    box.setSelectionRange(from, to);
                    document.execCommand('insertText', false, line);
                    return box.dispatchEvent(new Event('input', { bubbles: true }));
                }

                if (act === 'forecolor' || act === 'backcolor') {
                    const [from, to] = [box.selectionStart, box.selectionEnd];
                    if (from === to) return window.alert('Select the text to colour first.');
                    const colour = window.prompt(act === 'forecolor' ? 'Text colour' : 'Background colour', '#337ab7');
                    if (!colour) return;
                    const prop = act === 'forecolor' ? 'color' : 'background-color';
                    const picked = box.value.slice(from, to);
                    document.execCommand('insertText', false,
                        '<span style="' + prop + ': ' + colour + '">' + picked + '</span>');
                    return box.dispatchEvent(new Event('input', { bubbles: true }));
                }

                if (act === 'paste' || act === 'pastetext') {
                    {{-- A button may only read the clipboard with the user's permission, and
                         a refusal is silent — so say what happened rather than doing nothing.
                         Ctrl+V always works; this button is here because the reference has it. --}}
                    if (!navigator.clipboard || !navigator.clipboard.readText) {
                        return window.alert('This browser will not let a button read the clipboard - use Ctrl+V.');
                    }
                    return navigator.clipboard.readText().then((text) => {
                        if (!text) return;
                        box.focus();
                        document.execCommand('insertText', false,
                            act === 'pastetext' ? text.replace(/[*_`~#>[\]()]/g, '') : text);
                        box.dispatchEvent(new Event('input', { bubbles: true }));
                    }).catch(() => window.alert('Clipboard access was refused - use Ctrl+V.'));
                }

                if (act === 'media') {
                    const src = window.prompt('Media URL (video or embed)', 'https://');
                    if (!src || src === 'https://') return;
                    document.execCommand('insertText', false,
                        '<video src="' + src + '" controls width="560"></video>');
                    return box.dispatchEvent(new Event('input', { bubbles: true }));
                }

                if (act === 'ltr' || act === 'rtl') {
                    const [from, to] = [box.selectionStart, box.selectionEnd];
                    const picked = from === to ? '' : box.value.slice(from, to);
                    document.execCommand('insertText', false,
                        '<div dir="' + (act === 'ltr' ? 'ltr' : 'rtl') + '">' + picked + '</div>');
                    return box.dispatchEvent(new Event('input', { bubbles: true }));
                }

                if (act === 'omega') {
                    const ch = window.prompt('Character to insert', '€');
                    if (!ch) return;
                    document.execCommand('insertText', false, ch);
                    return box.dispatchEvent(new Event('input', { bubbles: true }));
                }

                if (act === 'clearfmt') {
                    const [start, end] = [box.selectionStart, box.selectionEnd];
                    if (start === end) return;
                    // Strip the Markdown that wraps or opens the selection, nothing else —
                    // a placeholder inside it must come through untouched.
                    const plain = box.value.slice(start, end)
                        .replace(/(\*\*|__|~~|`|\*|_)/g, '')
                        .replace(/^\s*(#{1,6}\s+|>\s+|[-*+]\s+|\d+\.\s+)/gm, '');
                    document.execCommand('insertText', false, plain);
                    return box.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });

            {{-- The Paragraph/Heading select: sets the heading level of the caret's line. --}}
            root.addEventListener('change', (event) => {
                const select = event.target.closest('[data-ao-block]');
                if (!select) return;
                const box = root.querySelector('[data-ao-message]');
                if (!box) return;

                const at = box.selectionStart;
                const from = box.value.lastIndexOf('\n', at - 1) + 1;
                let to = box.value.indexOf('\n', at);
                if (to === -1) to = box.value.length;

                const line = box.value.slice(from, to).replace(/^#{1,6}\s+/, '');
                box.setSelectionRange(from, to);
                document.execCommand('insertText', false, select.value + line);
                box.dispatchEvent(new Event('input', { bubbles: true }));
                select.value = '';
                box.focus();
            });
        })();
    </script>
</x-filament-panels::page>
