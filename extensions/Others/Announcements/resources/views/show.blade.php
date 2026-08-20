{{-- Single announcement, in the portal's panel chrome. --}}
<div class="wf-page">
    <div class="wf-pagehead">
        <h1>{{ $announcement->title }}</h1>
    </div>

    <div class="wf-crumb">
        <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a>
        <span>/</span>
        <a href="{{ route('announcements.index') }}" wire:navigate>{{ __('Announcements') }}</a>
        <span>/</span>{{ $announcement->title }}
    </div>

    <div class="wf-panel">
        <div class="wf-panel-heading">
            <span>{{ $announcement->title }}</span>
            <span class="wf-muted">{{ $announcement->published_at->diffForHumans() }}</span>
        </div>
        <div class="wf-panel-body wf-prose">
            {!! $announcement->content !!}
        </div>
    </div>
</div>
