@extends('activitypub::fediverse.layout')

@section('content')
    <h2 class="text-2xl font-bold text-gray-900 mb-6">Followers</h2>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <form method="GET" action="{{ route('fediverse.followers') }}" class="flex flex-wrap gap-3 items-end">
            <div>
                <label for="domain" class="block text-xs font-medium text-gray-500 mb-1">Domain</label>
                <input type="text" name="domain" id="domain" value="{{ $domain ?? '' }}" placeholder="e.g. mastodon.social"
                    class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label for="status" class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                <select name="status" id="status" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">All</option>
                    <option value="accepted" {{ ($status ?? '') === 'accepted' ? 'selected' : '' }}>Accepted</option>
                    <option value="pending" {{ ($status ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                </select>
            </div>
            <button type="submit" class="px-4 py-1.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">Filter</button>
            @if ($domain || $status)
                <a href="{{ route('fediverse.followers') }}" class="px-4 py-1.5 text-sm text-gray-600 hover:text-gray-900">Clear</a>
            @endif
        </form>
    </div>

    @if ($followers->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
            <p class="text-gray-500">No followers yet. When someone follows you, they will appear here.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach ($followers as $follower)
                @php $ra = $follower->remoteActor; @endphp
                @if ($ra)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 flex items-start gap-4">
                        <div class="flex-shrink-0">
                            @if ($ra->icon_url)
                                <img src="{{ $ra->icon_url }}" alt="" class="w-12 h-12 rounded-full">
                            @else
                                <div class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center">
                                    <span class="text-lg font-bold text-gray-500">{{ strtoupper(substr($ra->name ?? $ra->username, 0, 1)) }}</span>
                                </div>
                            @endif
                        </div>

                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ $ra->name ?? $ra->username }}</p>
                            <p class="text-xs text-gray-500 truncate">{{ $ra->username }}@ {{ $ra->domain }}</p>
                            <p class="text-xs text-gray-400 mt-1">Following since {{ $follower->created_at->format('M j, Y') }}</p>
                            <div class="mt-2 flex gap-2">
                                <form action="{{ route('fediverse.servers.block-actor') }}" method="POST" onsubmit="return confirm('Block {{ addslashes($follower->remoteActor->name ?? $follower->remoteActor->username) }}?')">
                                    @csrf
                                    <input type="hidden" name="remote_actor_id" value="{{ $follower->remote_actor_id }}">
                                    <button type="submit" class="px-3 py-1.5 text-xs font-medium text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors">
                                        Block user
                                    </button>
                                </form>
                                <a href="{{ route('fediverse.followers', ['domain' => $follower->remoteActor->domain]) }}" class="px-3 py-1.5 text-xs font-medium text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                    Block server
                                </a>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
@endsection
