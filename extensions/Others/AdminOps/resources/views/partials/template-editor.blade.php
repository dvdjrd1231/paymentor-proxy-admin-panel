{{--
    One template body with the reference's editor around it: menu bar, both toolbar rows,
    the Preview strip and the word count. Included once for the Default Version and once
    per activated language, because the reference gives every language version the same
    editor — ours left the extra languages as a bare textarea with no tools at all
    (Leandro, 2026-09-30).

    $model  — the Livewire property this box writes to
    $target — which body Source code and Preview act on
    $text   — that body's current text, for the word count
--}}
            <div class="ao-ete-box" data-ao-target="{{ $target }}" x-data="{ menu: null }" @click.outside="menu = null">
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
                    {{-- The reference's `<>` is Source code: the HTML the email is built
                         from. Preview, beneath, is what the reader sees — two different
                         views of the same body, as the reference has them. --}}
                    <button type="button" class="ao-ete-mode" wire:click="openSource('{{ $target }}')"
                        title="The HTML this email is built from">&lt;&gt;</button>
                    <button type="button" data-ao-act="clearfmt" title="Clear formatting">&#8455;x</button>
                </div>

                {{-- Its own strip under the toolbar, padded like the rows above it. --}}
                <div class="ao-ete-modebar">
                    <button type="button" class="ao-ete-mode" wire:click="openPreview('{{ $target }}')"
                        title="See the email as the reader will — a dialog over the editor, as the reference's File ▸ Preview opens">&#128065; Preview</button>
                </div>

                {{-- The editor is always the source. Preview used to replace it, so the two
                     were one surface and switching between them read as the same screen
                     twice (Leandro, 2026-09-13); the reference keeps its editor in place
                     and opens the rendered email in a dialog on top. --}}
                <textarea class="ao-ete-source" rows="18" wire:model="{{ $model }}" spellcheck="false"
                    data-ao-message
                    title="Markdown with Blade placeholders — @{{ $ip }} and friends are filled in when the email sends. The toolbar writes Markdown into this box; a WYSIWYG surface is not offered because owning the HTML means rewriting those placeholders as plain text and breaking them."></textarea>

                {{-- The reference closes the editor with a word count along its bottom edge.
                     Counted from the stored body, so it is the real length rather than a
                     number that only updates when the box is retyped. --}}
                <p class="ao-ete-count">{{ str_word_count(strip_tags($text)) }} words</p>
            </div>
