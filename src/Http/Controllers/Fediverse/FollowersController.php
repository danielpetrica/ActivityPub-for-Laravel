<?php

namespace DanielPetrica\LaravelActivityPub\Http\Controllers\Fediverse;

use DanielPetrica\LaravelActivityPub\Models\Follower;
use DanielPetrica\LaravelActivityPub\Traits\ResolvesLocalActor;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

final class FollowersController extends Controller
{
    use ResolvesLocalActor;

    public function __invoke(Request $request): View
    {
        $user = auth()->user();
        $localActor = $this->resolveLocalActor();

        $query = Follower::with('remoteActor')
            ->where('actor_id', $localActor->id);

        $domain = $request->query('domain');
        $status = $request->query('status');

        if ($domain) {
            $query->whereHas('remoteActor', function ($q) use ($domain) {
                $q->where('domain', $domain);
            });
        }

        if ($status && in_array($status, ['pending', 'accepted'])) {
            $query->where('status', $status);
        }

        $followers = $query->latest()->get();

        return view('activitypub::fediverse.followers', [
            'followers' => $followers,
            'actor' => $user,
            'domain' => $domain,
            'status' => $status,
        ]);
    }
}
