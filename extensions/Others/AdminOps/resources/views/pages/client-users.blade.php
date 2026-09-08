{{--
    The reference's Users tab: who can sign in to this account, and what each of them may
    do. The account holder is the owner; anyone else is a contact promoted to a sub-account,
    which is what ClientTools' `is_sub_account` and `permissions` already record.
--}}
<div class="ao-cu">
    @if ($hasContacts)
        <div class="ao-tx-tabs">
            <button type="button" class="ao-mu-tab" wire:click="$set('associating', true)">&#10010; Associate User</button>
        </div>
    @endif

    <table class="ao-mu-grid">
        <thead>
            <tr>
                <th>Name / Email Address</th>
                <th>Last Login Time</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            {{-- The owner always, first: the account exists because they do. --}}
            <tr>
                <td class="ao-mu-left">
                    {{ trim($user->first_name . ' ' . $user->last_name) ?: $user->email }}
                    <span class="ao-tag ao-cu-owner">OWNER</span>
                    <br><span class="ao-cpg-muted">{{ $user->email }}</span>
                </td>
                <td>{{ $user->last_login_at?->format('j M Y H:i') ?? 'N/A' }}</td>
                <td class="ao-mu-actions">
                    <a class="ao-pg-btn" href="{{ $urls['edit'] ?? \App\Admin\Resources\UserResource::getUrl('edit', ['record' => $user]) }}">Manage User</a>
                    <span class="ao-cpg-muted" title="An account cannot be taken from the person it belongs to">Remove</span>
                </td>
            </tr>

            @foreach ($rows->where('is_sub_account', true) as $row)
                <tr>
                    <td class="ao-mu-left">
                        {{ $row->name }}
                        <br><span class="ao-cpg-muted">{{ $row->email }}</span>
                    </td>
                    <td class="ao-cpg-muted" title="Sub-account sign-ins are not stamped separately from the account's">N/A</td>
                    <td class="ao-mu-actions">
                        <a class="ao-pg-btn" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $user->id, 'tab' => 'contacts']) }}">Manage User</a>
                        <button type="button" class="ao-mo-delete ao-cu-remove"
                            wire:click="removeUser({{ $row->id }})"
                            wire:confirm="Remove this person's access? They stay a contact on the account.">Remove</button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="ao-cp-note">
        A person has to be a contact on the account before they can be given access, so
        Associate User picks from
        <a class="ao-link" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $user->id, 'tab' => 'contacts']) }}">Contacts</a>
        rather than inviting a stranger by email — there is no invitation for this platform to send.
        Removing access leaves the contact in place.
    </p>
</div>

@if ($associating)
    <div class="ao-mud-overlay" wire:click.self="$set('associating', false)">
        <form class="ao-mud" wire:submit.prevent="associate">
            <div class="ao-mud-head">
                Associate User
                <button type="button" wire:click="$set('associating', false)" aria-label="Close">&times;</button>
            </div>
            <div class="ao-mud-text">
                <label class="ao-mud-field">
                    <span>Select User</span>
                    <select wire:model="associateContact">
                        <option value="">Choose a contact on this account</option>
                        @foreach ($rows->where('is_sub_account', false) as $row)
                            <option value="{{ $row->id }}">{{ $row->name }} - {{ $row->email }}</option>
                        @endforeach
                    </select>
                </label>
                @if ($rows->where('is_sub_account', false)->isEmpty())
                    <p class="ao-gs-empty">
                        Everyone on this account already has access, or there are no contacts yet.
                        Add one on the Contacts tab first.
                    </p>
                @endif

                <div class="ao-mud-field">
                    <span>Permissions</span>
                    <div class="ao-ep-radios">
                        @foreach ([
                            'account' => 'Modify Master Account Profile',
                            'services' => 'View Products & Services',
                            'invoices' => 'View & Pay Invoices',
                            'tickets' => 'View & Open Support Tickets',
                            'affiliates' => 'View & Manage Affiliate Account',
                        ] as $key => $label)
                            <label class="ao-check">
                                <input type="checkbox" value="{{ $key }}" wire:model="associatePermissions">
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="ao-cp-note">
                        The reference lists eleven permissions; five of them name features this
                        deployment does not have — domains, quotes and single sign-on — and the rest
                        are folded into the ones above. These five are what the client area actually
                        checks before it shows a page.
                    </p>
                </div>

                <div class="ao-mud-field ao-gs-off">
                    <span>Send Invite</span>
                    <label class="ao-check">
                        <input type="checkbox" disabled title="Access is granted directly; there is no invitation email to send">
                        <span>Email an invitation</span>
                    </label>
                </div>

                @error('associateContact') <p class="ao-anc-errors">{{ $message }}</p> @enderror
            </div>
            <div class="ao-mud-foot ao-mud-foot-only-right">
                <span class="ao-mud-foot-right">
                    <button type="button" class="ao-mud-close" wire:click="$set('associating', false)">Close</button>
                    <button type="submit" class="ao-find-go">Associate User</button>
                </span>
            </div>
        </form>
    </div>
@endif
