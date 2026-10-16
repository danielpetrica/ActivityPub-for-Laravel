<?php

namespace DanielPetrica\LaravelActivityPub\Http\Controllers\Fediverse;

use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\Follower;
use DanielPetrica\LaravelActivityPub\Services\ActivityPubService;
use DanielPetrica\LaravelActivityPub\Traits\ResolvesLocalActor;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class FollowersController extends Controller
{
    use ResolvesLocalActor;

    public function __construct(
        private ActivityPubService $activityPubService,
    ) {}

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

        $pendingCount = Follower::query()
            ->where('actor_id', $localActor->id)
            ->where('status', 'pending')
            ->count();

        return view('activitypub::fediverse.followers', [
            'followers' => $followers,
            'actor' => $user,
            'domain' => $domain,
            'status' => $status,
            'pendingCount' => $pendingCount,
        ]);
    }

    /**
     * Approve a pending follower: sends the Accept wrapping the original
     * Follow and advances the follower to Accepted.
     */
    public function approve(Request $request, Follower $follower): RedirectResponse
    {
        $owner = $this->authorizeFollower(request: $request, follower: $follower);

        $approved = $this->activityPubService->approveFollower(
            actor: $owner,
            follower: $follower,
        );

        return redirect()
            ->route('fediverse.followers')
            ->with(
                $approved ? 'success' : 'error',
                $approved ? 'Follower approved.' : 'Could not approve that follower.',
            );
    }

    /**
     * Reject a pending follower: sends a Reject wrapping the original Follow
     * and removes the follower row.
     */
    public function reject(Request $request, Follower $follower): RedirectResponse
    {
        $owner = $this->authorizeFollower(request: $request, follower: $follower);

        $rejected = $this->activityPubService->rejectFollower(
            actor: $owner,
            follower: $follower,
        );

        return redirect()
            ->route('fediverse.followers')
            ->with(
                $rejected ? 'success' : 'error',
                $rejected ? 'Follower rejected.' : 'Could not reject that follower.',
            );
    }

    /**
     * Authorize acting on a follower and return the local actor that owns it.
     *
     * The authenticated user may only approve/reject followers of their own
     * actor (a follower owned by a different actor is forbidden, not merely
     * hidden). When the configured admin ability applies, an admin may act
     * instance-wide; the action is still performed as the actor that owns the
     * follower so the correct local actor signs the Accept/Reject.
     */
    protected function authorizeFollower(Request $request, Follower $follower): Actor
    {
        $localActor = $this->resolveLocalActor();

        if ($follower->actor_id === $localActor->id) {
            return $localActor;
        }

        if ($this->isAdmin(user: $request->user())) {
            $owner = Actor::query()->find($follower->actor_id);

            if ($owner !== null) {
                return $owner;
            }
        }

        abort(403, 'You may only manage followers of your own actor.');
    }

    protected function isAdmin(?Authenticatable $user): bool
    {
        if ($user === null) {
            return false;
        }

        $ability = config('activitypub.fediverse.admin_gate');

        if (! is_string($ability) || $ability === '') {
            $ability = 'activitypub.fediverse.admin';
        }

        return Gate::forUser($user)->allows($ability);
    }
}
