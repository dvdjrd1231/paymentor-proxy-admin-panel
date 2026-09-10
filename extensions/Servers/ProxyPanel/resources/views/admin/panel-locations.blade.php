{{--
    The locations console. The table renders itself (Filament, array-backed); all this adds
    is the banner for the case the table cannot explain on its own — the panel being
    unreachable or unconfigured, which otherwise just looks like "no locations".
--}}
<x-filament-panels::page>
    {{-- The window standard (Leandro, 2026-09-09), without giving up the working table:
         Filament still runs the search, filters and modals; these rules dress its table
         in the skin's navy grid and rounded frame. Scoped here — this is the only page
         that renders a raw Filament table. --}}
    <style>
        .fi-ta { border: 1px solid var(--wa-panel-border, #ddd); border-radius: 6px; overflow: hidden; background: #fff; }
        .fi-ta-header-cell {
            background: var(--wa-grid, #1a4d80);
            color: #fff;
        }
        .fi-ta-header-cell button, .fi-ta-header-cell span { color: #fff !important; }
        .fi-ta-row:nth-child(even) { background: #f7f7f7; }
        .fi-ta-row:hover { background: #eff2f9; }
        .fi-ta-cell { font-size: 0.9375rem; }
    </style>

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
