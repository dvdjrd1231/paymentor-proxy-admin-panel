{{-- Set new password — the reference's sidebar page of the same name. --}}
<div class="wf-proxy-manage">
    @include('servers.proxypanel::partials.flash')

    <h2 class="wf-action-title">{{ __('proxypanel.change_password') }}</h2>

    <div class="wf-alert wf-alert--notice wf-action-note">{{ __('proxypanel.password_rules') }}</div>

    <form method="POST" action="{{ route('extensions.servers.proxypanel.password', $service) }}">
        @csrf
        <input class="wf-input wf-input--block" type="text" id="proxy_password_new" name="password"
               minlength="8" maxlength="8" pattern="[A-Za-z0-9]{8}" required autocomplete="off"
               title="{{ __('proxypanel.password_rules') }}"
               placeholder="{{ __('proxypanel.new_password') }}"
               aria-label="{{ __('proxypanel.new_password') }}">
        <button type="submit" class="wf-btn wf-btn--block wf-action-go">{{ __('proxypanel.save') }}</button>
    </form>
</div>
