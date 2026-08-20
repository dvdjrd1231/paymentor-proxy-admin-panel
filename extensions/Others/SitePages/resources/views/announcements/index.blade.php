{{-- Overrides the Announcements extension's own view so it uses this theme instead of the
     default theme's Tailwind components — those render a blue primary button, which is not
     the brand colour and looked out of place next to everything else.
     Variables ($announcements) come from the extension's Livewire component. --}}
<div class="wf-page">
    <div class="wf-title">
        <h1>{{ __('sitepages.news') }}</h1>
        <span>{{ __('sitepages.news_subtitle') }}</span>
    </div>
    <hr class="wf-title-rule">

    <div class="wf-crumb">
        <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a>
        <span>/</span>{{ __('sitepages.announcements') }}
    </div>

    <div class="wf-layout">
        <div>
            @include('sitepages::components.support-rail', ['active' => 'announcements'])
        </div>

        <div>
            <div class="wf-panel">
                <div class="wf-panel-heading">{{ __('sitepages.announcements') }}</div>
                <ul class="wf-list">
                    @forelse ($announcements as $announcement)
                        <li>
                            <a href="{{ route('announcements.show', $announcement) }}" wire:navigate>
                                <span style="min-width:0">
                                    <span class="wf-list-title">{{ $announcement->title }}</span>
                                    <span class="wf-list-sub">
                                        {{ $announcement->published_at?->format('d M Y') }}
                                        @if ($announcement->description) &middot; {{ $announcement->description }} @endif
                                    </span>
                                </span>
                            </a>
                        </li>
                    @empty
                        <li><div class="wf-empty">{{ __('sitepages.no_announcements') }}</div></li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
