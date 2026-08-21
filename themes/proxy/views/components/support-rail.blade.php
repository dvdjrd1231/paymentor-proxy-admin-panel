{{-- Shared support rail used by public information pages. --}}
@props(['active' => null])

@php
    $isActive = fn (string $name) => $active === $name;
@endphp
<div class="wf-panel wf-panel--brand">
    <div class="wf-panel-heading">
        <span><span class="wf-head-icon"><x-ri-customer-service-fill /></span>{{ __('theme.support') }}</span>
        <span class="wf-chevron">▲</span>
    </div>
    <ul class="wf-list">
        @if (Route::has('tickets'))
            <li><a href="{{ route('tickets') }}" wire:navigate><span>{{ __('theme.my_tickets') }}</span><span class="wf-head-icon"><x-ri-customer-service-fill /></span></a></li>
        @endif
        @if (Route::has('announcements.index'))
            <li><a class="{{ $isActive('announcements') ? 'is-active' : '' }}" href="{{ route('announcements.index') }}" wire:navigate><span>{{ __('Announcements') }}</span><span class="wf-head-icon"><x-ri-list-check-2 /></span></a></li>
        @endif
        @if (Route::has('knowledgebase.index'))
            <li><a class="{{ $isActive('knowledgebase') ? 'is-active' : '' }}" href="{{ route('knowledgebase.index') }}" wire:navigate><span>{{ __('knowledgebase.title') }}</span><span class="wf-head-icon"><x-ri-information-fill /></span></a></li>
        @endif
        {{-- Network Status was guarded on `network.status`, a route name that does not
             exist — SitePages registers it as `network-status` — so this row never
             rendered. There is deliberately no Downloads row: the portal has no downloads
             section. --}}
        @if (Route::has('network-status'))
            <li><a class="{{ $isActive('network-status') ? 'is-active' : '' }}" href="{{ route('network-status') }}" wire:navigate><span>{{ __('sitepages.network_status') }}</span><span class="wf-head-icon"><x-ri-rocket-2-fill /></span></a></li>
        @endif
        @if (Route::has('tickets.create'))
            <li><a href="{{ route('tickets.create') }}" wire:navigate><span>{{ __('theme.open_ticket') }}</span><span class="wf-head-icon"><x-ri-chat-3-fill /></span></a></li>
        @endif
    </ul>
</div>