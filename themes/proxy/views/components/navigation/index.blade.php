{{--
    WHMCS-style site chrome (overrides <x-navigation /> from the default theme), matched
    against the client's reference portal:

      1. White header — logo left; right side changes with auth state exactly like the
         reference: guest → Login / Register links + a filled "View Cart" button,
         auth → Notifications link + a filled Logout button.
      2. Brand-coloured menu bar:
           guest — Home, Store ▾ (Browse All + one entry per category)  |  Account ▾
           auth  — Home, Services ▾, Billing ▾, Support ▾, Open Ticket  |  Hello, {name}! ▾
         Signed-in customers do not see the public Store menu; the reference puts the shop
         behind Services ▾ → Order New Services instead.

    Public links come from Paymenter's Navigation API so category entries appear and
    disappear with the catalogue; the client-area structure is explicit because the
    reference portal's grouping (Services / Billing / Support) is a design decision,
    not something derivable from route data.
--}}
@php
    use App\Classes\Navigation;

    $links = Navigation::getLinks();                 // Home, Store ▾, extension items
    $isAuth = auth()->check();

    // The store entry feeds two places: the guest Store ▾ menu, and the signed-in
    // "Order New Services" item, which the reference points at the shop.
    $storeLink = collect($links)->first(fn ($l) => !empty($l['children']));
    $orderNewUrl = $storeLink['children'][0]['url'] ?? route('cart');

    // Account sub-pages (Personal Details, Security, …) still come from the API so
    // extension-added pages keep appearing in the account dropdown.
    $dash = collect($isAuth ? Navigation::getDashboardLinks() : []);
    $accountItem = $dash->first(fn ($l) => !empty($l['children']));
    // Core filters `condition` on top-level entries only, so a disabled feature's child
    // (Credits with credits_enabled off) would otherwise link at a 404.
    $accountChildren = array_filter($accountItem['children'] ?? [], fn ($c) => $c['condition'] ?? true);

    $isAdmin = $isAuth && auth()->user()->role_id !== null;
    $hasLogo = config('settings.logo') || config('settings.logo_dark');

    // The signed-in menu bar, in the reference portal's order and grouping.
    $clientMenu = $isAuth ? [
        ['name' => __('theme.my_services'), 'url' => route('services')],
        ['name' => __('theme.order_new_services'), 'url' => $orderNewUrl],
    ] : [];

    // Signed in, the reference bar carries only Home, the three grouped menus, Open Ticket
    // and Affiliates — the informational pages (Announcements, Knowledgebase, Network
    // Status, Contact Us) live inside Support ▾. Those entries are contributed by
    // extensions, so they are split by URL rather than by label: an extension may rename
    // its entry, but the route it points at still identifies it.
    $isAffiliate = fn ($l) => str_contains($l['url'] ?? '', 'affiliate');
    $isHome = fn ($l) => ($l['url'] ?? null) === route('home');

    $barLinks = $isAuth ? array_filter($links, $isHome) : $links;
    // Affiliates is rendered separately because the reference places it last, after Open
    // Ticket, not in the position the Navigation API returns it in.
    $affiliateLink = $isAuth ? collect($links)->first($isAffiliate) : null;

    // Everything else the API offered (plus anything an extension adds later) is folded
    // into Support ▾, after the two ticket entries.
    $supportLinks = $isAuth
        ? array_filter($links, fn ($l) => !$isHome($l) && !$isAffiliate($l) && empty($l['children']))
        : [];
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
                <a href="{{ route('cart') }}" class="wf-hbtn wf-hbtn--primary" wire:navigate>{{ __('theme.view_cart') }}</a>
            @endguest
            @auth
                <a href="{{ route('account.notifications') }}" class="wf-hbtn" wire:navigate>{{ __('theme.notifications') }}</a>
                {{-- Logout lives here (the reference puts it top-right). --}}
                <livewire:auth.logout />
            @endauth
        </div>
    </div>
</header>

