{{-- User Management — who can reach this account: the owner, any sub-account contacts,
     and the sessions currently signed in. --}}
<div class="wf-page">
    <div class="wf-title">
        <h1>{{ __('clienttools.user_management') }}</h1>
        <span>{{ __('clienttools.user_management_subtitle') }}</span>
    </div>
    <hr class="wf-title-rule">

    <div class="wf-crumb">
        <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a>
        <span>/</span>{{ __('clienttools.user_management') }}
    </div>

    <div class="wf-panel">
        <div class="wf-panel-heading">
            <span><span class="wf-head-icon"><x-ri-user-3-fill /></span>{{ __('clienttools.account_owner') }}</span>
        </div>
        <div class="wf-list-row">
            <div class="wf-row-main">
                <div class="wf-list-title">
                    {{ $owner->name }}
                    <span class="wf-label wf-label--info">{{ __('clienttools.owner') }}</span>
                </div>
                <span class="wf-list-sub">{{ $owner->email }}</span>
            </div>
            <div class="wf-actions">
                <a class="wf-btn wf-btn--sm" href="{{ route('account') }}" wire:navigate>
                    {{ __('dashboard.update_details') }}
                </a>
            </div>
        </div>
    </div>

    <div class="wf-panel">
        <div class="wf-panel-heading">
            <span><span class="wf-head-icon"><x-ri-team-fill /></span>{{ __('clienttools.sub_accounts') }}</span>
            <a class="wf-btn wf-btn--sm" href="{{ route('account.contacts') }}" wire:navigate>
                {{ __('clienttools.manage_contacts') }}
            </a>
        </div>

        @forelse ($subAccounts as $contact)
            <div class="wf-list-row">
                <div class="wf-row-main">
                    <div class="wf-list-title">{{ $contact->name }}</div>
                    <span class="wf-list-sub">
                        {{ $contact->email }}
                        @if (!empty($contact->permissions))
                            &middot;
                            {{ collect($contact->permissions)->map(fn ($p) => __('clienttools.perm_' . $p))->join(', ') }}
                        @else
                            &middot; {{ __('clienttools.no_permissions') }}
                        @endif
                    </span>
                </div>
                <div class="wf-actions">
                    <button type="button" class="wf-btn wf-btn--sm wf-btn--danger"
                            wire:click="revoke({{ $contact->id }})"
                            wire:confirm="{{ __('clienttools.revoke_confirm') }}">
                        {{ __('clienttools.revoke_access') }}
                    </button>
                </div>
            </div>
        @empty
            <div class="wf-empty">{{ __('clienttools.sub_accounts_empty') }}</div>
        @endforelse
    </div>

    {{-- Live sessions make "who has access right now" answerable, which is the question
         this page exists to answer. --}}
    <div class="wf-panel">
        <div class="wf-panel-heading">
            <span><span class="wf-head-icon"><x-ri-shield-check-fill /></span>{{ __('clienttools.active_sessions') }}</span>
        </div>

        @forelse ($sessions as $session)
            <div class="wf-list-row">
                <div class="wf-row-main">
                    <div class="wf-list-title">{{ $session->ip_address ?? '—' }}</div>
                    <span class="wf-list-sub">
                        {{ \Illuminate\Support\Str::limit($session->user_agent ?? '—', 70) }}
                        &middot; {{ \Carbon\Carbon::parse($session->last_activity)->diffForHumans() }}
                    </span>
                </div>
            </div>
        @empty
            <div class="wf-empty">{{ __('clienttools.sessions_empty') }}</div>
        @endforelse
    </div>
</div>
