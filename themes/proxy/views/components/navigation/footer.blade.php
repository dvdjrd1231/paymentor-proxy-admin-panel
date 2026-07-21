{{-- WHMCS-style footer (overrides <x-navigation.footer /> from the default theme). --}}
<footer class="wf-footer">
    <div class="wf-shell wf-footer-inner">
        <p class="wf-footer-copy">
            Copyright &copy; {{ date('Y') }} {{ config('app.name', 'Paymenter') }}. All Rights Reserved.
        </p>
        <button type="button" class="wf-totop" onclick="window.scrollTo({ top: 0, behavior: 'smooth' })"
            aria-label="Back to top">▲</button>
    </div>
</footer>
