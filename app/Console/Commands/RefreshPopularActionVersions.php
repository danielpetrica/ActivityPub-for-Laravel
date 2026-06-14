<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mcp\Cache\ActionCache;
use App\Mcp\Services\GitHubActionsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class RefreshPopularActionVersions extends Command
{
    protected $signature = 'app:refresh-popular-action-versions
                            {--category= : Specific category to refresh (ci, deployment, security, utility, docker, node)}';

    protected $description = 'Refresh the cached versions of all popular GitHub Actions.';

    private array $categories = ['ci', 'deployment', 'security', 'utility', 'docker', 'node'];

    public function handle(GitHubActionsService $service): int
    {
        $category = $this->option('category');

        if ($category !== null) {
            if (! in_array($category, $this->categories, true)) {
                $this->error("Invalid category: '{$category}'. Valid: ".implode(', ', $this->categories));

                return self::FAILURE;
            }
            $this->refreshCategory($service, $category);
        } else {
            $this->info('Refreshing all categories...');
            foreach ($this->categories as $cat) {
                $this->refreshCategory($service, $cat);
            }
        }

        // Also refresh "all" combined category
        $this->refreshAll($service);

        $this->info('Popular action versions refreshed successfully.');

        return self::SUCCESS;
    }

    private function refreshCategory(GitHubActionsService $service, string $category): void
    {
        $this->info("Refreshing category: {$category}");

        $actionNames = $service->getPopularActionNames($category);
        $this->info('  Found '.count($actionNames).' actions in config.');

        $this->withProgressBar($actionNames, function (string $actionName) use ($service): void {
            try {
                [$owner, $repo] = explode('/', $actionName);
                $service->fetchAction($owner, $repo);
                $this->line("  ✓ {$actionName}");
            } catch (\Throwable $e) {
                $errorCode = method_exists($e, 'getErrorCode') ? $e->getErrorCode() : 'unknown';
                Log::warning('RefreshPopularActionVersions: failed', [
                    'action' => $actionName,
                    'error_code' => $errorCode,
                    'message' => $e->getMessage(),
                ]);
                $this->line("  ✗ {$actionName} ({$errorCode})");
            }
        });

        // After fetching all, build and cache the popular data for this category
        try {
            $data = $service->fetchPopularActions($category, count($actionNames));
            ActionCache::putPopular($category, $data);
        } catch (\Throwable $e) {
            Log::warning('RefreshPopularActionVersions: failed to build popular cache', [
                'category' => $category,
                'message' => $e->getMessage(),
            ]);
        }

        $this->newLine();
    }

    private function refreshAll(GitHubActionsService $service): void
    {
        $this->info('Refreshing combined "all" category...');

        try {
            $data = $service->fetchPopularActions('all', 50);
            ActionCache::putPopular('all', $data);
            $this->info('  ✓ "all" category refreshed with '.count($data['actions']).' actions.');
        } catch (\Throwable $e) {
            Log::warning('RefreshPopularActionVersions: failed to build "all" cache', [
                'message' => $e->getMessage(),
            ]);
            $this->warn('  Could not refresh "all" category.');
        }

        $this->newLine();
    }
}
