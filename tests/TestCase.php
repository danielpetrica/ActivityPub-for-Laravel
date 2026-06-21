<?php

namespace DanielPetrica\LaravelActivityPub\Tests;

use DanielPetrica\LaravelActivityPub\ActivityPubServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate');
    }

    protected function getPackageProviders($app): array
    {
        return [
            ActivityPubServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $app['config']->set('app.url', 'http://localhost');

        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('activitypub.domain', 'http://localhost');
        $app['config']->set('activitypub.routes.middleware', ['api']);
    }
}
