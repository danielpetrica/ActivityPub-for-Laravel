<?php

namespace DanielPetrica\LaravelActivityPub\Console\Commands;

use DanielPetrica\LaravelActivityPub\Jobs\PruneOldActivities;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\InboxLog;
use DanielPetrica\LaravelActivityPub\Models\Interaction;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

final class PruneActivitiesCommand extends Command
{
    protected $signature = 'activitypub:prune-activities
        {--days= : Days to keep delivered activities (defaults to the configured retention)}
        {--received-days= : Days to keep received (incoming) activities}
        {--inbox-logs-days= : Days to keep rejected-request audit rows}
        {--interactions-days= : Days to keep interactions}
        {--dry-run : Report what would be pruned without deleting anything}';

    protected $description = 'Prune old ActivityPub data: delivered and received activities, the rejected-request audit log and stale interactions.';

    public function handle(): int
    {
        $deliveredDays = $this->retentionDays(option: 'days', configKey: 'delivered_activities_days', default: 30);
        $receivedDays = $this->retentionDays(option: 'received-days', configKey: 'received_activities_days', default: 30);
        $inboxLogDays = $this->retentionDays(option: 'inbox-logs-days', configKey: 'inbox_logs_days', default: 30);
        $interactionDays = $this->retentionDays(option: 'interactions-days', configKey: 'interactions_days', default: 30);

        if ((bool) $this->option('dry-run')) {
            $this->line(sprintf(
                'Would prune %d delivered activities, %d received activities, %d inbox logs and %d interactions.',
                $this->deliveredActivitiesQuery($deliveredDays)->count(),
                $this->receivedActivitiesQuery($receivedDays)->count(),
                $this->inboxLogsQuery($inboxLogDays)->count(),
                $this->interactionsQuery($interactionDays)->count(),
            ));
            $this->info('Dry run: nothing was deleted.');

            return self::SUCCESS;
        }

        // Delivered outgoing activities keep using the existing queued job so
        // large backlogs do not block the console worker.
        PruneOldActivities::dispatch(daysOld: $deliveredDays);

        $received = $this->receivedActivitiesQuery($receivedDays)->delete();
        $inboxLogs = $this->inboxLogsQuery($inboxLogDays)->delete();
        $interactions = $this->interactionsQuery($interactionDays)->delete();

        $this->info("Queued pruning of delivered activities older than {$deliveredDays} days.");
        $this->report(label: 'received activities', days: $receivedDays, count: $received);
        $this->report(label: 'inbox logs', days: $inboxLogDays, count: $inboxLogs);
        $this->report(label: 'interactions', days: $interactionDays, count: $interactions);

        return self::SUCCESS;
    }

    /**
     * Resolve a retention window from the CLI option, falling back to config
     * and finally to the built-in default. Negative values are clamped to 0 so
     * a typo can never mean "delete everything".
     */
    protected function retentionDays(string $option, string $configKey, int $default): int
    {
        $value = $this->option($option);

        if ($value === null || $value === '') {
            $value = config("activitypub.retention.{$configKey}", $default);
        }

        return max((int) $value, 0);
    }

    /**
     * @return Builder<Activity>
     */
    protected function deliveredActivitiesQuery(int $days): Builder
    {
        return Activity::query()
            ->where('status', '=', 'delivered')
            ->where('is_incoming', '=', false)
            ->where('created_at', '<', now()->subDays($days));
    }

    /**
     * @return Builder<Activity>
     */
    protected function receivedActivitiesQuery(int $days): Builder
    {
        return Activity::query()
            ->where('is_incoming', '=', true)
            ->where('created_at', '<', now()->subDays($days));
    }

    /**
     * @return Builder<InboxLog>
     */
    protected function inboxLogsQuery(int $days): Builder
    {
        return InboxLog::query()
            ->where('created_at', '<', now()->subDays($days));
    }

    /**
     * @return Builder<Interaction>
     */
    protected function interactionsQuery(int $days): Builder
    {
        return Interaction::query()
            ->where('created_at', '<', now()->subDays($days));
    }

    protected function report(string $label, int $days, int $count): void
    {
        $this->line("Pruned {$count} {$label} older than {$days} days.");
    }
}
