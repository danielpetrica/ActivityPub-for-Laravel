<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>Daniel Petrica - RSS Feeds</title>
        <link>{{ url('/') }}</link>
        <description>Available RSS feeds from Daniel Petrica.</description>
        <language>en</language>
        <atom:link href="{{ route('rss.index', absolute: true) }}" rel="self" type="application/rss+xml" />
        @foreach($feeds as $feed)
        <item>
            <title>{{ $feed['title'] }}</title>
            <link>{{ $feed['url'] }}</link>
            <guid>{{ $feed['url'] }}</guid>
            <description>Subscribe to {{ $feed['title'] }} feed</description>
        </item>
        @endforeach
    </channel>
</rss>
