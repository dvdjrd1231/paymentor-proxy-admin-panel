{{-- RSS 2.0 feed for announcements, behind the reference portal's "View RSS Feed" link.

     Values go through Blade's normal `{{ }}` escaping rather than CDATA. Escaping produces
     &amp; &lt; &gt; &quot; — all valid XML entities — whereas CDATA would have shown those
     entities literally and would still break on a `]]>` inside an announcement. --}}
<?php echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"; ?>
<rss version="2.0">
    <channel>
        <title>{{ config('app.name') }} — {{ __('sitepages.announcements') }}</title>
        <link>{{ route('announcements.index') }}</link>
        <description>{{ __('sitepages.news_subtitle') }}</description>
        <language>{{ str_replace('_', '-', app()->getLocale()) }}</language>
        <lastBuildDate>{{ ($items->first()?->published_at ?? now())->toRssString() }}</lastBuildDate>

        @foreach ($items as $item)
            <item>
                <title>{{ $item->title }}</title>
                <link>{{ route('announcements.show', $item) }}</link>
                <guid isPermaLink="true">{{ route('announcements.show', $item) }}</guid>
                <pubDate>{{ $item->published_at->toRssString() }}</pubDate>
                {{-- Tags are stripped so the summary is plain text; what remains is then
                     escaped by Blade, so markup in an announcement cannot alter the feed. --}}
                <description>{{ \Illuminate\Support\Str::limit(strip_tags((string) ($item->description ?: $item->content)), 500) }}</description>
            </item>
        @endforeach
    </channel>
</rss>
