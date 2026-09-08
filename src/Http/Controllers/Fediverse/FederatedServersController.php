<?php

namespace DanielPetrica\LaravelActivityPub\Http\Controllers\Fediverse;

use DanielPetrica\LaravelActivityPub\Models\BlockedDomain;
use DanielPetrica\LaravelActivityPub\Models\BlockedRemoteActor;
use DanielPetrica\LaravelActivityPub\Models\Follower;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use DanielPetrica\LaravelActivityPub\Traits\ResolvesLocalActor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $blockedDomains = BlockedDomain::pluck('domain')->toArray();

        return view('activitypub::fediverse.servers', [
            'servers' => $servers,
            'actor' => $user,
            'blockedDomains' => $blockedDomains,
        ]);
    }

    public function blockDomain(Request $request): RedirectResponse
    {
        $request->validate(['domain' => 'required|string']);

        $localActor = $this->resolveLocalActor();
        $domain = $request->input('domain');

        BlockedDomain::updateOrCreate(
            ['domain' => $domain],
            ['reason' => 'Blocked via Fediverse dashboard']
        );

        // Remove all followers from this domain
        Follower::where('actor_id', $localActor->id)
            ->whereHas('remoteActor', fn ($q) => $q->where('domain', $domain))
            ->delete();

        return redirect()->route('fediverse.servers')->with('success', "Blocked domain: {$domain}");
    }

    public function unblockDomain(Request $request): RedirectResponse
    {
        $request->validate(['domain' => 'required|string']);

        BlockedDomain::where('domain', $request->input('domain'))->delete();

        return redirect()->route('fediverse.servers')->with('success', 'Domain unblocked.');
    }

    public function blockActor(Request $request): RedirectResponse
    {
        $request->validate(['remote_actor_id' => 'required|integer']);

        $remoteActor = RemoteActor::find($request->input('remote_actor_id'));

        if ($remoteActor) {
            BlockedRemoteActor::updateOrCreate(
                ['remote_actor_id' => $remoteActor->id],
                ['reason' => 'Blocked via Fediverse dashboard']
            );

            // Remove follower relationship
            Follower::where('remote_actor_id', $remoteActor->id)->delete();
        }

        return redirect()->route('fediverse.followers')->with('success', 'User blocked.');
    }

    public function unblockActor(Request $request): RedirectResponse
    {
        $request->validate(['remote_actor_id' => 'required|integer']);

        BlockedRemoteActor::where('remote_actor_id', $request->input('remote_actor_id'))->delete();

        return redirect()->route('fediverse.followers')->with('success', 'User unblocked.');
    }
}
