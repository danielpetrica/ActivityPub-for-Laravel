<?php

namespace App\Console\Commands;

use App\Classes\Business\Migration\ImageMigrationBusiness;
use Illuminate\Console\Command;

final class MigrateImagesToS3Command extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-images-to-s3 {--dry} {--disk=hetzner}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate images from local storage to S3 hetzner storage';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry');
        $disk = $this->option('disk');

        $this->info(string: "Starting image migration to {$disk}".($dryRun ? ' (DRY RUN)' : ''));

        try {
            $migrator = new ImageMigrationBusiness(dryRun: $dryRun, destinationDisk: $disk);
            $report = $migrator->run();

            $this->table(
                headers: ['Status', 'Count'],
                rows: [
                    ['Migrated', $report['moved']],
                    ['Skipped (already exists)', $report['skipped']],
                    ['Errors', count($report['errors'])],
                ]
            );

            if (! empty($report['errors'])) {
                $this->error(string: "\nErrors:");
                foreach ($report['errors'] as $error) {
                    $this->line(string: "- {$error}");
                }
            }

            $this->info(string: "\nMigration completed.");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error(string: "Migration failed: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
