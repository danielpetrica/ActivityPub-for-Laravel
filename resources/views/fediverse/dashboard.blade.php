@extends('activitypub::fediverse.layout')

@section('content')
    <h2 class="h2 mb-6">Dashboard</h2>

    <div class="card p-5 mb-6">
        <div class="flex items-center gap-5">
            <div>
                @if ($localActor->icon_url)
                    <img src="{{ $localActor->icon_url }}" alt="" style="width:4rem;height:4rem;border-radius:9999px;">
                @else
                    <div style="width:4rem;height:4rem;border-radius:9999px;background:var(--color-indigo-light);display:flex;align-items:center;justify-content:center;">
                        <span class="h2" style="color:var(--color-indigo);">{{ strtoupper(substr($localActor->name ?? $localActor->username, 0, 1)) }}</span>
                    </div>
                @endif
            </div>

            <div class="flex-1">
                <h3 class="h3">{{ $localActor->name ?? $localActor->username }}</h3>
                <p class="text-sm text-muted">{{ $localActor->username.'@'.$actorDomain }}</p>
                <p class="text-xs text-muted mt-1">
                    <a href="{{ $localActor->actor_id }}" target="_blank">{{ $localActor->actor_id }}</a>
                </p>
            </div>
        </div>
    </div>

    <div class="grid grid-4 gap-6 mb-8">
        <div class="card p-5">
            <p class="text-sm text-muted">Followers</p>
            <p class="h2 mt-1">{{ number_format($followerCount) }}</p>
        </div>

        <div class="card p-5">
            <p class="text-sm text-muted">Following</p>
            <p class="h2 mt-1">{{ number_format($followingCount) }}</p>
        </div>

        <div class="card p-5">
            <p class="text-sm text-muted">Incoming</p>
            <p class="h2 mt-1">{{ number_format($incomingCount) }}</p>
        </div>

        <div class="card p-5">
            <p class="text-sm text-muted">Outgoing</p>
            <p class="h2 mt-1">{{ number_format($outgoingCount) }}</p>
        </div>
    </div>

    <div class="grid grid-2 gap-6">
        <div class="card">
            <div class="card-header">
                <h3 class="h5">Recent Inbox</h3>
            </div>

            @if ($recentInbox->isEmpty())
                <div class="card-body text-sm text-muted">No incoming activities yet.</div>
            @else
                <div>
                    @foreach ($recentInbox as $activity)
                        <div class="px-5 py-3 flex items-center gap-3">
                            <span class="badge
                                @switch($activity->type->value)
                                    @case('Follow') badge-blue @break
                                    @case('Like') badge-pink @break
                                    @case('Announce') badge-green @break
                                    @case('Create') badge-purple @break
                                    @default badge-gray @endswitch
                            ">{{ $activity->type->value }}</span>
                            <div class="flex-1">
                                @if ($activity->remoteActor)
                                    <p class="text-sm truncate">{{ $activity->remoteActor->name ?? $activity->remoteActor->username }}</p>
                                @endif
                                <p class="text-xs text-muted">{{ $activity->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="px-5 py-3 border">
                    <a href="{{ route('fediverse.inbox') }}" class="text-sm">View all &rarr;</a>
                </div>
            @endif
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="h5">Recent Outbox</h3>
            </div>

            @if ($recentOutbox->isEmpty())
                <div class="card-body text-sm text-muted">No outgoing activities yet.</div>
            @else
                <div>
                    @foreach ($recentOutbox as $activity)
                        <div class="px-5 py-3 flex items-center gap-3">
                            <span class="badge
                                @switch($activity->type->value)
                                    @case('Follow') badge-blue @break
                                    @case('Like') badge-pink @break
                                    @case('Announce') badge-green @break
                                    @case('Create') badge-purple @break
                                    @default badge-gray @endswitch
                            ">{{ $activity->type->value }}</span>
                            @if ($activity->status->value === 'pending')
                                <span class="badge badge-yellow">Pending</span>
                            @endif
                            @if ($activity->status->value === 'failed')
                                <span class="badge badge-red">Failed</span>
                            @endif
                            @if (isset($activity->payload['pinned']) && $activity->payload['pinned'])
                                <span class="badge badge-indigo">Pinned</span>
                            @endif
                            @if (isset($activity->payload['published']))
                                <span class="badge badge-gray">Published</span>
                            @endif
                            <div class="flex-1">
                                @if ($activity->remoteActor)
                                    <p class="text-sm truncate">{{ $activity->remoteActor->name ?? $activity->remoteActor->username }}</p>
                                @endif
                                <p class="text-xs text-muted">{{ $activity->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="px-5 py-3 border">
                    <a href="{{ route('fediverse.outbox') }}" class="text-sm">View all &rarr;</a>
                </div>
            @endif
        </div>
    </div>
@endsection
