@extends('activitypub::fediverse.layout')

@section('content')
    <h2 class="h2 mb-6">Favorites</h2>

    @if ($bookmarks->isEmpty())
        <div class="card p-8 text-center">
            <p class="text-muted">No favorites yet. Bookmark posts to see them here.</p>
        </div>
    @else
        <div class="card">
            @foreach ($bookmarks as $bookmark)
                <div class="p-4 flex items-start gap-4 border-bottom">
                    <span class="badge badge-pink">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 0.875rem; height: 0.875rem; margin-right: 0.25rem;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                        Bookmark
                    </span>

                    <div class="flex-1">
                        <a href="{{ $bookmark->object_id }}" target="_blank" rel="noopener noreferrer" class="text-sm">
                            {{ Str::limit($bookmark->object_id, 100) }}
                        </a>

                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-xs text-muted">{{ $bookmark->created_at->diffForHumans() }}</span>
                            <a href="{{ $bookmark->object_id }}" target="_blank" rel="noopener noreferrer" class="badge badge-indigo">Open link ↗</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
