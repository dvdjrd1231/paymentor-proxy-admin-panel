{{--
    A genuine WYSIWYG, not a read-only preview of the raw HTML — the gap Leandro's
    Announcements screenshot showed precisely: the reference's toolbar (Bold, Italic,
    lists, links, undo/redo…) actually edits with formatting; toggling "Rich-Text Editor"
    off/on before this showed a live-rendered `<div>` that could not be typed into at all.

    No build step and no third-party library, matching how every other script in this skin
    is written: a plain `[contenteditable]` div, a toolbar of `document.execCommand()`
    buttons — deprecated, but still the one API every evergreen browser implements for
    exactly this, and there is no bundler here to pull TipTap or Quill in through instead.

    Bound to Livewire through a hidden textarea rather than directly: `wire:model` has
    nothing to listen to on a contenteditable div, whose changes fire neither `input` nor
    `change` the way a form control's do. The hidden field is the real source of truth
    Livewire sees; the visible div is kept in sync with it in both directions.

    Usage: @include('adminops::components.rich-editor', ['model' => 'body', 'value' => $body])
    `model` is the Livewire property name; `value` seeds the editor's first paint, since
    Livewire's own hydration re-renders the hidden field but not a plain div's innerHTML.
--}}
@php
    $rteId = 'ao-rte-' . \Illuminate\Support\Str::random(8);
@endphp
<div class="ao-rte" wire:ignore data-model="{{ $model }}" id="{{ $rteId }}">
    <div class="ao-rte-toolbar">
        <button type="button" data-cmd="bold" title="Bold"><b>B</b></button>
        <button type="button" data-cmd="italic" title="Italic"><i>I</i></button>
        <button type="button" data-cmd="underline" title="Underline"><u>U</u></button>
        <button type="button" data-cmd="strikeThrough" title="Strikethrough"><s>S</s></button>
        <span class="ao-rte-sep"></span>
        <button type="button" data-cmd="insertUnorderedList" title="Bullet list">&#8226;&#8226;</button>
        <button type="button" data-cmd="insertOrderedList" title="Numbered list">1.</button>
        <button type="button" data-cmd="formatBlock" data-arg="blockquote" title="Quote">&#10078;</button>
        <span class="ao-rte-sep"></span>
        <button type="button" data-cmd="createLink" data-prompt="Link URL" title="Insert link">&#128279;</button>
        <button type="button" data-cmd="unlink" title="Remove link">&#128279;&#8416;</button>
        <button type="button" data-cmd="insertHorizontalRule" title="Horizontal rule">&#9472;</button>
        <span class="ao-rte-sep"></span>
        <button type="button" data-cmd="undo" title="Undo">&#8630;</button>
        <button type="button" data-cmd="redo" title="Redo">&#8631;</button>
        <span class="ao-rte-sep"></span>
        {{-- The reference's own "Enable/Disable Rich-Text Editor" — kept as a toggle here
             too, but both sides of it are now genuinely editable: this one formats as you
             type, the other edits the raw HTML directly. Whichever was touched last wins
             when the form saves; nothing is lost switching between them. --}}
        <button type="button" data-ao-rte-source title="Edit raw HTML">&lt;/&gt;</button>
    </div>
    <div class="ao-rte-area" contenteditable="true" data-ao-rte-editable></div>
    <textarea class="ao-rte-source" data-ao-rte-raw rows="10" hidden></textarea>
</div>

<script>
    (() => {
        if (window.aoRte) return;
        window.aoRte = true;

        const init = (root) => {
            if (root.dataset.aoRteInit) return;
            root.dataset.aoRteInit = '1';

            const editable = root.querySelector('[data-ao-rte-editable]');
            const raw = root.querySelector('[data-ao-rte-raw]');

            // getAttribute rather than a CSS attribute selector: wire:model's colon and
            // .live/.blur modifiers are not safe to drop unescaped into a selector string,
            // and escaping them is more fragile than just checking each candidate by hand.
            const scope = root.closest('form') ?? document;
            const hidden = [...scope.querySelectorAll('textarea, input')].find((el) =>
                ['wire:model', 'wire:model.live', 'wire:model.blur', 'wire:model.defer']
                    .some((attr) => el.getAttribute(attr) === root.dataset.model));
            if (!hidden) return;

            editable.innerHTML = hidden.value || '';
            raw.value = hidden.value || '';

            const commit = (html) => {
                hidden.value = html;
                hidden.dispatchEvent(new Event('input', { bubbles: true }));
            };

            editable.addEventListener('input', () => commit(editable.innerHTML));

            raw.addEventListener('input', () => commit(raw.value));

            root.querySelector('[data-ao-rte-source]').addEventListener('click', () => {
                const showingSource = !raw.hasAttribute('hidden');
                if (showingSource) {
                    // Back to rich: the source box was the one being typed in.
                    editable.innerHTML = raw.value;
                    raw.setAttribute('hidden', '');
                    editable.removeAttribute('hidden');
                } else {
                    raw.value = editable.innerHTML;
                    editable.setAttribute('hidden', '');
                    raw.removeAttribute('hidden');
                }
            });

            root.querySelectorAll('[data-cmd]').forEach((button) => {
                button.addEventListener('click', () => {
                    editable.focus();
                    const cmd = button.dataset.cmd;
                    let arg = button.dataset.arg ?? null;
                    if (button.dataset.prompt) {
                        arg = prompt(button.dataset.prompt, 'https://');
                        if (!arg) return;
                    }
                    document.execCommand(cmd, false, arg);
                    commit(editable.innerHTML);
                });
            });
        };

        const scan = () => document.querySelectorAll('.ao-rte').forEach(init);

        scan();
        document.addEventListener('livewire:navigated', scan);
        document.addEventListener('livewire:init', () => window.Livewire?.hook?.('morphed', scan));
    })();
</script>
