<?php

namespace DanielPetrica\LaravelActivityPub\Http\Controllers\Fediverse;

use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\InboxLog;
use DanielPetrica\LaravelActivityPub\Traits\ResolvesLocalActor;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

final class InboxController extends Controller
{
    use ResolvesLocalActor;

    public function __invoke(): View
    {
        $user = auth()->user();
        $localActor = $this->resolveLocalActor();

        $activities = Activity::query()
            ->with(['remoteActor', 'actor'])
            ->where('actor_id', '=', $localActor->id)
            ->where('is_incoming', '=', true)
            ->latest()
            ->paginate(20);

        // The audit trail is always available to operators, independent of
        // debug mode. A distinct page name keeps it from clashing with the
        // activity paginator's `page` parameter.
        $rejectedLogs = InboxLog::query()
            ->with('actor')
            ->latest()
            ->paginate(20, ['*'], 'log_page');

        return view('activitypub::fediverse.inbox', [
            'activities' => $activities,
            'rejectedLogs' => $rejectedLogs,
            'actor' => $user,
        ]);
    }
}
