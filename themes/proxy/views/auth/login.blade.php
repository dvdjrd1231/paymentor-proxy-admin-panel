{{--
    WHMCS-style login (Proxy theme). Overrides the default theme's login view.
    Binds to the core App\Livewire\Auth\Login component's public props (email,
    password, remember), and keeps the default theme's extension points:
    the hook('auth.login') render hook and the OAuth provider buttons.
--}}
<div class="wf-page">
    <div class="wf-title">
        <h1>{{ __('auth.sign_in_title') }}</h1>
        <span>This page is restricted</span>
    </div>
    <hr class="wf-title-rule">

    <div class="wf-split">
        {{-- Sidebar, mirroring WHMCS's "Already Registered?" panel --}}
        <aside class="wf-aside">
            <div class="wf-aside-head">Need an account?</div>
            <div class="wf-aside-body">
                <p style="margin:0 0 .9rem">
                    Not registered with us yet? Create an account to order services and
                    manage them from your client area.
                </p>
                @if (!config('settings.registration_disabled', false))
                    <a href="{{ route('register') }}" wire:navigate class="wf-btn wf-btn--ghost wf-btn--block">
                        {{ __('auth.sign_up') }}
                    </a>
                @endif
                <a href="{{ route('password.request') }}" class="wf-btn wf-btn--ghost wf-btn--block" style="margin-top:.5rem">
                    {{ __('auth.forgot_password') }}
                </a>
            </div>
        </aside>

        <form class="wf-card" wire:submit="submit" id="login">
            <div class="wf-field" style="margin-bottom:1rem">
                <label for="email">{{ __('general.input.email') }}<span class="wf-req">*</span></label>
                <input id="email" type="email" class="wf-input" wire:model="email" autocomplete="email"
                    placeholder="{{ __('general.input.email_placeholder') }}" required>
                @error('email') <span class="wf-error">{{ $message }}</span> @enderror
            </div>

            <div class="wf-field" style="margin-bottom:1rem">
                <label for="password">{{ __('general.input.password') }}<span class="wf-req">*</span></label>
                <input id="password" type="password" class="wf-input" wire:model="password"
                    autocomplete="current-password"
                    placeholder="{{ __('general.input.password_placeholder') }}" required>
                @error('password') <span class="wf-error">{{ $message }}</span> @enderror
            </div>

            <label class="wf-check">
                <input type="checkbox" wire:model="remember">
                <span>Remember me</span>
            </label>

            <div style="margin-top:1rem">
                <x-captcha :form="'login'" />
            </div>

            <div class="wf-actions">
                <button type="submit" class="wf-btn">{{ __('auth.sign_in') }}</button>
                <a href="{{ route('password.request') }}" class="wf-btn wf-btn--ghost">
                    {{ __('auth.forgot_password') }}
                </a>
            </div>

            {!! hook('auth.login') !!}

            @if (config('settings.oauth_github') || config('settings.oauth_google') || config('settings.oauth_discord'))
                <div style="margin-top:1.5rem;text-align:center">
                    <div class="wf-section" style="margin:1rem 0">{{ __('auth.or_sign_in_with') }}</div>
                    <div style="display:flex;flex-wrap:wrap;justify-content:center;gap:.75rem">
                        @foreach (['github', 'google', 'discord'] as $provider)
                            @if (config('settings.oauth_' . $provider))
                                <a href="{{ route('oauth.redirect', $provider) }}" class="wf-btn wf-btn--ghost">
                                    {{ __(ucfirst($provider)) }}
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            @if (!config('settings.registration_disabled', false))
                <div class="wf-alt">
                    {{ __('auth.dont_have_account') }}
                    <a href="{{ route('register') }}" wire:navigate>{{ __('auth.sign_up') }}</a>
                </div>
            @endif
        </form>
    </div>
</div>
