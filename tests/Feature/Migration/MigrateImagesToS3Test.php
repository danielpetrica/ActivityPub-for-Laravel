<?php

namespace Tests\Feature\Migration;

use Illuminate\Support\Facades\Storage;

it('migrates images from local public disk to s3 hetzner disk', function () {
    Storage::fake('public');
    Storage::fake('hetzner');

    $sourceStorage = Storage::disk('public');
    $destStorage = Storage::disk('hetzner');

    $sourceStorage->put('media/content/2023/01/test.png', 'fake-content');
    $sourceStorage->put('media/feature/2023/02/test2.jpg', 'fake-content-2');

    $this->artisan('app:migrate-images-to-s3')
        ->expectsOutputToContain('Starting image migration to hetzner')
        ->expectsTable(['Status', 'Count'], [
            ['Migrated', 2],
            ['Skipped (already exists)', 0],
            ['Errors', 0],
        ])
        ->assertExitCode(0);

    $destStorage->assertExists('media/content/2023/01/test.png');
    $destStorage->assertExists('media/feature/2023/02/test2.jpg');

    expect($destStorage->get('media/content/2023/01/test.png'))->toBe('fake-content');
});

it('skips already existing files on destination', function () {
    Storage::fake('public');
    Storage::fake('hetzner');

    $sourceStorage = Storage::disk('public');
    $destStorage = Storage::disk('hetzner');

    $sourceStorage->put('media/content/test.png', 'new-content');
    $destStorage->put('media/content/test.png', 'old-content');

    $this->artisan('app:migrate-images-to-s3')
        ->expectsTable(['Status', 'Count'], [
            ['Migrated', 0],
            ['Skipped (already exists)', 1],
            ['Errors', 0],
        ])
        ->assertExitCode(0);

    expect($destStorage->get('media/content/test.png'))->toBe('old-content');
});

it('honors dry run mode', function () {
    Storage::fake('public');
    Storage::fake('hetzner');

    Storage::disk('public')->put('media/content/test.png', 'content');

    $this->artisan('app:migrate-images-to-s3', ['--dry' => true])
        ->expectsOutputToContain('DRY RUN')
        ->expectsTable(['Status', 'Count'], [
            ['Migrated', 1],
            ['Skipped (already exists)', 0],
            ['Errors', 0],
        ])
        ->assertExitCode(0);

    Storage::disk('hetzner')->assertMissing('media/content/test.png');
});
