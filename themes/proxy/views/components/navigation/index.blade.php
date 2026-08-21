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

    // The signed-in menu bar, in the reference portal's order and grouping. Entries whose
    // page comes from an extension are guarded on the route existing, so disabling that
    // extension drops the row instead of raising "route not defined" on every page.
    $clientMenu = $isAuth ? array_values(array_filter([
        ['name' => __('theme.my_services'), 'url' => route('services')],
        ['name' => __('theme.order_new_services'), 'url' => $orderNewUrl],
        Route::has('addons')
            ? ['name' => __('clienttools.addons'), 'url' => route('addons')]
            : null,
    ])) : [];

    // Signed in, the reference bar carries only Home, the three grouped menus, Open Ticket
    // and Affiliates — the informational pages (Announcements, Knowledgebase, Network
    // Status, Contact Us) live inside Support ▾. Those entries are contributed by
    // extensions, so they are split by URL rather than by label: an extension may rename
    // its entry, but the route it points at still identifies it.
    $isAffiliate = fn ($l) => str_contains($l['url'] ?? '', 'affiliate');
    $isHome = fn ($l) => ($l['url'] ?? null) === route('home');

    $barLinks = $isAuth
        ? array_filter($links, $isHome)
        : collect($links)
            ->sortBy(fn ($link) => $isHome($link) ? 0 : (!empty($link['children']) ? 1 : 2))
            ->values()
            ->all();
    // Affiliates is rendered separately because the reference places it last, after Open
    // Ticket, not in the position the Navigation API returns it in.
    $affiliateLink = $isAuth ? collect($links)->first($isAffiliate) : null;

    // Everything else the API offered (plus anything an extension adds later) is folded
    // into Support ▾, after the two ticket entries.
    // Support ▾ carries the informational pages only. Contact Us is excluded because the
    // reference's signed-in bar does not list it (it is a page for people who cannot get
    // in); it stays reachable at its own URL and in the guest bar.
    $isContact = fn ($l) => Route::has('contact') && ($l['url'] ?? null) === route('contact');

    $supportLinks = $isAuth
        ? array_filter(
            $links,
            fn ($l) => !$isHome($l) && !$isAffiliate($l) && !$isContact($l) && empty($l['children'])
        )
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
                {{-- Notifications panel. The reference shows three standing facts about the
                     account rather than a message feed: what is unpaid, what is overdue,
                     and what credit is on hand. All three are derived live here, so the
                     badge only appears when there is genuinely something to report. --}}
                @php
                    $notifUser = auth()->user();
                    $notifCurrency = session('currency', config('settings.default_currency'));

                    $notifUnpaid = $notifUser->invoices()->where('status', 'pending')->count();
                    $notifOverdue = $notifUser->invoices()
                        ->where('status', 'pending')
                        ->whereNotNull('due_at')
                        ->where('due_at', '<', now())
                        ->get();
                    $notifCredit = config('settings.credits_enabled')
                        ? $notifUser->credits()->where('currency_code', $notifCurrency)->first()
                        : null;

                    $notifications = [];

                    if ($notifUnpaid > 0) {
                        $notifications[] = ['type' => 'info', 'text' => trans_choice('theme.notif_unpaid', $notifUnpaid, ['count' => $notifUnpaid])];
                    }
                    if ($notifOverdue->isNotEmpty()) {
                        $notifications[] = ['type' => 'warning', 'text' => trans_choice('theme.notif_overdue', $notifOverdue->count(), [
                            'count' => $notifOverdue->count(),
                            'amount' => $notifOverdue->first()->formattedTotal->format($notifOverdue->sum('remaining')),
                        ])];
                    }
                    if ($notifCredit && $notifCredit->amount > 0) {
                        $notifications[] = ['type' => 'success', 'text' => __('theme.notif_credit', ['amount' => $notifCredit->formatted_amount])];
                    }
                @endphp

                <div class="wf-notif" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" class="wf-hbtn wf-notifications" @click="open = !open">
                        {{ __('theme.notifications') }}
                        @if (count($notifications))
                            <span class="wf-notifications-badge">NEW</span>
                        @endif
                        <span class="wf-notifications-caret" aria-hidden="true">▾</span>
                    </button>

                    <div class="wf-notif-panel" x-show="open" x-transition x-cloak>
                        @forelse ($notifications as $n)
                            <div class="wf-notif-row">
                                <span class="wf-notif-ico wf-notif-ico--{{ $n['type'] }}" aria-hidden="true">
                                    {{ ['info' => 'i', 'warning' => '!', 'success' => '✓'][$n['type']] }}
                                </span>
                                <span>{{ $n['text'] }}</span>
                            </div>
                        @empty
                            <div class="wf-notif-row"><span>{{ __('theme.notif_none') }}</span></div>
                        @endforelse
                    </div>
                </div>
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
                    {{-- Reference grouping: My Services | Order New Services, View
                         Available Addons — the rule separating what is owned from what can
                         be bought. --}}
                    <ul class="wf-dropdown" x-show="open" x-transition x-cloak>
                        @foreach ($clientMenu as $item)
                            <li @class(['wf-dropdown-sep' => $loop->index === 1])>
                                <a href="{{ $item['url'] }}" wire:navigate>{{ $item['name'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </li>

                {{-- Billing ▾ --}}
                <li class="wf-menu-item" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" class="wf-menu-link" @click="open = !open">
                        {{ __('theme.billing') }} <span class="wf-caret">▾</span>
                    </button>
                    {{-- The reference groups Billing as: My Invoices, My Quotes | Mass
                         Payment, Payment Methods, Add Funds — the separator marking where
                         the list moves from "what I owe" to "how I pay". --}}
                    <ul class="wf-dropdown" x-show="open" x-transition x-cloak>
                        <li><a href="{{ route('invoices') }}" wire:navigate>{{ __('theme.my_invoices') }}</a></li>
                        @if (Route::has('quotes'))
                            <li><a href="{{ route('quotes') }}" wire:navigate>{{ __('clienttools.quotes') }}</a></li>
                        @endif
                        @if (Route::has('mass-payment'))
                            <li class="wf-dropdown-sep">
                                <a href="{{ route('mass-payment') }}" wire:navigate>{{ __('clienttools.mass_payment') }}</a>
                            </li>
                        @endif
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
                    {{-- The reference lists Support as: Tickets, Announcements,
                         Knowledgebase, Network Status — one flat group. Open Ticket is not
                         repeated here because it is already a top-level item beside this
                         menu, and Contact Us is not in the signed-in bar at all. --}}
                    <ul class="wf-dropdown" x-show="open" x-transition x-cloak>
                        <li><a href="{{ route('tickets') }}" wire:navigate>{{ __('theme.tickets') }}</a></li>
                        @foreach ($supportLinks as $link)
                            <li><a href="{{ $link['url'] }}" wire:navigate>{{ $link['name'] }}</a></li>
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
                    {{-- The reference's "Hello, {name}!" menu, in its three groups:

                           Account Details, User Management, Payment Methods, Contacts, Email History
                           ── Your Profile, Change Password, Security Settings
                           ── Logout

                         Built from lists rather than inline markup so the rules fall exactly
                         where the reference puts them, and an entry whose extension is
                         disabled drops out instead of leaving a stray separator.

                         Change Password and Security Settings share /account/security:
                         Paymenter keeps the password form on its security page rather than
                         splitting the two as WHMCS does. --}}
                    @php
                        $urlFor = fn (string $name) => Route::has($name) ? route($name) : null;

                        $group1 = array_values(array_filter([
                            ['name' => __('theme.account_details'), 'url' => $urlFor('account')],
                            ['name' => __('clienttools.user_management'), 'url' => $urlFor('account.users')],
                            ['name' => __('theme.payment_methods'), 'url' => $urlFor('account.payment-methods')],
                            ['name' => __('clienttools.contacts'), 'url' => $urlFor('account.contacts')],
                            ['name' => __('clienttools.email_history'), 'url' => $urlFor('account.email-history')],
                        ], fn ($i) => $i['url']));

                        $group2 = array_values(array_filter([
                            ['name' => __('theme.your_profile'), 'url' => $urlFor('account.notifications')],
                            ['name' => __('theme.change_password'), 'url' => $urlFor('account.security')],
                            ['name' => __('theme.security_settings'), 'url' => $urlFor('account.security')],
                        ], fn ($i) => $i['url']));

                        // Anything core or an extension put in the account menu that is not
                        // already placed above — Credits, Affiliate, and whatever a future
                        // extension contributes — so nothing silently disappears.
                        $placed = array_column(array_merge($group1, $group2), 'url');
                        $extra = array_values(array_filter(
                            $accountChildren,
                            fn ($c) => !in_array($c['url'], $placed, true)
                        ));
                    @endphp

                    @foreach ($group1 as $item)
                        <li><a href="{{ $item['url'] }}" wire:navigate>{{ $item['name'] }}</a></li>
                    @endforeach

                    @foreach ($group2 as $item)
                        <li @class(['wf-dropdown-sep' => $loop->first])>
                            <a href="{{ $item['url'] }}" wire:navigate>{{ $item['name'] }}</a>
                        </li>
                    @endforeach

                    @foreach ($extra as $item)
                        <li @class(['wf-dropdown-sep' => $loop->first])>
                            <a href="{{ $item['url'] }}" wire:navigate>{{ $item['name'] }}</a>
                        </li>
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
