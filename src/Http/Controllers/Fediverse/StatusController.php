<?php

namespace DanielPetrica\LaravelActivityPub\Http\Controllers\Fediverse;

use DanielPetrica\LaravelActivityPub\Jobs\DeliverActivity;
use DanielPetrica\LaravelActivityPub\Jobs\FetchRemoteActor;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\Follower;
use DanielPetrica\LaravelActivityPub\Models\Following;
use DanielPetrica\LaravelActivityPub\Traits\ResolvesLocalActor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\View\View;

final class StatusController extends Controller
{
    use ResolvesLocalActor;

    public function __invoke(Request $request): View
    {
        $user = auth()->user();
        $localActor = $this->resolveLocalActor();

        return view(view: 'activitypub::fediverse.status', data: [
            'actor' => $user,
            'localActor' => $localActor,
            'checks' => $this->runChecks(),
        ]);
    }

    /**
     * @return array<string, array{label: string, status: string, detail: string}>
     */
    private function runChecks(): array
    {
        $checks = [];

        // Database
        $checks['database'] = $this->checkDatabase();

        // Queue
        $checks['queue'] = $this->checkQueue();

        // Federation config
        $checks['federation'] = $this->checkFederation();

        // HTTP signatures
        $checks['http_signatures'] = $this->checkHttpSignatures();

        // Activity counts
        $checks['activities'] = $this->checkActivities();

        // Relationships
        $checks['relationships'] = $this->checkRelationships();

        return $checks;
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return [
                'label' => 'Database',
                'status' => 'ok',
                'detail' => 'Connected ('.config('database.default').')',
            ];
        } catch (\Throwable $e) {
            return [
                'label' => 'Database',
                'status' => 'error',
                'detail' => $e->getMessage(),
            ];
        }
    }

    private function checkQueue(): array
    {
        $connection = config('activitypub.queue.connection') ?? config('queue.default');
        $queueName = config('activitypub.queue.queue', 'default');

        try {
            $pendingJobs = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();

            $detail = "Connection: {$connection}, Queue: {$queueName}";

            if ($pendingJobs > 0) {
                $detail .= ", Pending: {$pendingJobs}";
            }

            if ($failedJobs > 0) {
                $detail .= ", Failed: {$failedJobs}";
            }

            $status = $failedJobs > 0 ? 'warning' : 'ok';

            return [
                'label' => 'Queue',
                'status' => $status,
                'detail' => $detail,
            ];
        } catch (\Throwable $e) {
            return [
                'label' => 'Queue',
                'status' => 'error',
                'detail' => "Connection: {$connection}, Error: {$e->getMessage()}",
            ];
        }
    }

    private function checkFederation(): array
    {
        $enabled = config('activitypub.federation.enabled');
        $domain = config('activitypub.domain');

        return [
            'label' => 'Federation',
            'status' => $enabled ? 'ok' : 'warning',
            'detail' => $enabled ? "Enabled on {$domain}" : "Disabled (ACTIVITYPUB_FEDERATION_ENABLED=false) on {$domain}",
        ];
    }

    private function checkHttpSignatures(): array
    {
        $enabled = config('activitypub.http_signatures.enabled');

        return [
            'label' => 'HTTP Signatures',
            'status' => $enabled ? 'ok' : 'warning',
            'detail' => $enabled ? 'Enabled' : 'Disabled (incoming requests are not verified)',
        ];
    }

    private function checkActivities(): array
    {
        try {
            $total = Activity::count();
            $pending = Activity::where('status', 'pending')->where('is_incoming', false)->count();
            $failed = Activity::where('status', 'failed')->count();
            $delivered = Activity::where('status', 'delivered')->count();

            $status = $failed > 0 ? 'warning' : ($pending > 10 ? 'warning' : 'ok');

            return [
                'label' => 'Activities',
                'status' => $status,
                'detail' => "{$total} total, {$delivered} delivered, {$pending} pending, {$failed} failed",
            ];
        } catch (\Throwable $e) {
            return [
                'label' => 'Activities',
                'status' => 'error',
                'detail' => $e->getMessage(),
            ];
        }
    }

    private function checkRelationships(): array
    {
        try {
            $followers = Follower::count();
            $following = Following::count();

            return [
                'label' => 'Relationships',
                'status' => 'ok',
                'detail' => "{$followers} followers, {$following} following",
            ];
        } catch (\Throwable $e) {
            return [
                'label' => 'Relationships',
                'status' => 'error',
                'detail' => $e->getMessage(),
            ];
        }
    }

    public function reschedulePending(Request $request): RedirectResponse
    {
        $pendingActivities = Activity::query()
            ->where('status', 'pending')
            ->where('is_incoming', false)
            ->get();

        $count = 0;

        foreach ($pendingActivities as $activity) {
            $remoteActor = $activity->remoteActor;

            if ($remoteActor && $activity->actor) {
                Bus::dispatch(new DeliverActivity(
                    inboxUrl: $remoteActor->inbox_url,
                    activityModelId: $activity->id,
                    actorId: $activity->actor_id,
                ));

                $count++;
            }
        }

        return redirect()->route('fediverse.status')
            ->with('success', "Rescheduled {$count} pending activities for delivery.");
    }

    public function refreshFollowedAccounts(Request $request): RedirectResponse
    {
        $acceptedFollowings = Following::query()
            ->where('status', 'accepted')
            ->with('remoteActor')
            ->get();

        $refreshedUrls = [];

        foreach ($acceptedFollowings as $following) {
            $remoteActor = $following->remoteActor;

            if ($remoteActor && ! in_array($remoteActor->actor_url, $refreshedUrls)) {
                Bus::dispatch(new FetchRemoteActor(
                    actorUri: $remoteActor->actor_url,
                ));

                $refreshedUrls[] = $remoteActor->actor_url;
            }
        }

        $count = count($refreshedUrls);

        return redirect()->route('fediverse.status')
            ->with('success', "Dispatched refresh for {$count} followed accounts.");
    }

    public function pruneOldActivities(Request $request): RedirectResponse
    {
        $pruned = Activity::query()
            ->where('status', 'delivered')
            ->where('is_incoming', false)
            ->where('delivered_at', '<', now()->subDays(30))
            ->delete();

        return redirect()->route('fediverse.status')
            ->with('success', "Pruned {$pruned} delivered activities older than 30 days.");
    }
}
