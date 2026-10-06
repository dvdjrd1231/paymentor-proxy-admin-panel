{{-- Set IP Authorization — the reference's sidebar page of the same name. --}}
<div class="wf-proxy-manage">
    @include('servers.proxypanel::partials.flash')

    <div class="wf-panel">
        <div class="wf-panel-heading">{{ __('proxypanel.auth_ips') }}</div>
        <div class="wf-panel-body">
            <p class="wf-section-note">{{ __('proxypanel.auth_ips_hint', ['max' => $maxAuthIps]) }}</p>

            <form method="POST" action="{{ route('extensions.servers.proxypanel.auth-ips', $service) }}">
                @csrf
                @for ($i = 0; $i < $maxAuthIps; $i++)
                    <div class="wf-field">
                        <label for="ip{{ $i }}">{{ __('proxypanel.ip_number', ['number' => $i + 1]) }}</label>
                        <input class="wf-input" type="text" id="ip{{ $i }}" name="ips[]"
                               value="{{ old('ips.' . $i, $authIps[$i] ?? '') }}"
                               placeholder="203.0.113.10">
                    </div>
                @endfor
                <div class="wf-actions">
                    <button type="submit" class="wf-btn wf-btn--sm">{{ __('proxypanel.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
