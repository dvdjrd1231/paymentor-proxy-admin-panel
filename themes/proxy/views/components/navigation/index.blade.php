{{--
    WHMCS-style site chrome (overrides <x-navigation /> from the default theme).

    Two stacked bars, exactly like the WHMCS "Six" layout:
      1. White header  — logo left, Login/Register/View Cart right
      2. Brand-coloured menu bar — Home / Store ▾ / (extension items), Account ▾ right

    Menu data comes from Paymenter's real navigation API, so every link points at a
    page that actually exists (categories, Announcements, tickets, admin, …).
--}}
@php
    $links = \App\Classes\Navigation::getLinks();
    $accountLinks = \App\Classes\Navigation::getAccountDropdownLinks();
    $hasLogo = config('settings.logo') || config('settings.logo_dark');
@endphp

<header class="wf-header">
    <div class="wf-shell wf-header-inner">
        <a href="{{ route('home') }}" class="wf-brand" wire:navigate>
            @if ($hasLogo)
                <x-logo class="wf-logo" />
            @else
                <span class="wf-brand-text">{{ config('app.name', 'Paymenter') }}</span>
            @endif
        </a>

        <div class="wf-header-actions">
            @guest
                <a href="{{ route('login') }}" class="wf-hbtn" wire:navigate>{{ __('auth.sign_in') }}</a>
                <a href="{{ route('register') }}" class="wf-hbtn" wire:navigate>{{ __('auth.sign_up') }}</a>
            @endguest
            @auth
                <a href="{{ route('dashboard') }}" class="wf-hbtn" wire:navigate>{{ __('navigation.dashboard') }}</a>
            @endauth
            <a href="{{ route('cart') }}" class="wf-hbtn wf-hbtn--primary" wire:navigate>
                {{ __('navigation.cart') !== 'navigation.cart' ? __('navigation.cart') : 'View Cart' }}
            </a>
        </div>
    </div>
</header>

<nav class="wf-menubar" x-data="{ mobile: false }">
    <div class="wf-shell wf-menubar-inner">
        <button type="button" class="wf-burger" @click="mobile = !mobile" aria-label="Menu">☰</button>

        <ul class="wf-menu" :class="{ 'wf-menu--open': mobile }">
            @foreach ($links as $link)
                @if (!empty($link['children']))
                    <li class="wf-menu-item" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" class="wf-menu-link" @click="open = !open">
                            {{ $link['name'] }} <span class="wf-caret">▾</span>
                        </button>
                        <ul class="wf-dropdown" x-show="open" x-transition x-cloak>
                            @foreach ($link['children'] as $child)
                                <li><a href="{{ $child['url'] }}" wire:navigate>{{ $child['name'] }}</a></li>
                            @endforeach
                        </ul>
                    </li>
                @else
                    <li class="wf-menu-item">
                        <a class="wf-menu-link {{ ($link['active'] ?? false) ? 'is-active' : '' }}"
                            href="{{ $link['url'] }}" wire:navigate>{{ $link['name'] }}</a>
                    </li>
                @endif
            @endforeach
        </ul>

        @auth
            <div class="wf-menu-right" x-data="{ open: false }" @click.outside="open = false">
                <button type="button" class="wf-menu-link" @click="open = !open">
                    {{ __('navigation.account') }} <span class="wf-caret">▾</span>
                </button>
                <ul class="wf-dropdown wf-dropdown--right" x-show="open" x-transition x-cloak>
                    @foreach ($accountLinks as $al)
                        <li>
                            <a href="{{ $al['url'] }}" @if ($al['spa'] ?? true) wire:navigate @endif>{{ $al['name'] }}</a>
                        </li>
                    @endforeach
                    @if (Route::has('logout'))
                        <li class="wf-dropdown-sep">
                            <a href="{{ route('logout') }}">{{ __('navigation.logout') !== 'navigation.logout' ? __('navigation.logout') : 'Logout' }}</a>
                        </li>
                    @endif
                </ul>
            </div>
        @endauth
    </div>
</nav>
