@extends('activitypub::fediverse.layout')

@section('content')
    <h2 class="h2 mb-6">Inbox</h2>

    @if ($activities->isEmpty() && ($rejectedLogs === null || $rejectedLogs->isEmpty()))
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

    {{-- Rejected requests (debug mode only) --}}
    @if (config('activitypub.debug_display') && $rejectedLogs !== null && ! $rejectedLogs->isEmpty())
        <h2 class="h2 mt-8 mb-4">Rejected Requests</h2>
        <div class="card">
            @foreach ($rejectedLogs as $log)
                <div class="p-4 flex items-start gap-4 border-bottom">
                    <span class="badge badge-red">{{ $log->status_code }}</span>

                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            @if ($log->actor_url)
                                <span class="text-sm">{{ $log->actor_url }}</span>
                            @else
                                <span class="text-sm text-muted">Unknown actor</span>
                            @endif

                            @if ($log->key_id)
                                <span class="text-xs text-muted truncate" title="{{ $log->key_id }}">{{ Str::afterLast($log->key_id, '/') }}</span>
                            @endif
                        </div>

                        <p class="text-sm font-semibold text-red-700 mt-1">{{ $log->reason }}</p>

                        @if ($log->headers)
                            <details class="mt-2">
                                <summary class="text-xs text-muted">Request details</summary>
                                <div class="mt-1 text-xs text-muted">
                                    @if ($log->ip)
                                        <p>IP: {{ $log->ip }}</p>
                                    @endif
                                    @if ($log->method && $log->path)
                                        <p>{{ $log->method }} /{{ $log->path }}</p>
                                    @endif
                                    @foreach ($log->headers as $key => $value)
                                        <p>{{ $key }}: {{ Str::limit($value, 80) }}</p>
                                    @endforeach
                                    @if ($log->body_summary)
                                        <pre class="mt-1 p-2 rounded overflow-auto max-h-32">{{ Str::limit($log->body_summary, 500) }}</pre>
                                    @endif
                                </div>
                            </details>
                        @endif

                        <p class="text-xs text-muted mt-1">{{ $log->created_at->diffForHumans() }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $rejectedLogs->links() }}
        </div>
    @endif
@endsection
