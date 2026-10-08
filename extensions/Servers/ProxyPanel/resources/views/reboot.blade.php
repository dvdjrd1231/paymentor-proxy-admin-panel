{{-- Reboot — the reference asks before it takes the proxies down, and says how long for. --}}
<div class="wf-proxy-manage">
    @include('servers.proxypanel::partials.flash')

    <h2 class="wf-action-title">{{ __('proxypanel.reboot_title') }}</h2>

    <div class="wf-alert wf-alert--notice wf-action-note">{{ __('proxypanel.reboot_warning') }}</div>

    <form method="POST" action="{{ route('extensions.servers.proxypanel.reboot', $service) }}">
        @csrf
        <button type="submit" class="wf-btn wf-btn--block wf-action-go">
            {{ __('proxypanel.action_reboot_confirm') }}
        </button>
    </form>
</div>
