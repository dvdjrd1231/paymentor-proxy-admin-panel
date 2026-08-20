{{-- Theme override for a single announcement — see index.blade.php for why. --}}
<div class="wf-page">
    <div class="wf-title">
        <h1>{{ $announcement->title }}</h1>
        @if ($announcement->description)<span>{{ $announcement->description }}</span>@endif
    </div>
    <hr class="wf-title-rule">

    <div class="wf-crumb">
        <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a>
        <span>/</span><a href="{{ route('announcements.index') }}" wire:navigate>{{ __('sitepages.announcements') }}</a>
    </div>

    <div class="wf-layout">
        <div>
            @include('sitepages::components.support-rail', ['active' => 'announcements'])
        </div>

        <div>
            <div class="wf-panel">
                <div class="wf-panel-heading">
                    <span>{{ $announcement->title }}</span>
                    <span class="wf-list-sub">{{ $announcement->published_at?->format('d M Y H:i') }}</span>
                </div>
                <div class="wf-panel-body">
                    <article class="prose dark:prose-invert">{!! $announcement->content !!}</article>
                </div>
            </div>
        </div>
    </div>
</div>
