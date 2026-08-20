{{--
    reCAPTCHA v2 checkbox (Proxy theme override).

    Three things the default theme's version gets wrong on this portal:

      1. The API script re-downloaded on every wire:navigate and the onload
         callback fired against a stale page, leaving an empty grey box where the
         checkbox should be. data-navigate-once loads it once per visit and the
         render below re-attaches on each page.

      2. It reset the widget after *every* successful Livewire request while
         leaving the spent token on the component, so the next submit replayed a
         token Google had already burned and came back "invalid". The reset here
         clears both sides, and only for a request that actually spent a token:
         one that called a method, carried a token, and did not redirect away.
         Because the challenge then has to be solved again — unavoidable, the
         backend verifies the token on every submit — the notice below says so,
         otherwise a form that failed on some other field comes back with a blank
         checkbox and "The CAPTCHA is required." on the next attempt.

      3. Expiry was unhandled: a challenge solved and then left for two minutes
         was submitted as a valid-looking but dead token. The expired and error
         callbacks now clear it, so the message is the accurate "required" one.

    Deliberately no @assets/@script here: those directives are Livewire's, and in
    a plain Blade component nested in a Livewire view they made Livewire re-render
    the whole page into the CAPTCHA's slot.
--}}
@php
    $widgetId = 'g-recaptcha-' . ($form ?? 'form');
@endphp

<script src="https://www.google.com/recaptcha/api.js?render=explicit" data-navigate-once="true" async defer></script>

<div id="{{ $widgetId }}" class="wf-captcha-widget"></div>
<span class="wf-error" data-wf-captcha-notice hidden>{{ __('theme.captcha_recheck') }}</span>

<script>
    (() => {
        const el = document.getElementById(@js($widgetId));
        if (!el || el.dataset.wfRendered) return;

        const registry = (window.wfCaptcha ??= { widgets: new Map(), hooked: false });
        const componentId = el.closest('[wire\\:id]')?.getAttribute('wire:id');

        const notice = el.parentElement?.querySelector('[data-wf-captcha-notice]');
        const setNotice = (visible) => notice && (notice.hidden = !visible);

        const render = () => {
            el.dataset.wfRendered = '1';

            const wire = componentId ? Livewire.find(componentId) : null;

            const widget = grecaptcha.render(el, {
                sitekey: @js(config('settings.captcha_site_key')),
                callback: (token) => {
                    setNotice(false);
                    wire?.set('captcha', token, false);
                },
                'expired-callback': () => wire?.set('captcha', '', false),
                'error-callback': () => wire?.set('captcha', '', false),
            });

            if (wire) registry.widgets.set(componentId, { el, widget, wire, setNotice });
        };

        // The API script is async, so it may still be in flight at this point.
        const whenReady = () => {
            if (window.grecaptcha?.render && window.Livewire) return render();
            setTimeout(whenReady, 100);
        };
        whenReady();

        // Registered once for the page rather than once per render, so navigating
        // back and forth doesn't stack duplicate handlers.
        if (registry.hooked) return;
        registry.hooked = true;

        document.addEventListener('livewire:init', () => {
            Livewire.hook('commit', ({ component, commit, succeed }) => {
                const entry = registry.widgets.get(component.id);
                if (!entry || !commit.calls?.length) return;

                succeed(({ effect }) => {
                    if (!document.body.contains(entry.el)) {
                        registry.widgets.delete(component.id);

                        return;
                    }

                    // Nothing was spent (never solved), or the browser is leaving
                    // for the post-submit page — either way, leave the widget alone.
                    if (!entry.wire.get('captcha') || effect?.redirect) return;

                    grecaptcha.reset(entry.widget);
                    entry.wire.set('captcha', '', false);
                    entry.setNotice(true);
                });
            });
        });
    })();
</script>
