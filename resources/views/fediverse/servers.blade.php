@extends('activitypub::fediverse.layout')

@section('content')
    <h2 class="text-2xl font-bold text-gray-900 mb-6">Federated Servers</h2>

    @if ($servers->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
            <p class="text-gray-500">No federated servers yet. When someone follows you, their server will appear here.</p>
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 divide-y divide-gray-100">
            @foreach ($servers as $server)
                <div class="px-5 py-4 flex items-center gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center">
                        <span class="text-lg font-bold text-indigo-600">{{ strtoupper(substr($server->domain, 0, 1)) }}</span>
                    </div>

                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900">{{ $server->domain }}</p>
                        <p class="text-xs text-gray-500">{{ $server->follower_count }} {{ Str::plural('follower', $server->follower_count) }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
