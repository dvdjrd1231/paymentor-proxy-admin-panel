{{--
    The locations console. The table renders itself (Filament, array-backed); all this adds
    is the banner for the case the table cannot explain on its own — the panel being
    unreachable or unconfigured, which otherwise just looks like "no locations".
--}}
<x-filament-panels::page>
    @if ($error)
        <x-filament::section>
            <div style="display:flex;gap:.75rem;align-items:flex-start;">
                <x-filament::icon
                    icon="heroicon-o-exclamation-triangle"
                    style="width:1.25rem;height:1.25rem;flex:none;color:hsl(var(--color-warning));"
                />
                <div>
                    <p style="font-weight:600;">The panel could not be read</p>
                    <p style="color:hsl(var(--color-muted));font-size:.875rem;">{{ $error }}</p>
                </div>
            </div>
        </x-filament::section>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
