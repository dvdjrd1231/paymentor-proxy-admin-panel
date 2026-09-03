{{--
    OpenID Connect, to issue #51: the new window standard — the create tab, the navy
    Name / Client ID / Redirect URIs grid, edit and delete icons, the "Are you sure?"
    modal. Secrets never render on a list.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        @if ($createUrl)
            <div class="ao-tx-tabs">
                <a class="ao-mu-tab ao-api-generate" href="{{ $createUrl }}">&#10010; Create New OAuth Client</a>
            </div>
        @endif

        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th>Client ID</th>
                    <th>Name</th>
                    <th>Redirect URIs</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($clients as $client)
                    @php $edit = $editUrl($client); @endphp
                    <tr>
                        <td><code>{{ $client->id }}</code></td>
                        <td class="ao-mu-left">
                            @if ($edit)
                                <a href="{{ $edit }}">{{ $client->name ?: '—' }}</a>
                            @else
                                {{ $client->name ?: '—' }}
                            @endif
                        </td>
                        <td class="ao-mu-left">
                            {{ is_array($client->redirect_uris ?? null) ? implode(', ', $client->redirect_uris) : ($client->redirect ?: '—') }}
                        </td>
                        <td class="ao-mu-actions ao-mu-iconpair">
                            @if ($edit)
                                <a href="{{ $edit }}" title="Edit client — the secret is regenerated there">
                                    <x-filament::icon icon="ri-edit-box-line" class="ao-mu-cell-icon" />
                                </a>
                            @endif
                            <button type="button" title="Delete client" wire:click="$set('confirming', {{ $client->id }})">
                                <x-filament::icon icon="ri-indeterminate-circle-fill" class="ao-mu-cell-icon ao-mu-icon-red" />
                            </button>
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
                        <p>Are you sure you wish to delete this OAuth client?</p>
                        <p>Anything still using it stops authenticating immediately.</p>
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="$set('confirming', null)">Cancel</button>
                            <button type="button" class="ao-mud-delete" wire:click="runDelete">OK</button>
                        </span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
