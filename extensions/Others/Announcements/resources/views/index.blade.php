{{-- Announcements list, styled with the theme's panel primitives so it matches the
     rest of the portal instead of the default theme's card grid. --}}
<div class="wf-page">
    <div class="wf-pagehead">
        <h1>{{ __('Announcements') }}</h1>
    </div>

    <div class="wf-crumb">
        <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a>
        <span>/</span>{{ __('Announcements') }}
    </div>

    <div class="wf-panel">
        <div class="wf-panel-heading">
            <span><span class="wf-head-icon"><x-ri-megaphone-fill /></span>{{ __('Announcements') }}</span>
        </div>
        <ul class="wf-list">
            @forelse ($announcements as $announcement)
                <li>
                    <a href="{{ route('announcements.show', $announcement) }}" wire:navigate>
                        <span style="min-width:0">
                            <span class="wf-list-title">{{ $announcement->title }}</span>
                            <span class="wf-list-sub">{{ $announcement->description }}</span>
                        </span>
                        <span class="wf-muted" style="white-space:nowrap">{{ $announcement->published_at->diffForHumans() }}</span>
                    </a>
                </li>
            @empty
                <li><div class="wf-empty">{{ __('theme.no_announcements') }}</div></li>
            @endforelse
        </ul>
    </div>
</div>
