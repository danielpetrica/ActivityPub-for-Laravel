<?php

namespace DanielPetrica\LaravelActivityPub\Http\Controllers\Fediverse;

use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Traits\ResolvesLocalActor;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

final class OutboxController extends Controller
{
    use ResolvesLocalActor;

    public function __invoke(): View
    {
        $user = auth()->user();
        $localActor = $this->resolveLocalActor();

        $activities = Activity::query()
            ->with(['remoteActor', 'actor'])
            ->where('actor_id', '=', $localActor->id)
            ->where('is_incoming', '=', false)
            ->latest()
            ->paginate(20);

        return view('activitypub::fediverse.outbox', [
            'activities' => $activities,
            'actor' => $user,
        ]);
    }
}
