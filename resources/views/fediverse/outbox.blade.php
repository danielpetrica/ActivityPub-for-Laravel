@extends('activitypub::fediverse.layout')

@section('content')
    <h2 class="h2 mb-6">Outbox</h2>

    @if ($activities->isEmpty())
        <div class="card p-8 text-center">
            <p class="text-muted">No outgoing activities yet. When you follow someone or interact with content, it will appear here.</p>
        </div>
    @else
        <div class="card">
            @foreach ($activities as $activity)
                <div class="p-4 flex items-start gap-4 border-bottom">
                    <span class="badge
                        @switch($activity->type->value)
                            @case('Follow') badge-blue @break
                            @case('Like') badge-pink @break
                            @case('Announce') badge-green @break
                            @case('Create') badge-purple @break
                            @default badge-gray @endswitch
                    ">{{ $activity->type->value }}</span>

                    @if (! $activity->is_incoming)
                        @switch($activity->status->value)
                            @case('pending')
                                <span class="badge badge-yellow">Pending</span>
                                @break
                            @case('delivered')
                                <span class="badge badge-green">Delivered</span>
                                @break
                            @case('failed')
                                <span class="badge badge-red">Failed</span>
                                @break
                        @endswitch
                    @endif

                    @if (isset($activity->payload['pinned']) && $activity->payload['pinned'])
                        <span class="badge badge-indigo">Pinned</span>
                    @endif
                    @if (isset($activity->payload['published']))
                        <span class="badge badge-gray">Published</span>
                    @endif

                    <div class="flex-1">
                        @if ($activity->remoteActor)
                            <div class="flex items-center gap-2 mb-1">
                                @if ($activity->remoteActor->icon_url)
                                    <img src="{{ $activity->remoteActor->icon_url }}" alt="" class="w-5 h-5 rounded-full">
                                @endif
                                <span class="text-sm">{{ $activity->remoteActor->name ?? $activity->remoteActor->username }}</span>
                            </div>
                        @endif

                        @php
                            $object = $activity->payload['object'] ?? [];
                            $objContent = is_array($object) ? ($object['content'] ?? null) : null;
                            $objId = is_string($activity->payload['object'] ?? null) ? $activity->payload['object'] : (is_array($object) ? ($object['id'] ?? null) : null);
                        @endphp

                        @if ($objContent)
                            <div class="text-sm mt-1">{{ Str::limit(strip_tags($objContent), 300) }}</div>
                        @elseif ($objId && is_string($objId))
                            <p class="text-sm text-muted mt-1 truncate">{{ $objId }}</p>
                        @endif

                        <p class="text-xs text-muted mt-1">{{ $activity->created_at->diffForHumans() }}</p>

                        @if (config('activitypub.debug_display') && $activity->debug)
                            <details class="mt-2">
                                <summary class="text-xs text-muted">Debug info</summary>
                                <pre class="mt-1 text-xs text-muted p-2 rounded overflow-auto">{{ json_encode($activity->debug, JSON_PRETTY_PRINT) }}</pre>
                            </details>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $activities->links() }}
        </div>
    @endif
@endsection
