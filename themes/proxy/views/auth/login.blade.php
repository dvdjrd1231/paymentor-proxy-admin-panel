{{--
    WHMCS-style login (Proxy theme). Overrides the default theme's login view.
    Binds to the core App\Livewire\Auth\Login component's public props (email,
    password, remember), and keeps the default theme's extension points:
    the hook('auth.login') render hook and the OAuth provider buttons.
--}}
<div class="wf-page wf-page--auth">
    <div class="wf-title">
        <h1>{{ __('auth.sign_in_title') }}</h1>
        <span>{{ __('theme.restricted') }}</span>
    </div>
    <hr class="wf-title-rule">

    <div class="wf-form-narrow">
        <form wire:submit="submit" id="login">
            <div class="wf-field" style="margin-bottom:1rem">
                <label for="email">{{ __('theme.email_address') }}</label>
                <input id="email" type="email" class="wf-input" wire:model="email" autocomplete="email" required>
                @error('email') <span class="wf-error">{{ $message }}</span> @enderror
            </div>

            <div class="wf-field" style="margin-bottom:1rem">
                <label for="password">{{ __('general.input.password') }}</label>
                <input id="password" type="password" class="wf-input" wire:model="password"
                    autocomplete="current-password" required>
                @error('password') <span class="wf-error">{{ $message }}</span> @enderror
            </div>

            <label class="wf-check">
                <input type="checkbox" wire:model="remember">
                <span>{{ __('theme.remember_me') }}</span>
            </label>

            <div style="margin-top:1rem">
                <x-captcha :form="'login'" />
            </div>

            <div class="wf-actions wf-actions--center">
                <button type="submit" class="wf-btn">{{ __('auth.sign_in') }}</button>
                <a href="{{ route('password.request') }}" class="wf-btn">
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

        </form>
    </div>
</div>
