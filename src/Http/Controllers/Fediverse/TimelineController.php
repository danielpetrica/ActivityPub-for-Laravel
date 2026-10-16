<?php

namespace DanielPetrica\LaravelActivityPub\Http\Controllers\Fediverse;

use DanielPetrica\LaravelActivityPub\Enums\FollowerStatus;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Follower;
use DanielPetrica\LaravelActivityPub\Traits\ResolvesLocalActor;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

final class TimelineController extends Controller
{
    use ResolvesLocalActor;

    public function __invoke(): View
    {
        $user = auth()->user();
        $localActor = $this->resolveLocalActor();

        $followedActorIds = Follower::query()
            ->select('remote_actor_id')
            ->where('actor_id', '=', $localActor->id)
            ->where('status', '=', FollowerStatus::Accepted);

        $activities = Activity::query()
            ->with(['remoteActor', 'actor'])
            ->whereIn('remote_actor_id', $followedActorIds)
            ->where('is_incoming', '=', true)
            ->whereIn('type', ['Create', 'Announce'])
            ->latest()
            ->paginate(20);

        return view('activitypub::fediverse.timeline', [
            'activities' => $activities,
            'actor' => $user,
        ]);
    }
}
