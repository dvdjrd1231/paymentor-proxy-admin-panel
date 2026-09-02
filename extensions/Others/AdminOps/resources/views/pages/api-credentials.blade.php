{{--
    Manage API Credentials, to issue #50's reference: the green Generate button and the
    Identifier / Description / Admin User / Last Access grid. The identifier is truncated
    on purpose — Paymenter's token IS the secret, and a list must not print secrets.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        <div class="ao-tx-tabs">
            <button type="button" class="ao-mu-tab ao-api-generate {{ $generating ? 'ao-on' : '' }}" wire:click="toggleGenerating">&#10010; Generate New API Credential</button>
        </div>

        @if ($generating)
            <form class="ao-anc-card" wire:submit.prevent="generate">
                <label class="ao-anc-row">
                    <span>Description</span>
                    <input type="text" class="ao-w-40" wire:model="newName" placeholder="What this credential is for" required>
                </label>
                <div class="ao-pr-center"><button type="submit" class="ao-find-go">Generate</button></div>
            </form>
            @if ($errors->any())
                <ul class="ao-anc-errors">
                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            @endif
        @endif

        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th>Identifier</th>
                    <th>Description</th>
                    <th>Admin User</th>
                    <th>Last Access</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($keys as $entry)
                    @php $key = $entry['row']; @endphp
                    <tr>
                        <td class="ao-mu-left">
                            <code title="Truncated — the full token is the secret itself and is only shown on the credential's own page">{{ str($key->token)->limit(10) }}</code>
                        </td>
                        <td class="ao-mu-left">
                            @if ($entry['edit'])
                                <a href="{{ $entry['edit'] }}">{{ $key->name ?: '—' }}</a>
                            @else
                                {{ $key->name ?: '—' }}
                            @endif
                        </td>
                        <td class="ao-mu-left">{{ $entry['user']?->email ?? '—' }}</td>
                        <td>{{ $key->last_used_at?->format('m/d/Y H:i') ?? 'Never' }}</td>
                        <td>
                            <span class="ao-mu-status {{ $key->enabled ? 'ao-mu-st-active' : 'ao-mu-st-cancelled' }}">
                                {{ $key->enabled ? 'Active' : 'Disabled' }}
                            </span>
                        </td>
                        <td class="ao-mu-actions">
                            @if ($entry['edit'])
                                <a href="{{ $entry['edit'] }}" title="Edit credential">
                                    <x-filament::icon icon="ri-edit-box-line" class="ao-mu-cell-icon" />
                                </a>
                            @endif
                            <button type="button" class="ao-mo-delete" title="Revoke credential"
                                wire:click="$set('confirming', {{ $key->id }})">
                                <x-filament::icon icon="ri-indeterminate-circle-fill" class="ao-mu-cell-icon ao-mu-icon-red" />
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        {{-- The reference has an API Roles tab; Paymenter scopes permissions per key. --}}
        <p class="ao-gs-empty">
            Paymenter has no separate API roles — each credential carries its own permission
            set, edited on the credential's page.
        </p>

        @if ($confirming)
            <div class="ao-mud-overlay" wire:click.self="$set('confirming', null)">
                <div class="ao-mud ao-mud-sm" role="alertdialog" aria-modal="true">
                    <div class="ao-mud-head">
                        Are you sure?
                        <button type="button" wire:click="$set('confirming', null)" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text">
                        <p>Are you sure you wish to revoke this API credential?</p>
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
