{{--
    CAPTCHA wrapper (Proxy theme). Same switch as the default theme's component,
    but framed and error-styled like the rest of the portal's forms instead of
    with Tailwind utilities.

    wire:ignore keeps Livewire's DOM diffing away from the widget's iframe: a morph
    that replaced it would silently drop a solved challenge.
--}}
@if (config('settings.captcha') && config('settings.captcha') !== 'disabled')
    <div class="wf-captcha">
        @if (config('settings.captcha_site_key') && config('settings.captcha_secret'))
            <div wire:ignore>
                @switch(config('settings.captcha'))
                    @case('recaptcha-v2')
                        <x-captcha.recaptcha-v2 :$form />
                        @break
                    @case('recaptcha-v3')
                        <x-captcha.recaptcha-v3 :$form />
                        @break
                    @case('turnstile')
                        <x-captcha.turnstile :$form />
                        @break
                    @case('hcaptcha')
                        <x-captcha.hcaptcha :$form />
                        @break
                @endswitch
            </div>
        @else
            <span class="wf-error">CAPTCHA is enabled but its site key and secret are not configured.</span>
        @endif
        </div>

        @error('captcha')
            <span class="wf-error">{{ $message }}</span>
        @enderror
    </div>
@endif
