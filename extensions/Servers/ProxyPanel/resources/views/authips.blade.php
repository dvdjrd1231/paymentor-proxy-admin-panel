{{-- Set IP Authorization — the reference's sidebar page of the same name. Same shape as
     the other action pages: a brand heading, the explanation, then the fields. --}}
<div class="wf-proxy-manage">
    @include('servers.proxypanel::partials.flash')

    <h2 class="wf-action-title">{{ __('proxypanel.auth_ips') }}</h2>

    <div class="wf-alert wf-alert--notice wf-action-note">{{ __('proxypanel.auth_ips_hint', ['max' => $maxAuthIps]) }}</div>

    <form method="POST" action="{{ route('extensions.servers.proxypanel.auth-ips', $service) }}">
        @csrf
        {{-- One field, an address per line, which is how the reference takes them
             (Leandro, 2026-10-07: "utilizar campo de texto unico, cada IP deve estar em uma
             nova linha"). The controller still receives ips[] -- the lines are split on
             submit -- so the server-side limit and validation are untouched. --}}
        <div class="wf-field">
            <label for="auth_ips">{{ __('proxypanel.auth_ips_label', ['max' => $maxAuthIps]) }}</label>
            {{-- One address per line, which is how Leandro asked for it (2026-10-07). The
                 placeholder shows a single example: two looked like entries the field
                 already held. --}}
            <textarea class="wf-input wf-input--block wf-ips" id="auth_ips" name="ips_text"
                      rows="{{ max(3, $maxAuthIps) }}" spellcheck="false"
                      placeholder="203.0.113.10">{{ old('ips_text', implode(PHP_EOL, $authIps)) }}</textarea>
        </div>
        <button type="submit" class="wf-btn wf-btn--block wf-action-go">{{ __('proxypanel.save') }}</button>
    </form>
</div>
