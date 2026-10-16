<?php

namespace DanielPetrica\LaravelActivityPub\Http\Controllers\Fediverse;

use DanielPetrica\LaravelActivityPub\Enums\FollowerStatus;
use DanielPetrica\LaravelActivityPub\Models\Following;
use DanielPetrica\LaravelActivityPub\Services\RemoteActorResolver;
use DanielPetrica\LaravelActivityPub\Traits\ResolvesLocalActor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

final class FollowingController extends Controller
{
    use ResolvesLocalActor;

    public function __construct(
        private RemoteActorResolver $remoteActorResolver,
    ) {}

    public function __invoke(): View
    {
        $user = auth()->user();
        $localActor = $this->resolveLocalActor();

        $following = Following::with('remoteActor')
            ->where('actor_id', $localActor->id)
            ->latest()
            ->get();

        return view('activitypub::fediverse.following', [
            'following' => $following,
            'actor' => $user,
        ]);
    }

    public function correctStatus(): RedirectResponse
    {
        $localActor = $this->resolveLocalActor();

        $pendingFollowings = Following::with('remoteActor')
            ->where('actor_id', $localActor->id)
            ->where('status', FollowerStatus::Pending)
            ->get();

        $corrected = 0;

        foreach ($pendingFollowings as $following) {
            $remoteActor = $following->remoteActor;

            if ($remoteActor === null) {
                $following->update(['status' => FollowerStatus::Accepted]);
                $corrected++;

                continue;
            }

            $actorData = $this->remoteActorResolver->fetchActorData(actorUri: $remoteActor->actor_url);

            if ($actorData !== null) {
                $manuallyApproves = $actorData['manuallyApprovesFollowers'] ?? true;

                if (! $manuallyApproves) {
                    $following->update(['status' => FollowerStatus::Accepted]);
                    $corrected++;
                }
            }
        }

        return redirect()
            ->route('fediverse.following')
            ->with('success', "Corrected {$corrected} follow(s) status.");
    }
}
