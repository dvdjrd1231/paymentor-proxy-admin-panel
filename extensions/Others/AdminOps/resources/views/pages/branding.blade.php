{{-- Logo, dark logo and favicon: one framed form of label-left rows, the way General
     Settings draws its own, with a preview of what is live beside each chooser. --}}
<x-filament-panels::page>
    {{-- `ao-gs` for the frame and rows, but not `ao-gs-page`: that one dissolves any
         wrapper inside a field (display: contents) so General Settings' controls fill
         their column, and it flattened the preview-beside-chooser pair. --}}
    <div class="ao-mu ao-gs ao-br-page">
        @if ($errors->any())
            <div class="ao-gs-errors">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="ao-gs-frame">
            @foreach (\Paymenter\Extensions\Others\AdminOps\Admin\Pages\Branding::IMAGES as $key => $image)
                @php $current = $this->currentUrl($key); @endphp
                <div class="ao-gs-row">
                    <label class="ao-gs-label" for="br-{{ $key }}">{{ $image['label'] }}</label>
                    <div class="ao-gs-field">
                        <div class="ao-br-field">
                            <div class="ao-br-preview">
                                @if ($current)
                                    <img src="{{ $current }}" alt="{{ $image['label'] }}">
                                @else
                                    <span class="ao-br-empty">None set</span>
                                @endif
                            </div>
                            <div class="ao-br-controls">
                                <input type="file" id="br-{{ $key }}" accept="{{ $image['accept'] }}"
                                    wire:model="uploads.{{ $key }}">
                                <div wire:loading wire:target="uploads.{{ $key }}" class="ao-br-note">Uploading…</div>
                                @if ($current)
                                    <button type="button" class="ao-br-clear" wire:click="clear('{{ $key }}')"
                                        wire:confirm="Remove the {{ strtolower($image['label']) }}?">Remove</button>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="ao-gs-hint">{{ $image['hint'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="ao-gs-actions">
            <button type="button" class="ao-find-go" wire:click="save">Save Changes</button>
            <a class="ao-gs-cancel" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\Branding::getUrl() }}">Cancel Changes</a>
        </div>
    </div>
</x-filament-panels::page>
