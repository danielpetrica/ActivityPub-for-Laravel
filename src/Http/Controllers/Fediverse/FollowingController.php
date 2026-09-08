<?php

namespace DanielPetrica\LaravelActivityPub\Http\Controllers\Fediverse;

use DanielPetrica\LaravelActivityPub\Models\Following;
use DanielPetrica\LaravelActivityPub\Traits\ResolvesLocalActor;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

final class FollowingController extends Controller
{
    use ResolvesLocalActor;

    public function __invoke(): View
    {
        $user = auth()->user();
        $localActor = $this->resolveLocalActor();

        $following = Following::with('remoteActor')
            ->where('actor_id', $localActor->id)
            ->latest()
            ->get();

        return view(view: 'activitypub::fediverse.following', data: [
            'following' => $following,
            'actor' => $user,
        ]);
    }
}
