@extends('activitypub::fediverse.layout')

@section('content')
    <h2 class="h2 mb-6">Followers
        @if (($pendingCount ?? 0) > 0)
            <span class="badge badge-yellow">{{ $pendingCount }} pending</span>
        @endif
    </h2>

    <div class="card p-4 mb-6">
        <form method="GET" action="{{ route('fediverse.followers') }}" class="flex flex-wrap gap-3 items-end">
            <div class="form-group mb-0">
                <label for="domain" class="form-label">Domain</label>
                <input type="text" name="domain" id="domain" value="{{ $domain ?? '' }}" placeholder="e.g. mastodon.social"
                    class="form-input">
            </div>
            <div class="form-group mb-0">
                <label for="status" class="form-label">Status</label>
                <select name="status" id="status" class="form-select">
                    <option value="">All</option>
                    <option value="accepted" {{ ($status ?? '') === 'accepted' ? 'selected' : '' }}>Accepted</option>
                    <option value="pending" {{ ($status ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
            @if ($domain || $status)
                <a href="{{ route('fediverse.followers') }}" class="btn btn-secondary">Clear</a>
            @endif
        </form>
    </div>

    @if ($followers->isEmpty())
        <div class="card p-8 text-center">
            <p class="text-muted">No followers yet. When someone follows you, they will appear here.</p>
        </div>
    @else
        <div class="grid grid-2 gap-4">
            @foreach ($followers as $follower)
                @php $ra = $follower->remoteActor; @endphp
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
                            <p class="text-xs text-muted mt-1">Following since {{ $follower->created_at->format('M j, Y') }}</p>
                            <div class="mt-2 flex gap-2 items-center flex-wrap">
                                @if ($follower->status->value === 'pending')
                                    <span class="badge badge-yellow">Pending approval</span>
                                    <form action="{{ route('fediverse.followers.approve', ['follower' => $follower->id]) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-primary btn-xs">Approve</button>
                                    </form>
                                    <form action="{{ route('fediverse.followers.reject', ['follower' => $follower->id]) }}" method="POST" onsubmit="return confirm('Reject {{ addslashes($ra->name ?? $ra->username) }}?')">
                                        @csrf
                                        <button type="submit" class="btn btn-danger btn-xs">Reject</button>
                                    </form>
                                @else
                                    <span class="badge badge-green">Accepted</span>
                                @endif
                                <form action="{{ route('fediverse.servers.block-actor') }}" method="POST" onsubmit="return confirm('Block {{ addslashes($ra->name ?? $ra->username) }}?')">
                                    @csrf
                                    <input type="hidden" name="remote_actor_id" value="{{ $follower->remote_actor_id }}">
                                    <button type="submit" class="btn btn-danger btn-xs">Block user</button>
                                </form>
                                <a href="{{ route('fediverse.followers', ['domain' => $ra->domain]) }}" class="btn btn-secondary btn-xs">Block server</a>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
@endsection
