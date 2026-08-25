{{-- One row of "start here" links. Renders nothing at all if the role may create nothing. --}}
<x-filament-widgets::widget>
    @if (count($shortcuts))
        <x-filament::section compact>
            <div class="ao-panel">
                <div class="ao-shortcuts">
                    @foreach ($shortcuts as $shortcut)
                        <a href="{{ $shortcut['url'] }}" class="ao-shortcut">
                            <x-filament::icon :icon="$shortcut['icon']" class="fi-icon fi-size-md" style="width:1rem;height:1rem;" />
                            {{ $shortcut['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
