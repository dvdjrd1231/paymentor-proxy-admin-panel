{{--
    WHMCS-style "Lost password" form. Binds to the core password request
    component (email + captcha) exactly like the default theme.
--}}
<div class="wf-page wf-page--auth">
    <div class="wf-title">
        <h1>{{ __('auth.reset_password') }}</h1>
        <span>{{ __('theme.reset_password_subtitle') }}</span>
    </div>
    <hr class="wf-title-rule">

    <div class="wf-form-narrow">
        <form wire:submit="submit" id="reset">
            <div class="wf-field" style="margin-bottom:1rem">
                <label for="email">{{ __('theme.email_address') }}</label>
                <input id="email" type="email" class="wf-input" wire:model="email" autocomplete="email"
                    placeholder="{{ __('theme.enter_email') }}" required>
                @error('email') <span class="wf-error">{{ $message }}</span> @enderror
            </div>

            <x-captcha :form="'reset'" />

            <div class="wf-actions wf-actions--center">
                <button type="submit" class="wf-btn">{{ __('auth.reset_password') }}</button>
                <a href="{{ route('login') }}" class="wf-btn wf-btn--ghost">{{ __('auth.sign_in') }}</a>
            </div>
        </form>
    </div>
</div>
