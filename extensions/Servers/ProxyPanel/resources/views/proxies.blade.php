{{-- ProxyList — the reference's proxies.tpl, which Leandro asked for unchanged on
     2026-10-08 ("deixar deste jeito").

     One textarea holding every endpoint, one per line, with a full-width Export beneath
     it. Not a table: the point of this page is that the whole list can be selected and
     copied in one gesture, which a table of rows cannot do. The table also showed only a
     preview, so a customer with 31,500 proxies could not reach most of them without the
     export — here they are all present and scrollable.

     Readonly, because nothing on this page edits the list; `onclick="this.select()"` so a
     single click takes the lot. --}}
<div class="wf-proxy-manage">
    @include('servers.proxypanel::partials.flash')

    <h2 class="wf-action-title">{{ __('proxypanel.proxy_list') }}</h2>

    @if (count($endpoints))
        <textarea class="wf-proxy-dump" rows="20" readonly
                  aria-label="{{ __('proxypanel.proxy_list') }}"
                  onclick="this.select()">{{ implode("\n", $endpoints) }}</textarea>

        <a class="wf-btn wf-btn--block wf-action-go"
           href="{{ route('extensions.servers.proxypanel.export', $service) }}">
            {{ __('proxypanel.action_export_short') }}
        </a>
    @else
        <div class="wf-empty">{{ __('proxypanel.no_proxies') }}</div>
    @endif
</div>
