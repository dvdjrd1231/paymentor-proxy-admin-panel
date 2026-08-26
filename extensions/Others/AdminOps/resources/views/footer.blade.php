{{--
    WHMCS's footer bar.

    Core already ships an admin footer, but it renders at `panels::sidebar.nav.end` — inside
    the sidebar, which top navigation translates off-screen — so under this skin it is never
    seen. This puts the same Paymenter attribution back where the reference has it.

    Two details are the reference's, not ours. It spans the **whole** window, passing under the
    left rail rather than starting where the content does, which is why AdminOps renders this
    at `panels::body.end` (outside `.fi-layout`) instead of `panels::footer` (inside the
    content column, beside the rail). And its links are separated by pipes rather than spaced
    apart, which is markup rather than CSS so the separators stay put when the bar wraps on a
    narrow window.

    The three destinations are the reference's three — somewhere to report a fault, the
    documentation, somewhere to ask a person — pointed at Paymenter's own.
--}}
<footer class="ao-admin-footer">
    <span class="ao-admin-footer-copy">
        Copyright &copy; {{ date('Y') }} {{ config('app.name', 'Paymenter') }}. All Rights Reserved.
    </span>

    <span class="ao-admin-footer-links">
        <a href="https://github.com/Paymenter/Paymenter/issues" target="_blank" rel="noopener">Report a Bug</a>
        <span class="ao-admin-footer-sep" aria-hidden="true">|</span>
        <a href="https://paymenter.org/docs" target="_blank" rel="noopener">Documentation</a>
        <span class="ao-admin-footer-sep" aria-hidden="true">|</span>
        <a href="https://discord.gg/paymenter" target="_blank" rel="noopener">Contact Us</a>
    </span>
</footer>
