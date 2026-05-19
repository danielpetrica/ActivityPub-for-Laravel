<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{ $description ?? 'Tech blog by Daniel Petrica' }}">
    <title>{{ $title ?? 'Daniel Petrica' }}</title>

    {{-- Canonical URL for SEO; falls back to current URL --}}
    <link rel="canonical" href="{{ $canonical ?? url()->current() }}" />

    {{-- Open Graph --}}
    <meta property="og:type" content="{{ $ogType ?? 'website' }}" />
    <meta property="og:title" content="{{ $metaTitle ?? ($title ?? 'Daniel Petrica') }}" />
    <meta property="og:description" content="{{ $metaDescription ?? ($description ?? 'Tech blog by Daniel Petrica') }}" />
    <meta property="og:url" content="{{ $canonical ?? url()->current() }}" />
    @isset($metaImage)
        <meta property="og:image" content="{{ $metaImage }}" />
    @endisset

    {{-- Twitter Cards --}}
    <meta name="twitter:card" content="{{ isset($metaImage) ? 'summary_large_image' : 'summary' }}" />
    <meta name="twitter:title" content="{{ $metaTitle ?? ($title ?? 'Daniel Petrica') }}" />
    <meta name="twitter:description" content="{{ $metaDescription ?? ($description ?? 'Tech blog by Daniel Petrica') }}" />
    @isset($metaImage)
        <meta name="twitter:image" content="{{ $metaImage }}" />
    @endisset
    <meta name="twitter:site" content="{{config('app.url')}}">

    {{-- Article specific meta when available --}}
    @isset($articlePublished)
        <meta property="article:published_time" content="{{ $articlePublished }}" />
    @endisset
    @isset($articleModified)
        <meta property="article:modified_time" content="{{ $articleModified }}" />
    @endisset

    @isset($structuredData)
        <script type="application/ld+json">
            {!! json_encode($structuredData) !!}
        </script>
    @endisset

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&display=swap');

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        pre {
            font-family: 'JetBrains Mono', monospace;
        }
    </style>
</head>
<body class="bg-neutral-50 text-neutral-800 antialiased selection:bg-primary-200 selection:text-primary-900">
    @php
        $tagIds = [];
        if (isset($post)) {
            $tagIds = $post->tags->pluck('id')->toArray();
        } elseif (isset($tag)) {
            $tagIds = [$tag->id];
        }
        $announcements = \App\Classes\Business\AnnouncementBusiness::getActiveAnnouncements($tagIds);
    @endphp

    @if($announcements)
        <x-ui.announcement-bar :announcements="$announcements" />
    @endif

    <x-layouts.header />

    <main>
        {{ $slot }}
    </main>

    <x-layouts.footer />

    <!-- Reusable subscribe dialog -->
    <x-ui.subscribe-modal />

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();
    </script>
    {{ $scripts ?? '' }}
</body>
</html>
