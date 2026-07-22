{{--
    WHMCS-style site chrome (overrides <x-navigation /> from the default theme).

      1. White header  — logo left, Login/Register (guest) or Dashboard (auth) + View Cart
      2. Brand-coloured menu bar:
           left  — Home / Store ▾ / extension items, plus Services, Invoices, Tickets
                   once signed in (these are Auth-gated by Paymenter itself)
           right — Account ▾ :  guest → Login / Register / Forgot Password
                                auth  → Dashboard, Personal Details, Security,
                                        Payment Methods, Notifications, Admin, Logout

    All menu data comes from Paymenter's real Navigation API, so every link points at
    a page that exists and respects Paymenter's own visibility conditions.
--}}
@php
    use App\Classes\Navigation;

    $links = Navigation::getLinks();                 // Home, Store, extension items
    $isAuth = auth()->check();

    // getDashboardLinks() is the client-area menu: Dashboard, Services, Invoices,
    // Tickets, and an "Account" item holding the sub-pages. It is Auth-gated, so it
    // comes back empty for guests.
    $dash = collect($isAuth ? Navigation::getDashboardLinks() : []);
    $accountItem = $dash->first(fn ($l) => !empty($l['children']));
    $accountChildren = $accountItem['children'] ?? [];

    if ($isAuth) {
        $clientLinks = $dash->filter(fn ($l) => empty($l['children']))->values();
    } else {
        // Visitors still see Services / Invoices / Tickets in the bar. The routes are
        // auth-protected, so clicking one simply sends them to the login page.
        $clientLinks = collect([
            ['name' => __('navigation.services'), 'url' => route('services')],
            ['name' => __('navigation.invoices'), 'url' => route('invoices')],
            [
                'name' => __('navigation.tickets'),
                'url' => route('tickets'),
                'condition' => !config('settings.tickets_disabled', false),
            ],
        ])->filter(fn ($l) => $l['condition'] ?? true)->values();
    }

    $isAdmin = $isAuth && auth()->user()->role_id !== null;
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
                <a href="{{ route('dashboard') }}" class="wf-hbtn wf-hbtn--primary" wire:navigate>{{ __('navigation.dashboard') }}</a>
            @endauth
        </div>
    </div>
</header>

<nav class="wf-menubar" x-data="{ mobile: false }">
    <div class="wf-shell wf-menubar-inner">
        <button type="button" class="wf-burger" @click="mobile = !mobile" aria-label="Menu">☰</button>

        <ul class="wf-menu" :class="{ 'wf-menu--open': mobile }">
            {{-- Public links: Home, Store ▾, extension-provided items --}}
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

            {{-- Client-area links (signed in only): Dashboard, Services, Invoices, Tickets --}}
            @foreach ($clientLinks as $link)
                <li class="wf-menu-item">
                    <a class="wf-menu-link {{ ($link['active'] ?? false) ? 'is-active' : '' }}"
                        href="{{ $link['url'] }}" wire:navigate>{{ $link['name'] }}</a>
                </li>
            @endforeach
        </ul>

        {{-- Account dropdown, right-aligned — shown to guests and members alike --}}
        <div class="wf-menu-right" x-data="{ open: false }" @click.outside="open = false">
            <button type="button" class="wf-menu-link" @click="open = !open">
                {{ __('navigation.account') }} <span class="wf-caret">▾</span>
            </button>
            <ul class="wf-dropdown wf-dropdown--right" x-show="open" x-transition x-cloak>
                @guest
                    <li><a href="{{ route('login') }}" wire:navigate>{{ __('auth.sign_in') }}</a></li>
                    <li><a href="{{ route('register') }}" wire:navigate>{{ __('auth.sign_up') }}</a></li>
                    @if (Route::has('password.request'))
                        <li class="wf-dropdown-sep">
                            <a href="{{ route('password.request') }}" wire:navigate>{{ __('auth.forgot_password') }}</a>
                        </li>
                    @endif
                @endguest

                @auth
                    <li><a href="{{ route('dashboard') }}" wire:navigate>{{ __('navigation.dashboard') }}</a></li>
                    @foreach ($accountChildren as $child)
                        <li><a href="{{ $child['url'] }}" wire:navigate>{{ $child['name'] }}</a></li>
                    @endforeach
                    @if ($isAdmin)
                        <li class="wf-dropdown-sep">
                            <a href="{{ route('filament.admin.pages.dashboard') }}">{{ __('navigation.admin') }}</a>
                        </li>
                    @endif
                    <li class="wf-dropdown-sep wf-dropdown-logout">
                        <livewire:auth.logout />
                    </li>
                @endauth
            </ul>
        </div>
    </div>
</nav>
