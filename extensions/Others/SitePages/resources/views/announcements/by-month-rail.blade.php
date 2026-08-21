{{-- The reference portal's "By Month" archive rail beside the news list.

     Months are derived from the published announcements themselves, so the list can never
     offer a month with nothing in it. Selecting one filters the page through the `month`
     query parameter — a plain link, so the archive stays bookmarkable and works without
     JavaScript. --}}
@php
    $model = 'Paymenter\Extensions\Others\Announcements\Models\Announcement';
    $months = collect();

    if (class_exists($model)) {
        try {
            $months = $model::where('is_active', true)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->orderByDesc('published_at')
                ->get()
                ->groupBy(fn ($a) => $a->published_at->format('Y-m'));
        } catch (\Throwable $e) {
            // Table missing (extension never installed) — show the rail with no months
            // rather than taking the page down.
            $months = collect();
        }
    }

    $selected = request('month');
    $selected = is_string($selected) && preg_match('/^\d{4}-\d{2}$/', $selected) ? $selected : null;
@endphp

<div class="wf-panel wf-panel--brand">
    <div class="wf-panel-heading">
        <span><span class="wf-head-icon"><x-ri-calendar-2-fill /></span>{{ __('sitepages.by_month') }}</span>
        <span class="wf-chevron">&#9650;</span>
    </div>
    <ul class="wf-list">
        @foreach ($months as $key => $items)
            <li>
                <a href="{{ route('announcements.index', ['month' => $key]) }}"
                   class="{{ $selected === $key ? 'is-active' : '' }}">
                    <span>{{ \Carbon\Carbon::createFromFormat('Y-m', $key)->format('F Y') }}</span>
                    <span class="wf-list-sub">{{ $items->count() }}</span>
                </a>
            </li>
        @endforeach

        @if ($selected)
            <li>
                <a href="{{ route('announcements.index') }}">
                    <span>{{ __('sitepages.all_announcements') }}</span>
                </a>
            </li>
        @endif

        {{-- Our own feed, not the upstream `announcements.rss`: that one is shadowed by
             `announcements/{announcement:slug}` and 404s. See SitePages/routes/web.php. --}}
        @if (Route::has('sitepages.announcements.rss'))
            <li>
                <a href="{{ route('sitepages.announcements.rss') }}">
                    <span>{{ __('sitepages.view_rss') }}</span>
                    <span class="wf-head-icon"><x-ri-rss-fill /></span>
                </a>
            </li>
        @endif
    </ul>
</div>
