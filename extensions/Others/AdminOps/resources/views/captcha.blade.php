{{--
    CAPTCHA on the admin sign-in page.

    Same challenge, same settings and the same server-side verification as the client
    login (`Admin → Settings → Security`, verified by `App\Traits\Captchable`) — there is
    one provider and one key pair for the whole install, not a second set to configure.

    It is *not* the client theme's `<x-captcha>` component. That one is styled with the
    portal's `wf-` classes and lives in the theme, which the admin panel does not load;
    this renders inside Filament's sign-in card instead. The behaviour it does copy is
    the part that was learned the hard way there:

      1. `wire:ignore` around the widget. A Livewire morph that replaced the iframe would
         silently drop a solved challenge.
      2. The widget is reset after any request that *spent* a token — one that called a
         method, carried a token, and did not redirect away — and the token is cleared on
         the component at the same time. Without both halves the next submit replays a
         token the provider has already burned and comes back "invalid".
      3. Because that means solving it again after a wrong password, the notice below
         says so. Otherwise the form returns with a blank checkbox and, on the next
         attempt, a confusing "The CAPTCHA is required."
      4. Expiry and error clear the token, so a challenge solved and then left for two
         minutes reports the accurate "required" rather than being submitted dead.

    Deliberately plain `<script>` rather than Livewire's `@@script`/`@@assets`: this is a
    Blade view embedded in a Filament schema, and those directives make Livewire re-render
    into the slot.
--}}
@php
    $provider = config('settings.captcha');
    $siteKey = config('settings.captcha_site_key');
@endphp

<div class="ao-captcha">
    {{-- The widget itself never re-renders; the messages below it must. --}}
    <div wire:ignore>
        <div id="ao-captcha-widget" @class(['ao-captcha-v3' => $provider === 'recaptcha-v3'])></div>
    </div>

    @if ($provider === 'recaptcha-v3')
        <p class="ao-captcha-note">{{ __('Protected by reCAPTCHA.') }}</p>
    @else
        <p class="ao-captcha-note" data-ao-captcha-note hidden>{{ __('Please confirm the CAPTCHA again.') }}</p>
    @endif

    @error('captcha')
        <p class="ao-captcha-error">{{ $message }}</p>
    @enderror
</div>

<script>
    (() => {
        const provider = @js($provider);
        const siteKey = @js($siteKey);

        // Filament writes `.dark` on <html>, so the challenge follows the panel's theme.
        const theme = () => document.documentElement.classList.contains('dark') ? 'dark' : 'light';

        // Every adapter is handed the live `ctx` — host element, token setter, notice
        // toggle — rather than closing over any of them, because `->spa()` means this
        // script can run again against a completely different DOM (see `mount` below).
        const options = (ctx) => ({
            sitekey: siteKey,
            theme: theme(),
            callback: (token) => { ctx.setNote(false); ctx.set(token); },
            'expired-callback': () => ctx.set(''),
            'error-callback': () => ctx.set(''),
        });

        // reCAPTCHA v3 is invisible: there is nothing to render and nothing to reset —
        // a token is fetched now, and fetched again whenever the last one is spent.
        const executeV3 = (ctx) => grecaptcha.ready(
            () => grecaptcha.execute(siteKey, { action: 'login' }).then(ctx.set),
        );

        const adapters = {
            'recaptcha-v2': {
                src: 'https://www.google.com/recaptcha/api.js?render=explicit',
                ready: () => window.grecaptcha?.render,
                render: (ctx) => grecaptcha.render(ctx.host, options(ctx)),
                reset: (ctx) => grecaptcha.reset(ctx.widget),
            },
            'recaptcha-v3': {
                src: `https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(siteKey)}`,
                ready: () => window.grecaptcha?.execute,
                render: (ctx) => executeV3(ctx),
                reset: (ctx) => executeV3(ctx),
                silent: true,
            },
            'turnstile': {
                src: 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit',
                ready: () => window.turnstile?.render,
                render: (ctx) => turnstile.render(ctx.host, options(ctx)),
                reset: (ctx) => turnstile.reset(ctx.widget),
            },
            'hcaptcha': {
                src: 'https://js.hcaptcha.com/1/api.js?render=explicit',
                ready: () => window.hcaptcha?.render,
                render: (ctx) => hcaptcha.render(ctx.host, options(ctx)),
                reset: (ctx) => hcaptcha.reset(ctx.widget),
            },
        };

        const adapter = adapters[provider];
        if (!adapter) return;

        // The <head> survives `wire:navigate`, so this loads once per visit rather than
        // once per page — and `window.grecaptcha` is already there on the second.
        if (!document.getElementById('ao-captcha-api')) {
            const script = document.createElement('script');
            script.id = 'ao-captcha-api';
            script.src = adapter.src;
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);
        }

        // The panel runs `->spa()`, so this page can arrive by `wire:navigate` — same JS
        // context, new DOM, new component id. The hook is registered once for the context
        // and looks the widget up by that id; closing over one page's element or component
        // would leave the second visit with a challenge that is never reset.
        const registry = (window.aoCaptcha ??= { widgets: new Map(), hooked: false });

        // The provider's script and Livewire load independently of each other and of this
        // view, so poll for both rather than assuming an order.
        const mount = () => {
            const host = document.getElementById('ao-captcha-widget');
            if (!host || host.dataset.aoRendered) return;

            const componentId = host.closest('[wire\\:id]')?.getAttribute('wire:id');
            const wire = componentId && window.Livewire ? Livewire.find(componentId) : null;

            if (!adapter.ready() || !wire) {
                window.setTimeout(mount, 100);

                return;
            }

            const note = document.querySelector('[data-ao-captcha-note]');
            const ctx = {
                host,
                wire,
                widget: null,
                set: (token) => wire.set('captcha', token ?? '', false),
                setNote: (visible) => note && (note.hidden = !visible),
            };

            ctx.widget = adapter.render(ctx);
            host.dataset.aoRendered = '1';
            registry.widgets.set(componentId, ctx);

            hookCommits();
        };

        const hookCommits = () => {
            if (registry.hooked) return;
            registry.hooked = true;

            Livewire.hook('commit', ({ component, commit, succeed }) => {
                const ctx = registry.widgets.get(component.id);
                if (!ctx || !commit.calls?.length) return;

                succeed(({ effect }) => {
                    if (!document.body.contains(ctx.host)) {
                        registry.widgets.delete(component.id);

                        return;
                    }

                    // Signed in: the page is leaving, and resetting a widget on the way
                    // out would fetch a challenge nobody will answer.
                    if (effect?.redirect || !ctx.wire.get('captcha')) return;

                    adapter.reset(ctx);
                    ctx.set('');
                    if (!adapter.silent) ctx.setNote(true);
                });
            });
        };

        mount();

        // Belt and braces: Livewire re-executes this script when it swaps the page in, so
        // the call above is normally enough — but if a future version stops doing that, the
        // listener still mounts the new page's widget. Registered once, or every navigation
        // would leave another copy behind.
        if (!registry.navigated) {
            registry.navigated = true;
            document.addEventListener('livewire:navigated', () => mount());
        }
    })();
</script>
