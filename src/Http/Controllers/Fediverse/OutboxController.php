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
            ->where(column: 'actor_id', operator: '=', value: $localActor->id)
            ->where(column: 'is_incoming', operator: '=', value: false)
            ->latest()
            ->paginate(perPage: 20);

        return view(view: 'activitypub::fediverse.outbox', data: [
            'activities' => $activities,
            'actor' => $user,
        ]);
    }
}
