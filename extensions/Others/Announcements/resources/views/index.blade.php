{{-- Announcements list, styled with the theme's panel primitives so it matches the
     rest of the portal instead of the default theme's card grid. --}}
<div class="wf-page">
    <div class="wf-pagehead">
        <h1>{{ __('theme.news') }}</h1>
        <span>{{ __('theme.news_subtitle') }}</span>
    </div>

    <div class="wf-crumb">
        <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a>
        <span>/</span>{{ __('Announcements') }}
    </div>

    <div class="wf-layout">
        <div>
            <div class="wf-panel wf-panel--brand">
                <div class="wf-panel-heading">
                    <span><span class="wf-head-icon"><x-ri-calendar-2-fill /></span>{{ __('theme.by_month') }}</span>
                    <span class="wf-chevron">▲</span>
                </div>
                <ul class="wf-list">
                    <li><a href="{{ route('announcements.index') }}" wire:navigate>{{ __('theme.older_announcements') }}...</a></li>
                    @if (Route::has('announcements.rss'))
                        <li><a href="{{ route('announcements.rss') }}">{{ __('theme.view_rss_feed') }} <span class="wf-head-icon"><x-ri-rss-fill /></span></a></li>
                    @endif
                </ul>
            </div>
            <x-support-rail active="announcements" />
        </div>

        <div class="wf-panel wf-panel--announcement-list">
            <div class="wf-panel-heading">
                <span><span class="wf-head-icon"><x-ri-megaphone-fill /></span>{{ __('Announcements') }}</span>
            </div>
            <ul class="wf-list">
                @forelse ($announcements as $announcement)
                    <li>
                        <a href="{{ route('announcements.show', $announcement) }}" wire:navigate>
                            <span style="min-width:0"><span class="wf-list-title">{{ $announcement->title }}</span><span class="wf-list-sub">{{ $announcement->description }}</span></span>
                            <span class="wf-muted" style="white-space:nowrap">{{ $announcement->published_at->diffForHumans() }}</span>
                        </a>
                    </li>
                @empty
                    <li><div class="wf-alert wf-alert--notice">{{ __('theme.no_announcements') }}</div></li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
