{{-- The reference portal's "Your Profile" rail: the pages that change who you are and how
     you sign in, kept apart from the Account rail, which is about the billing account.

     Change Password and Security Settings both point at /account/security — Paymenter keeps
     the password form on its security page rather than splitting the two as WHMCS does. --}}
@props(['active' => null])

@php
    $profileRail = array_values(array_filter([
        ['key' => 'profile', 'name' => __('theme.your_profile'), 'route' => 'account.notifications'],
        ['key' => 'password', 'name' => __('theme.change_password'), 'route' => 'account.security'],
        ['key' => 'security', 'name' => __('theme.security_settings'), 'route' => 'account.security'],
    ], fn ($i) => Route::has($i['route'])));
@endphp

<div class="wf-panel wf-panel--brand">
    <div class="wf-panel-heading">
        <span><span class="wf-head-icon"><x-ri-user-3-fill /></span>{{ __('theme.your_profile') }}</span>
        <span class="wf-chevron">▲</span>
    </div>
    <ul class="wf-list">
        @foreach ($profileRail as $item)
            <li>
                <a class="{{ $active === $item['key'] ? 'is-active' : '' }}"
                   href="{{ route($item['route']) }}" wire:navigate>{{ $item['name'] }}</a>
            </li>
        @endforeach
        <li class="wf-dropdown-logout"><livewire:auth.logout /></li>
    </ul>
</div>
