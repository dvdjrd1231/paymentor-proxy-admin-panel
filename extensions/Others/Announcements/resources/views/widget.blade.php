{{-- Dashboard announcements panel, in the portal's panel chrome. --}}
@if ($announcements->count() > 0)
    <div class="wf-panel">
        <div class="wf-panel-heading">
            <span><span class="wf-head-icon"><x-ri-megaphone-fill /></span>{{ __('Announcements') }}</span>
        </div>
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
        <div class="wf-panel-foot">
            <a href="{{ route('announcements.index') }}" wire:navigate>{{ __('dashboard.view_all') }}</a>
        </div>
    </div>
@endif
