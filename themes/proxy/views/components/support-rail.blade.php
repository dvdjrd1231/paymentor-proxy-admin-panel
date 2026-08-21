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
        {{-- Both of these were guarded on route names that do not exist — the Downloads
             page had no route at all until ClientTools added one, and Network Status is
             registered by SitePages as `network-status`, not `network.status` — so neither
             row ever rendered. --}}
        @if (Route::has('downloads'))
            <li><a class="{{ $isActive('downloads') ? 'is-active' : '' }}" href="{{ route('downloads') }}" wire:navigate><span>{{ __('clienttools.downloads') }}</span><span class="wf-head-icon"><x-ri-download-2-fill /></span></a></li>
        @endif
        @if (Route::has('network-status'))
            <li><a class="{{ $isActive('network-status') ? 'is-active' : '' }}" href="{{ route('network-status') }}" wire:navigate><span>{{ __('sitepages.network_status') }}</span><span class="wf-head-icon"><x-ri-rocket-2-fill /></span></a></li>
        @endif
        @if (Route::has('tickets.create'))
            <li><a href="{{ route('tickets.create') }}" wire:navigate><span>{{ __('theme.open_ticket') }}</span><span class="wf-head-icon"><x-ri-chat-3-fill /></span></a></li>
        @endif
    </ul>
</div>