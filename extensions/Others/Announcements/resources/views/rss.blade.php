{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0">
    <channel>
        <title>{{ config('app.name', 'Paymenter') }} Announcements</title>
        <link>{{ route('announcements.index') }}</link>
        <description>{{ __('theme.news_subtitle') }}</description>
        @foreach ($items as $item)
            <item>
                <title>{{ $item->title }}</title>
                <link>{{ route('announcements.show', $item) }}</link>
                <description>{{ $item->description }}</description>
                <pubDate>{{ $item->published_at->toRssString() }}</pubDate>
            </item>
        @endforeach
    </channel>
</rss>