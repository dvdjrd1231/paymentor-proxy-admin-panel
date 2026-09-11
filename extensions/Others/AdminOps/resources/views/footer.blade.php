{{-- WHMCS's footer bar. Core's own renders inside the sidebar, which top navigation moves
     off-screen, so it is never seen under this skin. --}}
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
