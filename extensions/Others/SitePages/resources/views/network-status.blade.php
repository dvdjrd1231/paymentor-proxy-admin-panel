{{-- Network Status — the reference portal's "News & Information" page: a green all-clear
     when nothing is published, otherwise the incident feed. --}}
<div class="wf-page">
    <div class="wf-title">
        <h1>{{ __('sitepages.network_status') }}</h1>
        <span>{{ __('sitepages.network_status_subtitle') }}</span>
    </div>
    <hr class="wf-title-rule">

    <div class="wf-crumb">
        <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a>
        <span>/</span>{{ __('sitepages.network_status') }}
    </div>

    @forelse ($incidents as $incident)
        <div class="wf-panel">
            <div class="wf-panel-heading">
                <span>{{ $incident->title }}</span>
                <span class="wf-list-sub">{{ $incident->published_at?->format('d M Y H:i') }}</span>
            </div>
            <div class="wf-panel-body">
                @if ($incident->description)<p>{{ $incident->description }}</p>@endif
                <article class="prose dark:prose-invert">{!! $incident->content !!}</article>
            </div>
        </div>
    @empty
        <div class="wf-alert wf-alert--success" style="text-align:center">
            {{ __('sitepages.no_issues') }}
        </div>
    @endforelse
</div>
