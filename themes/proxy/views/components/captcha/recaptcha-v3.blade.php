<script>
    (() => {
        const form = @js($form ?? 'form');
        const action = form === 'register' ? 'register' : 'login';
        const siteKey = @js(config('settings.captcha_site_key'));
        const run = () => {
            const el = document.querySelector(`[data-wf-recaptcha-v3="${form}"]`);
            const wireId = el?.closest('[wire\\:id]')?.getAttribute('wire:id');
            const wire = wireId && window.Livewire ? Livewire.find(wireId) : null;
            if (!el || !wire || !window.grecaptcha?.execute) return;

            grecaptcha.ready(() => grecaptcha.execute(siteKey, { action }).then((token) => {
                wire.set('captcha', token, false);
            }));
        };

        if (!document.querySelector('#wf-recaptcha-v3-api')) {
            const script = document.createElement('script');
            script.id = 'wf-recaptcha-v3-api';
            script.src = `https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(siteKey)}`;
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);
        }

        const whenReady = () => {
            if (window.grecaptcha?.execute && window.Livewire) return run();
            window.setTimeout(whenReady, 100);
        };
        whenReady();
        document.addEventListener('livewire:navigated', whenReady);
    })();
</script>
<span data-wf-recaptcha-v3="{{ $form ?? 'form' }}" class="wf-captcha-v3">{{ __('theme.captcha_protected') }}</span>