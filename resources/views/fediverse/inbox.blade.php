@extends('activitypub::fediverse.layout')

@section('content')
    <h2 class="h2 mb-6">Inbox</h2>

    @if ($activities->isEmpty())
        <div class="card p-8 text-center">
            <p class="text-muted">No incoming activities yet. When someone follows you or interacts with your content, it will appear here.</p>
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
                            @case('Delete') badge-red @break
                            @case('Update') badge-yellow @break
                            @default badge-gray @endswitch
                    ">{{ $activity->type->value }}</span>

                    <div class="flex-1">
                        @if ($activity->remoteActor)
                            <div class="flex items-center gap-2 mb-1">
                                @if ($activity->remoteActor->icon_url)
                                    <img src="{{ $activity->remoteActor->icon_url }}" alt="" class="w-6 h-6 rounded-full">
                                @endif
                                <a href="{{ $activity->remoteActor->actor_url }}" target="_blank" rel="noopener noreferrer" class="text-sm truncate">
                                    {{ $activity->remoteActor->name ?? $activity->remoteActor->username }}
                                </a>
                                <span class="text-xs text-muted">{{ $activity->remoteActor->username }}@ {{ $activity->remoteActor->domain }}</span>
                            </div>
                        @endif

                        @php
                            $object = $activity->payload['object'] ?? [];
                            $objContent = is_array($object) ? ($object['content'] ?? null) : null;
                            $objName = is_array($object) ? ($object['name'] ?? null) : null;
                        @endphp

                        @if ($objContent)
                            <div class="text-sm mt-1 post-content">{!! \DanielPetrica\LaravelActivityPub\Helpers\ActivityPubHelper::sanitizeContent($objContent) !!}</div>
                        @elseif ($objName)
                            <p class="text-sm mt-1">"{{ Str::limit($objName, 100) }}"</p>
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
