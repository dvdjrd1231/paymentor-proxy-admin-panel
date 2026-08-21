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

    @php
        // The component loads every published announcement, so filtering here is complete
        // rather than a filter over one page of results.
        // Only ever a YYYY-MM string: the value reaches Carbon below, and an arbitrary
        // query parameter would otherwise throw an unhandled parse error.
        $month = request('month');
        $month = is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month) ? $month : null;
        $shown = $month
            ? $announcements->filter(fn ($a) => $a->published_at?->format('Y-m') === $month)
            : $announcements;
    @endphp

    <div class="wf-layout">
        <div>
            @include('sitepages::announcements.by-month-rail')
            @include('sitepages::components.support-rail', ['active' => 'announcements'])
        </div>

        <div>
            <div class="wf-panel">
                <div class="wf-panel-heading">
                    <span>{{ __('sitepages.announcements') }}</span>
                    @if ($month)
                        <span class="wf-list-sub">
                            {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}
                        </span>
                    @endif
                </div>
                <ul class="wf-list">
                    @forelse ($shown as $announcement)
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
