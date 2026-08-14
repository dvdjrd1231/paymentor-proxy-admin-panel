{{-- Proxy management panel, rendered on the customer's service page.
     Styled with the proxy theme's `wf-*` classes; falls back gracefully on other themes.
     All wording comes from lang/en/proxypanel.php. --}}
<div class="wf-proxy-manage">

    @if (session('success'))
        <div class="wf-alert wf-alert--info">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="wf-alert wf-alert--danger">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="wf-alert wf-alert--danger">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    {{-- ── Proxy list ──────────────────────────────────────────────────── --}}
    <div class="wf-panel">
        <div class="wf-panel-heading">
            <span>{{ __('proxypanel.proxy_list') }}</span>
            @if (count($endpoints))
                <a class="wf-btn wf-btn--sm"
                   href="{{ route('extensions.servers.proxypanel.export', $service) }}">
                    {{ __('proxypanel.action_export') }}
                </a>
            @endif
        </div>

        @if (count($endpoints))
            <div class="wf-table-wrap">
                <table class="wf-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('proxypanel.endpoint') }}</th>
                            <th>{{ __('proxypanel.proxy_username') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($endpoints as $i => $endpoint)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td class="wf-kv-value">{{ $endpoint }}</td>
                                <td class="wf-kv-value">{{ $username }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="wf-empty">{{ __('proxypanel.no_proxies') }}</div>
        @endif
    </div>

    <div class="wf-grid">
        {{-- ── Authorized IPs ─────────────────────────────────────────── --}}
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

        {{-- ── Credentials + rotation ──────────────────────────────────── --}}
        <div>
            <div class="wf-panel">
                <div class="wf-panel-heading">{{ __('proxypanel.change_password') }}</div>
                <div class="wf-panel-body">
                    <form method="POST" action="{{ route('extensions.servers.proxypanel.password', $service) }}">
                        @csrf
                        <div class="wf-field">
                            <label for="proxy_password_new">{{ __('proxypanel.new_password') }}</label>
                            <input class="wf-input" type="text" id="proxy_password_new" name="password"
                                   minlength="8" maxlength="64" required autocomplete="off">
                        </div>
                        <div class="wf-actions">
                            <button type="submit" class="wf-btn wf-btn--sm">{{ __('proxypanel.save') }}</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="wf-panel">
                <div class="wf-panel-heading">{{ __('proxypanel.rotation') }}</div>
                <div class="wf-panel-body">
                    @if ($maxRotate)
                        <p class="wf-section-note">
                            {{ __('proxypanel.rotations_used') }}: {{ $rotationCounter ?? 0 }} / {{ $maxRotate }}
                        </p>
                    @endif

                    @if ($canChangeRotation)
                        <form method="POST" action="{{ route('extensions.servers.proxypanel.rotation', $service) }}">
                            @csrf
                            <div class="wf-field">
                                <label for="minutes">{{ __('proxypanel.rotation_time') }}</label>
                                <input class="wf-input" type="number" id="minutes" name="minutes"
                                       min="0" max="10080" value="{{ old('minutes', $rotationTime ?? 0) }}">
                                <span class="wf-section-note">{{ __('proxypanel.rotation_time_hint') }}</span>
                            </div>
                            <div class="wf-actions">
                                <button type="submit" class="wf-btn wf-btn--sm">{{ __('proxypanel.save') }}</button>
                            </div>
                        </form>
                    @else
                        <p class="wf-section-note">{{ __('proxypanel.rotation_change_not_allowed') }}</p>
                    @endif
                </div>
            </div>

            @if ($apiKey)
                <div class="wf-panel">
                    <div class="wf-panel-heading">{{ __('proxypanel.api_key') }}</div>
                    <div class="wf-panel-body">
                        <code class="wf-kv-value">{{ $apiKey }}</code>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
