<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ImportGhostCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-ghost {path?} {--ghost-base-url=https://danielpetrica.com} {--dry}';

    protected $description = 'Import content from Ghost export JSON file';

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! $path) {
            $path = $this->findLatestGhostExport();
        }

        if (! $path || ! file_exists($path)) {
            $this->error(string: 'No Ghost export file found'.($path ? " at {$path}" : ' in storage/app/'));

            return Command::FAILURE;
        }

        $ghostBaseUrl = $this->option('ghost-base-url');
        $dryRun = $this->option('dry');

        $this->info(string: "Starting Ghost import from {$path}".($dryRun ? ' (DRY RUN)' : ''));

        try {
            $importer = new \App\Classes\Business\Import\Ghost\GhostImportBusiness(
                ghostBaseUrl: $ghostBaseUrl,
                dryRun: $dryRun
            );

            $report = $importer->run(jsonPath: $path);

            $this->table(
                headers: ['Entity', 'Created', 'Updated', 'Skipped'],
                rows: [
                    ['Tags', $report['tags']['created'], $report['tags']['updated'], $report['tags']['skipped']],
                    ['Posts', $report['posts']['created'], $report['posts']['updated'], $report['posts']['skipped']],
                    ['Pages', $report['pages']['created'], $report['pages']['updated'], $report['pages']['skipped']],
                    ['Redirects', $report['redirects']['created'] ?? 0, '-', '-'],
                ]
            );

            if (! empty($report['warnings'])) {
                $this->warn(string: "\nWarnings:");
                foreach ($report['warnings'] as $warning) {
                    $this->line(string: "- {$warning}");
                }
            }

            if (! empty($report['errors'])) {
                $this->error(string: "\nErrors:");
                foreach ($report['errors'] as $error) {
                    $this->line(string: "- {$error}");
                }
            }

            $this->info(string: "\nImport completed.");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error(string: "Import failed: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    protected function findLatestGhostExport(): ?string
    {
        $files = glob(storage_path('app/*.json'));

        if (empty($files)) {
            return null;
        }

        // Sort by filename descending (assuming standard Ghost export format: .ghost.YYYY-MM-DD-...)
        rsort($files);

        return $files[0];
    }
}
