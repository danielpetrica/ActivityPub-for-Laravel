@extends('activitypub::fediverse.layout')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Following</h2>
        <a href="{{ route('fediverse.discover') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Follow someone
        </a>
    </div>

    @if ($following->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
            <p class="text-gray-500">You are not following anyone yet.</p>
            <a href="{{ route('fediverse.discover') }}" class="mt-3 inline-block text-sm text-indigo-600 hover:text-indigo-800 font-medium">Discover accounts to follow &rarr;</a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach ($following as $follow)
                @php $ra = $follow->remoteActor; @endphp
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
                            <p class="text-xs text-gray-400 mt-1">Following since {{ $follow->created_at->format('M j, Y') }}</p>
                            @if ($follow->status->value === 'pending')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">Pending</span>
                            @endif
                            @if ($follow->status->value === 'accepted')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Accepted</span>
                            @endif
                        </div>

                        <form action="{{ route('fediverse.unfollow') }}" method="POST" onsubmit="return confirm('Unfollow {{ addslashes($ra->name ?? $ra->username) }}?')">
                            @csrf
                            <input type="hidden" name="remote_actor_url" value="{{ $ra->actor_url }}">
                            <button type="submit" class="px-3 py-1.5 text-xs font-medium text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors">
                                Unfollow
                            </button>
                        </form>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
@endsection
