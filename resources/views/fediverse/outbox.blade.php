@extends('activitypub::fediverse.layout')

@section('content')
    <h2 class="text-2xl font-bold text-gray-900 mb-6">Outbox</h2>

    @if ($activities->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
            <p class="text-gray-500">No outgoing activities yet. When you follow someone or interact with content, it will appear here.</p>
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 divide-y divide-gray-100">
            @foreach ($activities as $activity)
                <div class="px-5 py-4 flex items-start gap-4">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium whitespace-nowrap
                        @switch($activity->type->value)
                            @case('Follow') bg-blue-100 text-blue-700 @break
                            @case('Like') bg-pink-100 text-pink-700 @break
                            @case('Announce') bg-green-100 text-green-700 @break
                            @case('Create') bg-purple-100 text-purple-700 @break
                            @default bg-gray-100 text-gray-700 @endswitch
                    ">{{ $activity->type->value }}</span>

                    <div class="flex-1 min-w-0">
                        @if ($activity->remoteActor)
                            <div class="flex items-center gap-2 mb-1">
                                @if ($activity->remoteActor->icon_url)
                                    <img src="{{ $activity->remoteActor->icon_url }}" alt="" class="w-5 h-5 rounded-full">
                                @endif
                                <span class="text-sm font-medium text-gray-900">{{ $activity->remoteActor->name ?? $activity->remoteActor->username }}</span>
                            </div>
                        @endif

                        @php
                            $object = $activity->payload['object'] ?? [];
                            $objContent = is_array($object) ? ($object['content'] ?? null) : null;
                            $objId = is_string($activity->payload['object'] ?? null) ? $activity->payload['object'] : (is_array($object) ? ($object['id'] ?? null) : null);
                        @endphp

                        @if ($objContent)
                            <div class="text-sm text-gray-600 mt-1 line-clamp-3">{{ Str::limit(strip_tags($objContent), 300) }}</div>
                        @elseif ($objId && is_string($objId))
                            <p class="text-sm text-gray-500 mt-1 truncate">{{ $objId }}</p>
                        @endif

                        <p class="text-xs text-gray-400 mt-1">{{ $activity->created_at->diffForHumans() }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $activities->links() }}
        </div>
    @endif
@endsection
