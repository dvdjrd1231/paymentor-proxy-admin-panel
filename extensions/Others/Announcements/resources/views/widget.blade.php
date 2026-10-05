{{-- Dashboard news panel, in the portal's panel chrome.

     Titled "Recent News", not "Announcements", and the View All sits in the heading as a
     button rather than in the footer — the reference portal's own shape, which Leandro
     marked against ours side by side on 2026-10-05.

     Rendered even with nothing to show, again as the reference does: its Recent News panel
     is present and empty on an account with no posts, so the dashboard keeps its shape
     instead of the column collapsing. --}}
<div>
    <div class="wf-panel wf-panel--top">
        <div class="wf-panel-heading">
            <span><span class="wf-head-icon"><x-ri-newspaper-fill /></span>{{ __('theme.recent_news') }}</span>
            <a class="wf-btn wf-btn--sm" href="{{ route('announcements.index') }}" wire:navigate>
                &rarr; {{ __('dashboard.view_all') }}
            </a>
        </div>
        @if ($announcements->count() > 0)
            <ul class="wf-list">
                @foreach ($announcements as $announcement)
                    <li>
                        <a href="{{ route('announcements.show', $announcement) }}" wire:navigate>
                            <span style="min-width:0">
                                <span class="wf-list-title">{{ $announcement->title }}</span>
                                <span class="wf-list-sub">{{ $announcement->description }}</span>
                            </span>
                            <span class="wf-muted" style="white-space:nowrap">{{ $announcement->published_at->diffForHumans() }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
