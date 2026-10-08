{{-- API — the endpoints a customer's own script can call, at the address the WHMCS module
     served them from, so scripts written against it keep working. --}}
<div class="wf-proxy-manage">
    @include('servers.proxypanel::partials.flash')

    <h2 class="wf-action-title">{{ __('proxypanel.api_title') }}</h2>

    @if ($apiKey)
        <div class="wf-field">
            <label for="api_key_value">{{ __('proxypanel.api_key') }}</label>
            <input class="wf-input wf-input--block wf-ips" id="api_key_value" type="text"
                   value="{{ $apiKey }}" readonly onclick="this.select()">
        </div>
    @endif

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
