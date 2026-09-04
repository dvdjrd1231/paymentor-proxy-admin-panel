{{--
    Extensions, to issue #52: the last Setup-menu screen still on core's raw table. The
    navy Name / Type / Enabled grid, a quick Enable/Disable, and Edit — the same shape as
    Payment Gateways (#45), Currencies (#46) and API Credentials (#50). Install Extension
    still opens core's own marketplace browser; that page is not reproduced here.
--}}
<x-filament-panels::page>
    {{-- Scoped here, same as Payment Gateways: one rule, not worth a shared-sheet entry. --}}
    <style>
        .ao-ext-actions { display: flex; justify-content: flex-end; margin-bottom: 0.75rem; }
    </style>
    <div class="ao-mu">
        @if ($installUrl)
            <div class="ao-ext-actions">
                <a class="ao-pg-btn" href="{{ $installUrl }}">Install Extension</a>
            </div>
        @endif

        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Enabled</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($extensions as $extension)
                    @php $edit = $canEdit($extension); @endphp
                    <tr>
                        <td class="ao-mu-left">{{ $extension->name }}</td>
                        <td>{{ $extension->type }}</td>
                        <td>
                            @if ($edit)
                                @if ($extension->enabled)
                                    <button type="button" class="ao-pg-btn" wire:click="confirm({{ $extension->id }}, false)">Disable</button>
                                @else
                                    <button type="button" class="ao-pg-btn" wire:click="confirm({{ $extension->id }}, true)">Enable</button>
                                @endif
                            @else
                                <span class="ao-mu-status {{ $extension->enabled ? 'ao-mu-st-active' : 'ao-mu-st-cancelled' }}">
                                    {{ $extension->enabled ? 'Active' : 'Disabled' }}
                                </span>
                            @endif
                        </td>
                        <td class="ao-mu-actions">
                            @if ($edit)
                                <a href="{{ $edit }}" title="Edit extension">
                                    <x-filament::icon icon="ri-edit-box-line" class="ao-mu-cell-icon" />
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        @if ($confirming)
            <div class="ao-mud-overlay" wire:click.self="$set('confirming', null)">
                <div class="ao-mud ao-mud-sm" role="alertdialog" aria-modal="true">
                    <div class="ao-mud-head">
                        Are you sure?
                        <button type="button" wire:click="$set('confirming', null)" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text">
                        @if ($confirmEnable)
                            <p>Enable this extension?</p>
                            <p>Its own setup routine runs now, the same as core's edit form.</p>
                        @else
                            <p>Disable this extension?</p>
                            <p>Its own teardown routine runs now. Settings and data are kept.</p>
                        @endif
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="$set('confirming', null)">Cancel</button>
                            <button type="button" class="ao-mud-delete" wire:click="run">OK</button>
                        </span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
