{{--
    WHMCS-style "Set a new password" form. Props (email, password,
    password_confirmation, captcha) match the core component.
--}}
<div class="wf-page wf-page--auth">
    <div class="wf-title">
        <h1>{{ __('auth.reset_password') }}</h1>
        <span>{{ __('theme.reset_password_choose') }}</span>
    </div>
    <hr class="wf-title-rule">

    <div class="wf-form-narrow">
        <form wire:submit="submit" id="reset">
            <div class="wf-field" style="margin-bottom:1rem">
                <label for="email">{{ __('theme.email_address') }}</label>
                <input id="email" type="email" class="wf-input" wire:model="email" required disabled>
                @error('email') <span class="wf-error">{{ $message }}</span> @enderror
            </div>

            <div class="wf-field" style="margin-bottom:1rem">
                <label for="password">{{ __('general.input.password') }}</label>
                <input id="password" type="password" class="wf-input" wire:model="password"
                    autocomplete="new-password" placeholder="{{ __('general.input.password_placeholder') }}" required>
                @error('password') <span class="wf-error">{{ $message }}</span> @enderror
            </div>

            <div class="wf-field" style="margin-bottom:1rem">
                <label for="password_confirmation">{{ __('general.input.password_confirmation') }}</label>
                <input id="password_confirmation" type="password" class="wf-input"
                    wire:model="password_confirmation" autocomplete="new-password"
                    placeholder="{{ __('general.input.password_confirmation_placeholder') }}" required>
                @error('password_confirmation') <span class="wf-error">{{ $message }}</span> @enderror
            </div>

            <x-captcha :form="'reset'" />

            <div class="wf-actions wf-actions--center">
                <button type="submit" class="wf-btn">{{ __('auth.reset_password') }}</button>
            </div>
        </form>
    </div>
</div>