<nav class="wf-menubar" x-data="{ mobile: false }">
    <div class="wf-shell wf-menubar-inner">
        <button type="button" class="wf-burger" @click="mobile = !mobile" aria-label="Menu">☰</button>

        <ul class="wf-menu" :class="{ 'wf-menu--open': mobile }">
            {{-- Rendered in the order the Navigation API returns them — Home, Store, then
                 whatever extensions have added. Pulling the flat links out first would put
                 Store last, which is not the order the reference portal uses. --}}
            @foreach ($barLinks as $link)
                @php $isStore = !empty($link['children']); @endphp

                {{-- A signed-in customer shops through Services → Order New Services, so the
                     public Store menu is guest-only, as on the reference. --}}
                @continue($isStore && $isAuth)

                @if ($isStore)
                    <li class="wf-menu-item" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" class="wf-menu-link" @click="open = !open">
                            {{ $link['name'] }} <span class="wf-caret">▾</span>
                        </button>
                        <ul class="wf-dropdown" x-show="open" x-transition x-cloak>
                            {{-- Core's Store entry carries no URL of its own (it is a pure
                                 container), so Browse All points at the first category —
                                 the storefront landing page a visitor expects. --}}
                            <li><a href="{{ $link['url'] ?? $orderNewUrl }}" wire:navigate>{{ __('theme.browse_all') }}</a></li>
                            <li class="wf-dropdown-sep"></li>
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

            @auth
                {{-- Services ▾ --}}
                <li class="wf-menu-item" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" class="wf-menu-link" @click="open = !open">
                        {{ __('navigation.services') }} <span class="wf-caret">▾</span>
                    </button>
                    <ul class="wf-dropdown" x-show="open" x-transition x-cloak>
                        @foreach ($clientMenu as $item)
                            <li><a href="{{ $item['url'] }}" wire:navigate>{{ $item['name'] }}</a></li>
                        @endforeach
                    </ul>
                </li>

                {{-- Billing ▾ --}}
                <li class="wf-menu-item" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" class="wf-menu-link" @click="open = !open">
                        {{ __('theme.billing') }} <span class="wf-caret">▾</span>
                    </button>
                    <ul class="wf-dropdown" x-show="open" x-transition x-cloak>
                        <li><a href="{{ route('invoices') }}" wire:navigate>{{ __('theme.my_invoices') }}</a></li>
                        <li><a href="{{ route('account.payment-methods') }}" wire:navigate>{{ __('theme.payment_methods') }}</a></li>
                        @if (config('settings.credits_enabled'))
                            <li><a href="{{ route('account.credits') }}" wire:navigate>{{ __('dashboard.add_funds') }}</a></li>
                        @endif
                    </ul>
                </li>

                {{-- Support ▾ --}}
                <li class="wf-menu-item" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" class="wf-menu-link" @click="open = !open">
                        {{ __('theme.support') }} <span class="wf-caret">▾</span>
                    </button>
                    <ul class="wf-dropdown" x-show="open" x-transition x-cloak>
                        <li><a href="{{ route('tickets') }}" wire:navigate>{{ __('theme.my_tickets') }}</a></li>
                        <li><a href="{{ route('tickets.create') }}" wire:navigate>{{ __('theme.open_ticket') }}</a></li>
                        @foreach ($supportLinks as $link)
                            <li @class(['wf-dropdown-sep' => $loop->first])>
                                <a href="{{ $link['url'] }}" wire:navigate>{{ $link['name'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </li>

                {{-- Open Ticket — also a direct item, exactly like the reference --}}
                <li class="wf-menu-item">
                    <a class="wf-menu-link" href="{{ route('tickets.create') }}" wire:navigate>{{ __('theme.open_ticket') }}</a>
                </li>

                @if ($affiliateLink)
                    <li class="wf-menu-item">
                        <a class="wf-menu-link" href="{{ $affiliateLink['url'] }}" wire:navigate>{{ $affiliateLink['name'] }}</a>
                    </li>
                @endif
            @endauth
        </ul>

        {{-- Right-aligned dropdown: guest → Account ▾, auth → Hello, {name}! ▾ --}}
        <div class="wf-menu-right" x-data="{ open: false }" @click.outside="open = false">
            <button type="button" class="wf-menu-link" @click="open = !open">
                @auth
                    {{ __('theme.hello', ['name' => auth()->user()->first_name]) }}
                @else
                    {{ __('navigation.account') }}
                @endauth
                <span class="wf-caret">▾</span>
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
