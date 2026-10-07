{{-- Set IP Authorization — the reference's sidebar page of the same name. Same shape as
     the other action pages: a brand heading, the explanation, then the fields. --}}
<div class="wf-proxy-manage">
    @include('servers.proxypanel::partials.flash')

    <h2 class="wf-action-title">{{ __('proxypanel.auth_ips') }}</h2>

    <div class="wf-alert wf-alert--notice wf-action-note">{{ __('proxypanel.auth_ips_hint', ['max' => $maxAuthIps]) }}</div>

    <form method="POST" action="{{ route('extensions.servers.proxypanel.auth-ips', $service) }}">
        @csrf
        @for ($i = 0; $i < $maxAuthIps; $i++)
            <div class="wf-field">
                <label for="ip{{ $i }}">{{ __('proxypanel.ip_number', ['number' => $i + 1]) }}</label>
                <input class="wf-input wf-input--block" type="text" id="ip{{ $i }}" name="ips[]"
                       value="{{ old('ips.' . $i, $authIps[$i] ?? '') }}"
                       placeholder="203.0.113.10">
            </div>
        @endfor
        <button type="submit" class="wf-btn wf-btn--block wf-action-go">{{ __('proxypanel.save') }}</button>
    </form>
</div>
