<?php

namespace DanielPetrica\LaravelActivityPub\Http\Controllers\Fediverse;

use DanielPetrica\LaravelActivityPub\Enums\ActivityType;
use DanielPetrica\LaravelActivityPub\Enums\FollowerStatus;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Follower;
use DanielPetrica\LaravelActivityPub\Traits\ResolvesLocalActor;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    use ResolvesLocalActor;

    public function __invoke(): View
    {
        $user = auth()->user();
        $localActor = $this->resolveLocalActor();

        $followerCount = Follower::query()
            ->where('actor_id', '=', $localActor->id)
            ->where('status', '=', FollowerStatus::Accepted)
            ->count();

        $followingCount = Activity::query()
            ->where('actor_id', '=', $localActor->id)
            ->where('type', '=', ActivityType::Follow)
            ->where('is_incoming', '=', false)
            ->distinct()
            ->count('remote_actor_id');

        $incomingCount = Activity::query()
            ->where('actor_id', '=', $localActor->id)
            ->where('is_incoming', '=', true)
            ->count();

        $outgoingCount = Activity::query()
            ->where('actor_id', '=', $localActor->id)
            ->where('is_incoming', '=', false)
            ->count();

        $recentInbox = Activity::query()
            ->with('remoteActor')
            ->where('actor_id', '=', $localActor->id)
            ->where('is_incoming', '=', true)
            ->latest()
            ->limit(5)
            ->get();

        $recentOutbox = Activity::query()
            ->with('remoteActor')
            ->where('actor_id', '=', $localActor->id)
            ->where('is_incoming', '=', false)
            ->latest()
            ->limit(5)
            ->get();

        $parsedUrl = parse_url($localActor->actor_id);

        return view('activitypub::fediverse.dashboard', [
            'followerCount' => $followerCount,
            'followingCount' => $followingCount,
            'incomingCount' => $incomingCount,
            'outgoingCount' => $outgoingCount,
            'recentInbox' => $recentInbox,
            'recentOutbox' => $recentOutbox,
            'actor' => $user,
            'localActor' => $localActor,
            'actorDomain' => $parsedUrl['host'].(isset($parsedUrl['port']) ? ':'.$parsedUrl['port'] : ''),
        ]);
    }
}
