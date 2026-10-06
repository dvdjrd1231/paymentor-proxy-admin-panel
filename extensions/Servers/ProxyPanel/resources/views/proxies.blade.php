{{-- Proxy List — the reference's "Proxy List (download)" sidebar page. --}}
<div class="wf-proxy-manage">
    @include('servers.proxypanel::partials.flash')

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

            {{--
                A service can hold 31,500 proxies, so the table shows the first
                `endpointPreview` of them. Saying so — with the real total and a route to the
                rest — is the difference between a deliberate preview and a list that looks
                like it lost most of the customer's order.
            --}}
            @if ($endpointTotal > count($endpoints))
                <div class="wf-panel-footnote">
                    {{ __('proxypanel.showing_preview', [
                        'shown' => number_format(count($endpoints)),
                        'total' => number_format($endpointTotal),
                    ]) }}
                    <a href="{{ route('extensions.servers.proxypanel.export', $service) }}">
                        {{ __('proxypanel.action_export') }}
                    </a>
                </div>
            @endif
        @else
            <div class="wf-empty">{{ __('proxypanel.no_proxies') }}</div>
        @endif
    </div>
</div>
