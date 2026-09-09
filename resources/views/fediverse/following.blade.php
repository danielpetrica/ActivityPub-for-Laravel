@extends('activitypub::fediverse.layout')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h2 class="h2">Following</h2>
        <a href="{{ route('fediverse.discover') }}" class="btn btn-primary">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Follow someone
        </a>
    </div>

    @if ($following->isEmpty())
        <div class="card p-8 text-center">
            <p class="text-muted">You are not following anyone yet.</p>
            <a href="{{ route('fediverse.discover') }}" class="mt-3 text-sm font-medium">Discover accounts to follow &rarr;</a>
        </div>
    @else
        <div class="grid grid-2 gap-4">
            @foreach ($following as $follow)
                @php $ra = $follow->remoteActor; @endphp
                @if ($ra)
                    <div class="card p-5 flex items-start gap-4">
                        <div class="flex-shrink-0">
                            @if ($ra->icon_url)
                                <img src="{{ $ra->icon_url }}" alt="" class="w-12 h-12 rounded-full">
                            @else
                                <div class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center">
                                    <span class="text-lg font-bold text-muted">{{ strtoupper(substr($ra->name ?? $ra->username, 0, 1)) }}</span>
                                </div>
                            @endif
                        </div>

                        <div class="flex-1">
                            <p class="text-sm truncate">{{ $ra->name ?? $ra->username }}</p>
                            <p class="text-xs text-muted truncate">{{ $ra->username }}@ {{ $ra->domain }}</p>
                            <p class="text-xs text-muted mt-1">Following since {{ $follow->created_at->format('M j, Y') }}</p>
                            @if ($follow->status->value === 'pending')
                                <span class="badge badge-yellow">Pending</span>
                            @endif
                            @if ($follow->status->value === 'accepted')
                                <span class="badge badge-green">Accepted</span>
                            @endif
                        </div>

                        <form action="{{ route('fediverse.unfollow') }}" method="POST" onsubmit="return confirm('Unfollow {{ addslashes($ra->name ?? $ra->username) }}?')">
                            @csrf
                            <input type="hidden" name="remote_actor_url" value="{{ $ra->actor_url }}">
                            <button type="submit" class="btn btn-danger btn-xs">Unfollow</button>
                        </form>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
@endsection
