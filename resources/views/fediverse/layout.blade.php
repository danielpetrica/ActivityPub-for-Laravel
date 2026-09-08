<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fediverse Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* ActivityPub post content styles */
        .post-content p { margin-bottom: 0.5em; }
        .post-content p:last-child { margin-bottom: 0; }
        .post-content a { color: #4f46e5; text-decoration: none; }
        .post-content a:hover { text-decoration: underline; }
        .post-content blockquote {
            border-left: 3px solid #e5e7eb;
            padding-left: 1em;
            margin: 0.5em 0;
            color: #6b7280;
        }
        .post-content pre {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 0.75rem;
            overflow-x: auto;
            font-size: 0.875rem;
        }
        .post-content code {
            background: #f3f4f6;
            padding: 0.125rem 0.25rem;
            border-radius: 0.25rem;
            font-size: 0.875em;
        }
        .post-content pre code {
            background: none;
            padding: 0;
        }
        .post-content ul, .post-content ol {
            margin: 0.5em 0;
            padding-left: 1.5em;
        }
        .post-content li { margin-bottom: 0.25em; }
        .post-content h1, .post-content h2, .post-content h3,
        .post-content h4, .post-content h5, .post-content h6 {
            font-weight: 600;
            margin: 0.75em 0 0.25em;
        }

        /* Mastodon microformat classes */
        .h-card { display: inline; }
        .u-url { color: #4f46e5; text-decoration: none; }
        .u-url:hover { text-decoration: underline; }
        .mention { color: #4f46e5; }
        .invisible { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
        .ellipsis::after { content: "…"; }
        .invisible + .ellipsis::after { content: none; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="flex">
        <aside class="fixed left-0 top-0 h-full w-60 bg-gray-900 text-white flex flex-col z-10">
            <div class="p-5 border-b border-gray-700">
                <h1 class="text-lg font-bold tracking-tight">Fediverse</h1>
                <p class="text-sm text-gray-400 mt-1">{{ $actor->getPreferredUsername() }}</p>
            </div>

            <nav class="flex-1 p-3 space-y-1">
                <a href="{{ route('fediverse.dashboard') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm {{ request()->routeIs('fediverse.dashboard') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    Dashboard
                </a>

                <a href="{{ route('fediverse.timeline') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm {{ request()->routeIs('fediverse.timeline') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                    Timeline
                </a>

                <a href="{{ route('fediverse.inbox') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm {{ request()->routeIs('fediverse.inbox') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                    Inbox
                </a>

                <a href="{{ route('fediverse.following') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm {{ request()->routeIs('fediverse.following') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    Following
                </a>

                <a href="{{ route('fediverse.servers') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm {{ request()->routeIs('fediverse.servers') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                    Servers
                </a>

                <a href="{{ route('fediverse.discover') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm {{ request()->routeIs('fediverse.discover*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Discover
                </a>

                <a href="{{ route('fediverse.outbox') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm {{ request()->routeIs('fediverse.outbox') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    Outbox
                </a>

                <hr class="border-gray-700 my-2">

                <a href="{{ route('fediverse.profile') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm {{ request()->routeIs('fediverse.profile') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Profile
                </a>

                <a href="{{ route('fediverse.status') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm {{ request()->routeIs('fediverse.status') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Status
                </a>
            </nav>

            <div class="p-4 border-t border-gray-700">
                <p class="text-xs text-gray-500">activitypub v1.0</p>
            </div>
        </aside>

        <main class="ml-60 flex-1 p-8">
            @if (session('success'))
                <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @yield('content')

            <footer class="mt-8 px-4 py-4 text-center text-sm text-gray-500 border-t border-gray-200">
                <p>Looking for a Laravel developer? <a href="https://danielpetrica.com/work-with-me/" class="text-indigo-600 hover:underline">Work with me</a></p>
                <p>Made with love by Daniel Petrica</p>
            </footer>
        </main>
    </div>
</body>
</html>
