{{--
    The impersonation bar.

    The default theme's version is styled entirely in Tailwind (`fixed bottom-0 right-0
    … p-4`), and this theme deliberately ships no Tailwind bundle — so every one of those
    classes was inert: the bar landed wherever the markup fell, hard against the right edge
    with no padding at all, and its text ran off the page (Leandro, 2026-09-16).

    Same content, styled in the theme's own CSS instead.
--}}
@if (session()->has('impersonating'))
    <div class="wf-impersonate">
        <p class="wf-impersonate-who">
            {{ __('You are currently impersonating') }}:
            <strong>{{ auth()->user()->name }}</strong>
        </p>
        <a class="wf-impersonate-leave" href="/admin/users/{{ auth()->user()->id }}/edit">
            {{ __('Leave') }}
        </a>
    </div>
@endif
