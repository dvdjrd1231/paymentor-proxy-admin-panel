{{--
    Logout control (overrides the default theme's auth/logout view).

    Deliberately unstyled here: it is used in two different places, so the look is
    set by the parent —
      .wf-header-actions .wf-logout button  → primary header button
      .wf-dropdown-logout button            → plain dropdown row
--}}
<div class="wf-logout">
    <button type="button" wire:click="logout">{{ __('auth.logout') }}</button>
</div>
