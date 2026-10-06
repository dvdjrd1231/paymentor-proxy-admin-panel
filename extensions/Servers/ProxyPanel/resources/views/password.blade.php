{{-- Set new password — the reference's sidebar page of the same name. --}}
<div class="wf-proxy-manage">
    @include('servers.proxypanel::partials.flash')

    <div class="wf-panel">
        <div class="wf-panel-heading">{{ __('proxypanel.change_password') }}</div>
        <div class="wf-panel-body">
            <form method="POST" action="{{ route('extensions.servers.proxypanel.password', $service) }}">
                @csrf
                <div class="wf-field">
                    <label for="proxy_password_new">{{ __('proxypanel.new_password') }}</label>
                    <input class="wf-input" type="text" id="proxy_password_new" name="password"
                           minlength="8" maxlength="8" pattern="[A-Za-z0-9]{8}" required autocomplete="off"
                           title="{{ __('proxypanel.password_rules') }}">
                    <small>{{ __('proxypanel.password_rules') }}</small>
                </div>
                <div class="wf-actions">
                    <button type="submit" class="wf-btn wf-btn--sm">{{ __('proxypanel.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
