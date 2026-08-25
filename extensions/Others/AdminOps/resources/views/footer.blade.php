{{--
    WHMCS's footer bar.

    Core already ships an admin footer, but it renders at `panels::sidebar.nav.end` — inside
    the sidebar, which top navigation translates off-screen — so under this skin it is never
    seen. This puts the same Paymenter attribution back where the reference has it, across
    the bottom of the page: the copyright at the start of the bar, links at its end.
--}}
<footer class="ao-admin-footer">
    <span class="ao-admin-footer-copy">
        Copyright &copy; {{ date('Y') }} {{ config('app.name', 'Paymenter') }}. All Rights Reserved.
    </span>

    <span class="ao-admin-footer-links">
        <a href="https://paymenter.org/docs" target="_blank" rel="noopener">Documentation</a>
        <a href="https://paymenter.org" target="_blank" rel="noopener">Powered by Paymenter</a>
    </span>
</footer>
