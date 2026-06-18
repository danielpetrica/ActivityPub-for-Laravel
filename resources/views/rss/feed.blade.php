{# RSS 2.0 Feed #}<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ $title }}</title>
        <link>{{ url('/') }}</link>
        <description>{{ $description }}</description>
        <language>en</language>
        <lastBuildDate>{{ now()->toRssString() }}</lastBuildDate>
        <atom:link href="{{ route($routeName, absolute: true) }}" rel="self" type="application/rss+xml" />
        @foreach($items as $item)
        <item>
            <title><![CDATA[{{ $item['title'] }}]]></title>
            <link>{{ $item['url'] }}</link>
            <guid isPermaLink="true">{{ $item['url'] }}</guid>
            <description><![CDATA[{{ $item['description'] }}]]></description>
            <pubDate>{{ $item['published_at']->toRssString() }}</pubDate>
        </item>
        @endforeach
    </channel>
</rss>
