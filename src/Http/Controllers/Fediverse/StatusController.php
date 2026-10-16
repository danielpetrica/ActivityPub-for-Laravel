<?php

namespace DanielPetrica\LaravelActivityPub\Http\Controllers\Fediverse;

use DanielPetrica\LaravelActivityPub\Jobs\DeliverActivity;
use DanielPetrica\LaravelActivityPub\Jobs\FetchRemoteActor;
use DanielPetrica\LaravelActivityPub\Jobs\ForwardPostsToNewServerJob;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\ActivityDelivery;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\Follower;
use DanielPetrica\LaravelActivityPub\Models\Following;
use DanielPetrica\LaravelActivityPub\Models\Report;
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

        return view('activitypub::fediverse.status', [
            'actor' => $user,
            'localActor' => $localActor,
            'checks' => $this->runChecks(),
            'deadLetters' => $this->failedDeliveries(),
            'reports' => $this->recentReports(),
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

        // Per-inbox delivery counts
        $checks['deliveries'] = $this->checkDeliveries();

        // Relationships
        $checks['relationships'] = $this->checkRelationships();

        // Inbound moderation reports (Flag)
        $checks['reports'] = $this->checkReports();

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

    /**
     * @return array{label: string, status: string, detail: string}
     */
    private function checkDeliveries(): array
    {
        try {
            $pending = ActivityDelivery::query()->where('status', '=', ActivityDelivery::STATUS_PENDING)->count();
            $delivered = ActivityDelivery::query()->where('status', '=', ActivityDelivery::STATUS_DELIVERED)->count();
            $failed = ActivityDelivery::query()->where('status', '=', ActivityDelivery::STATUS_FAILED)->count();

            return [
                'label' => 'Deliveries',
                'status' => $failed > 0 ? 'warning' : 'ok',
                'detail' => "{$delivered} delivered, {$pending} pending, {$failed} failed (per-inbox)",
            ];
        } catch (\Throwable $e) {
            return [
                'label' => 'Deliveries',
                'status' => 'error',
                'detail' => $e->getMessage(),
            ];
        }
    }

    /**
     * Up to 25 permanently failed per-inbox deliveries for the dead-letter view.
     *
     * @return array<int, array{id: int, activity_id: int, inbox_url: string, attempts: int, response_code: int|null, last_error: string|null, updated_at: string|null}>
     */
    private function failedDeliveries(): array
    {
        try {
            return ActivityDelivery::query()
                ->where('status', '=', ActivityDelivery::STATUS_FAILED)
                ->latest('updated_at')
                ->limit(25)
                ->get()
                ->map(static fn (ActivityDelivery $delivery): array => [
                    'id' => $delivery->id,
                    'activity_id' => $delivery->activity_id,
                    'inbox_url' => $delivery->inbox_url,
                    'attempts' => $delivery->attempts,
                    'response_code' => $delivery->response_code,
                    'last_error' => $delivery->last_error,
                    'updated_at' => $delivery->updated_at->toDateTimeString(),
                ])
                ->all();
        } catch (\Throwable) {
            return [];
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

    /**
     * @return array{label: string, status: string, detail: string}
     */
    private function checkReports(): array
    {
        try {
            $open = Report::query()->where('status', '=', Report::STATUS_OPEN)->count();
            $total = Report::count();

            return [
                'label' => 'Reports',
                'status' => $open > 0 ? 'warning' : 'ok',
                'detail' => "{$open} open, {$total} total inbound reports",
            ];
        } catch (\Throwable $e) {
            return [
                'label' => 'Reports',
                'status' => 'error',
                'detail' => $e->getMessage(),
            ];
        }
    }

    /**
     * Up to 25 most recent open moderation reports (inbound Flag activities).
     *
     * @return array<int, array{id: int, object_type: string|null, object_id: string|null, reason: string|null, reporter: string|null, created_at: string}>
     */
    private function recentReports(): array
    {
        try {
            return Report::query()
                ->with('remoteActor')
                ->where('status', '=', Report::STATUS_OPEN)
                ->latest('created_at')
                ->limit(25)
                ->get()
                ->map(static fn (Report $report): array => [
                    'id' => $report->id,
                    'object_type' => $report->object_type,
                    'object_id' => $report->object_id,
                    'reason' => $report->reason,
                    'reporter' => $report->remoteActor?->actor_url,
                    'created_at' => $report->created_at->toDateTimeString(),
                ])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public function reschedulePending(Request $request): RedirectResponse
    {
        $count = 0;

        // Retry every inbox whose previous delivery permanently failed.
        $failedDeliveries = ActivityDelivery::query()
            ->where('status', '=', ActivityDelivery::STATUS_FAILED)
            ->get();

        foreach ($failedDeliveries as $delivery) {
            $activity = Activity::query()->find($delivery->activity_id);

            if ($activity === null) {
                continue;
            }

            Bus::dispatch(new DeliverActivity(
                inboxUrl: $delivery->inbox_url,
                activityModelId: $activity->id,
                actorId: $activity->actor_id,
            ));

            $count++;
        }

        // Recover legacy / never-tracked pending activities that have no
        // per-inbox delivery rows yet.
        $pendingActivities = Activity::query()
            ->where('status', '=', 'pending')
            ->where('is_incoming', false)
            ->whereDoesntHave('deliveries')
            ->with('remoteActor')
            ->get();

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
            ->with('success', "Rescheduled {$count} deliveries.");
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
        $days = (int) config('activitypub.retention.delivered_activities_days', 30);

        $pruned = Activity::query()
            ->where('status', 'delivered')
            ->where('is_incoming', false)
            ->where('delivered_at', '<', now()->subDays($days))
            ->delete();

        return redirect()->route('fediverse.status')
            ->with('success', "Pruned {$pruned} delivered activities older than {$days} days.");
    }

    public function forwardPosts(Request $request): RedirectResponse
    {
        $localActor = $this->resolveLocalActor();

        if (! $localActor) {
            return redirect()->route('fediverse.status')->with('error', 'No local actor found.');
        }

        $models = config('activitypub.federatable_models', []);

        if (empty($models)) {
            return redirect()->route('fediverse.status')->with('error', 'No federatable models configured. Set ACTIVITYPUB_FEDERATED_MODELS in config.');
        }

        // Get all followers (one per domain)
        $followerDomains = Follower::query()
            ->where('actor_id', $localActor->id)
            ->join('remote_actors', 'followers.remote_actor_id', '=', 'remote_actors.id')
            ->select('remote_actors.id as remote_actor_id', 'remote_actors.domain')
            ->distinct('remote_actors.domain')
            ->get();

        $jobCount = 0;
        foreach ($followerDomains as $follower) {
            ForwardPostsToNewServerJob::dispatch(
                remoteActorId: $follower->remote_actor_id,
                actorId: $localActor->id,
            );
            $jobCount++;
        }

        return redirect()->route('fediverse.status')->with('success', "Dispatched post forwarding to {$jobCount} server(s).");
    }
}
