{{-- User Management, laid out as the reference portal has it: the Account rail on the left,
     then a count, a table of everyone with access, and the invite form beneath it. --}}
<div class="wf-page">
    <div class="wf-title">
        <h1>{{ __('clienttools.user_management') }}</h1>
    </div>
    <hr class="wf-title-rule">

    <div class="wf-crumb">
        <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a>
        <span>/</span><a href="{{ route('dashboard') }}" wire:navigate>{{ __('theme.client_area') }}</a>
        <span>/</span><a href="{{ route('account') }}" wire:navigate>{{ __('theme.account_details') }}</a>
        <span>/</span>{{ __('clienttools.user_management') }}
    </div>

    <div class="wf-layout">
        <x-account-rail active="users" />

        <div>
            {{-- The owner always counts, so the total is sub-accounts plus one. --}}
            <p class="wf-count">{{ trans_choice('clienttools.users_found', $subAccounts->count() + 1, ['count' => $subAccounts->count() + 1]) }}</p>

            <div class="wf-panel">
                <div class="wf-table-wrap">
                    <table class="wf-table">
                        <thead>
                            <tr>
                                <th>{{ __('clienttools.email_last_login') }}</th>
                                <th style="text-align:end">{{ __('clienttools.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <span class="wf-user-email">{{ $owner->email }}</span>
                                    <span class="wf-label wf-label--success">{{ __('clienttools.owner') }}</span>
                                    <span class="wf-owner-mark" aria-hidden="true">&hearts;</span>
                                    <span class="wf-list-sub">
                                        {{ __('clienttools.last_login') }}:
                                        {{ $lastLogin ? $lastLogin->diffForHumans() : __('clienttools.never') }}
                                    </span>
                                </td>
                                <td style="text-align:end">
                                    {{-- The owner cannot be demoted or removed, so both controls
                                         are shown disabled rather than hidden: the reference
                                         keeps the row shape identical for every user. --}}
                                    <button type="button" class="wf-btn wf-btn--sm" disabled>
                                        {{ __('clienttools.manage_permissions') }}
                                    </button>
                                    <button type="button" class="wf-btn wf-btn--sm wf-btn--danger" disabled>
                                        {{ __('clienttools.remove_access') }}
                                    </button>
                                </td>
                            </tr>

                            @foreach ($subAccounts as $contact)
                                <tr wire:key="sub-{{ $contact->id }}">
                                    <td>
                                        <span class="wf-user-email">{{ $contact->email }}</span>
                                        <span class="wf-list-sub">
                                            @if (!empty($contact->permissions))
                                                {{ collect($contact->permissions)->map(fn ($p) => __('clienttools.perm_' . $p))->join(', ') }}
                                            @else
                                                {{ __('clienttools.no_permissions') }}
                                            @endif
                                        </span>
                                    </td>
                                    <td style="text-align:end">
                                        <a class="wf-btn wf-btn--sm" href="{{ route('account.contacts') }}" wire:navigate>
                                            {{ __('clienttools.manage_permissions') }}
                                        </a>
                                        <button type="button" class="wf-btn wf-btn--sm wf-btn--danger"
                                                wire:click="revoke({{ $contact->id }})"
                                                wire:confirm="{{ __('clienttools.revoke_confirm') }}">
                                            {{ __('clienttools.remove_access') }}
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <p class="wf-section-note">{{ __('clienttools.owner_note') }}</p>

            {{-- Inviting someone is the same act as adding a sub-account contact, so this
                 form creates the contact and the Contacts page edits it. Keeping one record
                 avoids an invite and a contact drifting apart. --}}
            <div class="wf-section">{{ __('clienttools.invite_new_user') }}</div>
            <p>{{ __('clienttools.invite_help') }}</p>

            <form wire:submit.prevent="invite" class="wf-form-narrow" style="max-width:none">
                <div class="wf-field">
                    <label for="invite-email" class="sr-only">{{ __('general.input.email') }}</label>
                    <input id="invite-email" type="email" class="wf-input" wire:model="inviteEmail"
                           placeholder="name@example.com">
                    @error('inviteEmail') <span class="wf-error">{{ $message }}</span> @enderror
                </div>

                <div class="wf-actions" style="margin-top:.5rem">
                    <label class="wf-check">
                        <input type="radio" value="all" wire:model.live="invitePermissionMode">
                        <span>{{ __('clienttools.all_permissions') }}</span>
                    </label>
                    <label class="wf-check">
                        <input type="radio" value="choose" wire:model.live="invitePermissionMode">
                        <span>{{ __('clienttools.choose_permissions') }}</span>
                    </label>
                </div>

                @if ($invitePermissionMode === 'choose')
                    <div style="margin-top:.6rem">
                        @foreach ($permissionKeys as $key)
                            <label class="wf-check" style="display:block">
                                <input type="checkbox" value="{{ $key }}" wire:model="invitePermissions">
                                <span>{{ __('clienttools.perm_' . $key) }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif

                <div class="wf-actions">
                    <button type="submit" class="wf-btn" wire:loading.attr="disabled">
                        {{ __('clienttools.send_invite') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
