{{--
    EasyMDE markdown editor (overrides the default theme's component).

    The only change vs. the default is the @vite build target: this child theme
    ships no compiled assets of its own, so it must load easymde-entry.js from the
    `default` theme's build (public/default/…) instead of `public/proxy/…`, which
    doesn't exist. Without this, any page using the editor (e.g. Create Ticket,
    ticket replies) throws "Vite manifest not found at /app/public/proxy/manifest.json".
--}}
@once
    @vite('themes/default/js/easymde-entry.js', 'default')
@endonce

@script
    <script>
        const initializeEditor = () => {
            const editor = new EasyMDE({
                element: document.getElementById('editor'),
                spellChecker: false,
                previewImagesInEditor: true,
                autoDownloadFontAwesome: false,
                status: [{
                    className: 'upload-image',
                    defaultValue: '',
                }],
                toolbar: [{
                        name: 'bold',
                        action: EasyMDE.toggleBold,
                    }, {
                        name: 'italic',
                        action: EasyMDE.toggleItalic,
                    }, {
                        name: 'strikethrough',
                        action: EasyMDE.toggleStrikethrough,
                    }, {
                        name: 'link',
                        action: EasyMDE.drawLink,
                    }, '|',
                    {
                        name: 'heading',
                        action: EasyMDE.toggleHeadingSmaller,
                    }, '|',
                    {
                        name: 'quote',
                        action: EasyMDE.toggleBlockquote,
                    }, {
                        name: 'code',
                        action: EasyMDE.toggleCodeBlock,

                    }, {
                        name: 'unordered-list',
                        action: EasyMDE.toggleUnorderedList,
                    }, {
                        name: 'ordered-list',
                        action: EasyMDE.toggleOrderedList,
                    }, '|',
                    {
                        name: 'undo',
                        action: EasyMDE.undo,
                    }, {
                        name: 'redo',
                        action: EasyMDE.redo,
                    },

                ],
            });

            editor.codemirror.on('change', function() {
                @this.set('message', editor.value(), false);
            });

            // Listen for event called saved
            $wire.on('saved', () => {
                editor.clearAutosavedValue();
                editor.value('');
            });
        };

        if (window.EasyMDE) {
            initializeEditor();
        } else {
            document.addEventListener('easymde:ready', initializeEditor, { once: true });
        }
    </script>
@endscript
