{{-- The Support rail the reference portal shows beside every help page. Each entry is
     rendered only when its route exists, so disabling an extension removes the row rather
     than producing a "route not defined" error that would take down the page. --}}
@props(['active' => null])

@php
    $items = [];

    if (Auth::check() && Route::has('tickets')) {
        $items[] = ['key' => 'tickets', 'label' => __('theme.my_tickets'), 'url' => route('tickets'), 'icon' => 'ticket'];
    }
    if (Route::has('announcements.index')) {
        $items[] = ['key' => 'announcements', 'label' => __('sitepages.announcements'), 'url' => route('announcements.index'), 'icon' => 'list'];
    }
    if (Route::has('knowledgebase.index')) {
        $items[] = ['key' => 'knowledgebase', 'label' => __('knowledgebase.title'), 'url' => route('knowledgebase.index'), 'icon' => 'info'];
    }
    if (Route::has('downloads')) {
        $items[] = ['key' => 'downloads', 'label' => __('clienttools.downloads'), 'url' => route('downloads'), 'icon' => 'download'];
    }
    if (Route::has('network-status')) {
        $items[] = ['key' => 'network-status', 'label' => __('sitepages.network_status'), 'url' => route('network-status'), 'icon' => 'pulse'];
    }
    if (Route::has('tickets.create')) {
        $items[] = ['key' => 'open-ticket', 'label' => __('theme.open_ticket'), 'url' => route('tickets.create'), 'icon' => 'chat'];
    }
@endphp

@if (count($items))
    <div class="wf-panel wf-panel--brand">
        <div class="wf-panel-heading">
            <span><span class="wf-head-icon"><x-ri-customer-service-fill /></span>{{ __('theme.support') }}</span>
            <span class="wf-chevron">&#9650;</span>
        </div>
        <ul class="wf-list">
            @foreach ($items as $item)
                <li>
                    <a href="{{ $item['url'] }}" wire:navigate
                       class="{{ $active === $item['key'] ? 'is-active' : '' }}">
                        <span>{{ $item['label'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endif
