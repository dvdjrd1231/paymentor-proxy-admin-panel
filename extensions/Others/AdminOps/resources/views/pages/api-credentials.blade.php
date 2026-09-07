{{--
    Manage API Credentials, to the reference: the two tabs, the green Generate button and
    its modal, the credentials grid, and the Role Management modal with its Allowed API
    Actions split pane. The identifier is truncated on purpose — Paymenter's token IS the
    secret, and a list must not print secrets.
--}}
@php $catalogue = \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ApiCredentials::catalogue(); @endphp
<x-filament-panels::page>
    <div class="ao-mu">
        <div class="ao-tx-tabs">
            <button type="button" class="ao-mu-tab {{ $this->tab === 'credentials' ? 'ao-on' : '' }}"
                wire:click="$set('tab', 'credentials')">&#8677; API Credentials</button>
            <button type="button" class="ao-mu-tab {{ $this->tab === 'roles' ? 'ao-on' : '' }}"
                wire:click="$set('tab', 'roles')">&#9881; API Roles</button>
        </div>

        @if ($this->tab === 'credentials')
            <div class="ao-gs-actions ao-gs-actions-left">
                <button type="button" class="ao-api-generate" wire:click="toggleGenerating">
                    &#10010; Generate New API Credential
                </button>
            </div>

            <table class="ao-mu-grid">
                <thead>
                    <tr>
                        <th>Identifier</th>
                        <th>Description</th>
                        <th>Admin User</th>
                        <th>Roles</th>
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
                                <code title="Truncated — the full token is the secret itself and is only shown once, when it is generated">{{ str($key->token)->limit(10) }}</code>
                            </td>
                            <td class="ao-mu-left">
                                @if ($entry['edit'])
                                    <a href="{{ $entry['edit'] }}">{{ $key->name ?: '—' }}</a>
                                @else
                                    {{ $key->name ?: '—' }}
                                @endif
                            </td>
                            <td class="ao-mu-left">{{ $entry['user']?->email ?? '—' }}</td>
                            <td class="ao-mu-left">{{ $entry['roles']->isEmpty() ? '—' : $entry['roles']->implode(', ') }}</td>
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
                        <tr><td colspan="7" class="ao-mu-none">No Records Found</td></tr>
                    @endforelse
                </tbody>
            </table>
        @else
            <div class="ao-gs-actions ao-gs-actions-left">
                <button type="button" class="ao-api-generate" wire:click="openRole">&#10010; Create API Role</button>
            </div>

            <table class="ao-mu-grid">
                <thead>
                    <tr><th>Role Name</th><th>Description</th><th>Abilities</th><th>Credentials</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($roles as $role)
                        <tr>
                            <td class="ao-mu-left">
                                <a href="#" wire:click.prevent="openRole({{ $role->id }})">{{ $role->name }}</a>
                            </td>
                            <td class="ao-mu-left">{{ $role->description ?: '—' }}</td>
                            <td>{{ count((array) json_decode($role->permissions, true)) }}</td>
                            <td>{{ $holders[$role->id] ?? 0 }}</td>
                            <td class="ao-mu-actions">
                                <a href="#" title="Edit role" wire:click.prevent="openRole({{ $role->id }})">
                                    <x-filament::icon icon="ri-edit-box-line" class="ao-mu-cell-icon" />
                                </a>
                                <button type="button" class="ao-mo-delete" title="Delete role"
                                    wire:click="$set('confirmingRole', {{ $role->id }})">
                                    <x-filament::icon icon="ri-indeterminate-circle-fill" class="ao-mu-cell-icon ao-mu-icon-red" />
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="ao-mu-none">No Records Found</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endif

        {{-- The reference's Generate New API Credential modal. --}}
        @if ($generating)
            <div class="ao-mud-overlay" wire:click.self="$set('generating', false)">
                <form class="ao-mud" wire:submit.prevent="generate">
                    <div class="ao-mud-head">
                        Generate New API Credential
                        <button type="button" wire:click="$set('generating', false)" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text ao-api-modal">
                        <label class="ao-api-field">
                            <span>Admin User</span>
                            <select wire:model="newUser">
                                @foreach ($admins as $admin)
                                    <option value="{{ $admin->id }}">{{ trim($admin->first_name . ' ' . $admin->last_name) ?: $admin->email }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="ao-api-field">
                            <span>Description</span>
                            <input type="text" wire:model="newName" placeholder="Description" required>
                        </label>
                        <label class="ao-api-field">
                            <span>API Role(s)</span>
                            <select multiple size="4" wire:model="newRoles">
                                @forelse ($roles as $role)
                                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                                @empty
                                    <option disabled>No API roles yet — create one on the API Roles tab</option>
                                @endforelse
                            </select>
                            <i>Select the API Role(s) this credential set is assigned to. You may select more
                                than one using Ctrl + Click.</i>
                        </label>
                        @if ($errors->any())
                            <ul class="ao-anc-errors">
                                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                            </ul>
                        @endif
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="$set('generating', false)">Close</button>
                            <button type="submit" class="ao-find-go">Generate</button>
                        </span>
                    </div>
                </form>
            </div>
        @endif

        {{-- The reference's Role Management modal: name, description, and the Allowed API
             Actions split pane — categories on the left, that category's actions on the
             right with its own Check All / Uncheck All. --}}
        @if ($roleModal !== null)
            <div class="ao-mud-overlay" wire:click.self="closeRole">
                <form class="ao-mud ao-mud-lg" wire:submit.prevent="saveRole">
                    <div class="ao-mud-head">
                        Role Management
                        <button type="button" wire:click="closeRole" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text ao-api-modal">
                        <label class="ao-api-field ao-api-field-row">
                            <span>Role Name</span>
                            <input type="text" wire:model="roleName" placeholder="Role Name" required>
                        </label>
                        <label class="ao-api-field ao-api-field-row">
                            <span>Description</span>
                            <textarea rows="2" wire:model="roleDescription"
                                placeholder="Brief description for the role (Optional)"></textarea>
                        </label>

                        <h5 class="ao-api-actions-head">Allowed API Actions</h5>
                        <div class="ao-api-split">
                            <ul class="ao-api-cats">
                                @foreach ($catalogue as $group => $items)
                                    <li>
                                        <button type="button" class="{{ $category === $group ? 'ao-on' : '' }}"
                                            wire:click="$set('category', '{{ $group }}')">
                                            {{ ucwords(str_replace('_', ' ', $group)) }}
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                            <div class="ao-api-acts">
                                <h6>{{ ucwords(str_replace('_', ' ', $category)) }}</h6>
                                @foreach ($catalogue[$category] ?? [] as $key => $label)
                                    <label class="ao-rg-perm">
                                        <input type="checkbox" value="{{ $key }}" wire:model="rolePermissions">
                                        {{ $label }}
                                    </label>
                                @endforeach
                                <div class="ao-rg-bulk">
                                    <button type="button" wire:click="checkCategory(true)">Check All</button>
                                    <span>|</span>
                                    <button type="button" wire:click="checkCategory(false)">Uncheck All</button>
                                </div>
                            </div>
                        </div>
                        @if ($errors->any())
                            <ul class="ao-anc-errors">
                                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                            </ul>
                        @endif
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="closeRole">Close</button>
                            <button type="submit" class="ao-find-go">Save</button>
                        </span>
                    </div>
                </form>
            </div>
        @endif

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

        @if ($confirmingRole)
            <div class="ao-mud-overlay" wire:click.self="$set('confirmingRole', null)">
                <div class="ao-mud ao-mud-sm" role="alertdialog" aria-modal="true">
                    <div class="ao-mud-head">
                        Are you sure?
                        <button type="button" wire:click="$set('confirmingRole', null)" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text">
                        <p>Are you sure you wish to delete this API role?</p>
                        <p>Credentials assigned to it lose the abilities it granted.</p>
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="$set('confirmingRole', null)">Cancel</button>
                            <button type="button" class="ao-mud-delete" wire:click="runDeleteRole">OK</button>
                        </span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
