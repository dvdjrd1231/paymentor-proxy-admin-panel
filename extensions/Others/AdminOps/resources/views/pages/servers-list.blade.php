{{--
    Servers, to issue #43's reference: the navy grid with honest usage columns, quick
    Enable/Disable/Edit actions, and the Groups band saying plainly that Paymenter has no
    server groups rather than drawing a table that could never fill.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        <div class="ao-tx-tabs">
            @if ($newUrl)
                <a class="ao-mu-tab" href="{{ $newUrl }}">&#10010; Add New Server</a>
            @endif
            {{-- The reference's second button. It is drawn so the strip reads as the target
                 does, and says why it cannot act rather than being left out. --}}
            <button type="button" class="ao-mu-tab ao-gs-off" disabled
                title="A product attaches to one module directly, so there is no group to rotate orders around">
                &#10010; Create New Group
            </button>
        </div>

        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th>Server Name</th>
                    <th>Module</th>
                    {{-- The reference's IP Address: here, the host the module talks to. --}}
                    <th>Address</th>
                    {{-- The reference's two usage columns, named for what each actually is:
                         what this install has recorded, and what the panel itself reports. --}}
                    <th>Recorded Usage</th>
                    <th>Remote Usage</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($servers as $entry)
                    @php $server = $entry['row']; @endphp
                    <tr>
                        <td class="ao-mu-left"><b>{{ $server->name }}</b></td>
                        <td>{{ $server->extension }}</td>
                        <td>
                            @if ($entry['address'])
                                {{ $entry['address'] }}
                            @else
                                <span class="ao-cpg-muted" title="This module has no address configured yet">&mdash;</span>
                            @endif
                        </td>
                        <td>{{ number_format($entry['products']) }} products &middot; {{ number_format($entry['services']) }} active services</td>
                        <td>{{ $entry['usage'] ?? '—' }}</td>
                        <td>
                            <span class="ao-mu-status {{ $server->enabled ? 'ao-mu-st-active' : 'ao-mu-st-cancelled' }}">
                                {{ $server->enabled ? 'Active' : 'Disabled' }}
                            </span>
                        </td>
                        <td class="ao-mu-actions">
                            @if ($entry['edit'])
                                @if ($server->enabled)
                                    <button type="button" class="ao-pg-btn" wire:click="confirm({{ $server->id }}, false)">Disable</button>
                                @else
                                    <button type="button" class="ao-pg-btn" wire:click="confirm({{ $server->id }}, true)">Enable</button>
                                @endif
                                <a href="{{ $entry['edit'] }}">Edit</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        <h4 class="ao-ano-heading">Groups</h4>
        <p class="ao-gs-empty">
            Server groups let the reference put several servers behind one product and fill or
            rotate between them. Here a product is attached to one module directly, on its own
            edit page, so there is no group to build and the table below stays empty.
        </p>

        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th>Group Name</th>
                    <th>Fill Type</th>
                    <th>Servers</th>
                </tr>
            </thead>
            <tbody>
                <tr><td colspan="3" class="ao-mu-none">No Records Found</td></tr>
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
                            <p>Enable this server module?</p>
                            <p>New services provision through it again.</p>
                        @else
                            <p>Disable this server module?</p>
                            <p>It stops provisioning new services. Running services are untouched.</p>
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
