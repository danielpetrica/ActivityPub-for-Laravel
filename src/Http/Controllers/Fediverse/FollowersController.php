<?php

namespace DanielPetrica\LaravelActivityPub\Http\Controllers\Fediverse;

use DanielPetrica\LaravelActivityPub\Enums\FollowerStatus;
use DanielPetrica\LaravelActivityPub\Models\Follower;
use DanielPetrica\LaravelActivityPub\Traits\ResolvesLocalActor;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

final class FollowersController extends Controller
{
    use ResolvesLocalActor;

    public function __invoke(): View
    {
        $user = auth()->user();
        $localActor = $this->resolveLocalActor();

        $followers = Follower::with('remoteActor')
            ->where('actor_id', $localActor->id)
            ->where('status', FollowerStatus::Accepted)
            ->latest()
            ->get();

        return view(view: 'activitypub::fediverse.followers', data: [
            'followers' => $followers,
            'actor' => $user,
        ]);
    }
}
