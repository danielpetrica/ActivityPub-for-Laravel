<?php

namespace App\Classes\Business\Migration;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class ImageMigrationBusiness
{
    private string $sourceDisk = 'public';

    private string $destinationDisk;

    private bool $dryRun;

    public function __construct(bool $dryRun = false, string $destinationDisk = 'hetzner')
    {
        $this->dryRun = $dryRun;
        $this->destinationDisk = $destinationDisk;
    }

    public function run(): array
    {
        $report = [
            'moved' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $sourceStorage = Storage::disk($this->sourceDisk);
        $destinationStorage = Storage::disk($this->destinationDisk);

        // Files are organized as media/feature/yyyy/mm/... or media/content/yyyy/mm/...
        // We'll scan everything under 'media'
        $allFiles = $sourceStorage->allFiles('media');

        foreach ($allFiles as $filePath) {
            try {
                if ($destinationStorage->exists($filePath)) {
                    $report['skipped']++;

                    continue;
                }

                if ($this->dryRun) {
                    $report['moved']++;

                    continue;
                }

                $fileContent = $sourceStorage->get($filePath);
                $mimeType = $sourceStorage->mimeType($filePath);

                $destinationStorage->put(
                    path: $filePath,
                    contents: $fileContent,
                    options: ['visibility' => 'public', 'ContentType' => $mimeType]
                );

                $report['moved']++;

                Log::debug('Image migrated to S3', [
                    'path' => $filePath,
                    'disk' => $this->destinationDisk,
                ]);
            } catch (\Exception $e) {
                $report['errors'][] = "Failed to migrate {$filePath}: {$e->getMessage()}";
                Log::error('Image migration error', [
                    'path' => $filePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $report;
    }
}
