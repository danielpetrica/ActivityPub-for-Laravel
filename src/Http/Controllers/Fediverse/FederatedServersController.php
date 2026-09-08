<?php

namespace DanielPetrica\LaravelActivityPub\Http\Controllers\Fediverse;

use DanielPetrica\LaravelActivityPub\Models\Follower;
use DanielPetrica\LaravelActivityPub\Traits\ResolvesLocalActor;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class FederatedServersController extends Controller
{
    use ResolvesLocalActor;

    public function __invoke(): View
    {
        $user = auth()->user();
        $localActor = $this->resolveLocalActor();

        $servers = Follower::query()
            ->where('actor_id', $localActor->id)
            ->join('remote_actors', 'followers.remote_actor_id', '=', 'remote_actors.id')
            ->select('remote_actors.domain', DB::raw('count(*) as follower_count'))
            ->groupBy('remote_actors.domain')
            ->orderByDesc('follower_count')
            ->get();

        return view(view: 'activitypub::fediverse.servers', data: [
            'servers' => $servers,
            'actor' => $user,
        ]);
    }
}
