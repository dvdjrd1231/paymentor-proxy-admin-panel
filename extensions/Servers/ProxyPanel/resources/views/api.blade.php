{{-- API — the reference's api.tpl, which Leandro asked for unchanged on 2026-10-08
     ("deixar API desse jeito"): the heading, the three lines telling a customer where the
     key and the service id come from, a worked `a=info` response, then the action table.

     The example response is the real shape this app returns, not the reference's sample
     text — same fields, same order. The service's own id and key are already in every
     example request, so there is no separate key field here, exactly as there. --}}
<div class="wf-proxy-manage">
    @include('servers.proxypanel::partials.flash')

    <h2 class="wf-action-title">{{ __('proxypanel.api_heading') }}</h2>

    <p class="wf-section-note">{{ __('proxypanel.api_intro_key') }}</p>
    <p class="wf-section-note">{{ __('proxypanel.api_intro_id') }}</p>
    <p class="wf-section-note">{{ __('proxypanel.api_intro_example') }} <code>{{ $apiExample }}</code></p>

    <pre class="wf-api-sample">{{ $apiSample }}</pre>

    <p class="wf-section-note">{{ __('proxypanel.api_intro_json') }}</p>

    <hr class="wf-api-rule">

    <div class="wf-table-wrap">
        <table class="wf-table wf-api-table">
            <thead>
                <tr>
                    <th>{{ __('proxypanel.api_action') }}</th>
                    <th>{{ __('proxypanel.api_description') }}</th>
                    <th>{{ __('proxypanel.api_example') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($apiActions as $action)
                    <tr>
                        <td class="wf-kv-value">{{ $action['a'] }}</td>
                        <td>{{ $action['what'] }}</td>
                        <td class="wf-kv-value">{{ $action['example'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
