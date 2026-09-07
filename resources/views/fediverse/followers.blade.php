@extends('activitypub::fediverse.layout')

@section('content')
    <h2 class="text-2xl font-bold text-gray-900 mb-6">Followers</h2>

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
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
@endsection
