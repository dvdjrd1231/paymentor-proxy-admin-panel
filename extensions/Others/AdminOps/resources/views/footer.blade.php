{{--
    WHMCS's footer bar.

    Core already ships an admin footer, but it renders at `panels::sidebar.nav.end` — inside
    the sidebar, which top navigation translates off-screen — so under this skin it is never
    seen. This puts the same Paymenter attribution back where the reference has it, across
    the bottom of the page.
--}}
<footer class="ao-admin-footer">
    Copyright &copy; {{ date('Y') }} {{ config('app.name', 'Paymenter') }}. All Rights Reserved.
    &middot; Powered by <a href="https://paymenter.org" target="_blank" rel="noopener">Paymenter</a>
</footer>
