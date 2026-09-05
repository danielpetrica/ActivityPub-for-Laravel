<?php

use DanielPetrica\LaravelActivityPub\Traits\LogsActivityPub;
use Illuminate\Support\Facades\Log;

class TestLoggingClass
{
    use LogsActivityPub;

    public function testLog(string $level, string $message, array $context = []): void
    {
        $this->activityPubLog($level, $message, $context);
    }
}

it('does not log when logging is disabled', function (): void {
    config()->set('activitypub.logging.enabled', false);

    $testClass = new TestLoggingClass();

    Log::shouldReceive('channel')->never();

    $testClass->testLog('info', 'test message');
});

it('logs to default channel when no channel is configured', function (): void {
    config()->set('activitypub.logging.enabled', true);
    config()->set('activitypub.logging.channel', null);

    $mock = \Mockery::mock();
    $mock->shouldReceive('info')->once()->with('ActivityPub: test message', []);

    Log::shouldReceive('channel')->once()->with()->andReturn($mock);

    $testClass = new TestLoggingClass();
    $testClass->testLog('info', 'test message');
});

it('logs to configured channel', function (): void {
    config()->set('activitypub.logging.enabled', true);
    config()->set('activitypub.logging.channel', 'activitypub');

    $mock = \Mockery::mock();
    $mock->shouldReceive('warning')->once()->with('ActivityPub: warning message', ['key' => 'value']);

    Log::shouldReceive('channel')->once()->with('activitypub')->andReturn($mock);

    $testClass = new TestLoggingClass();
    $testClass->testLog('warning', 'warning message', ['key' => 'value']);
});

it('respects configured log level', function (): void {
    config()->set('activitypub.logging.enabled', true);
    config()->set('activitypub.logging.channel', 'activitypub');

    $mock = \Mockery::mock();
    $mock->shouldReceive('debug')->once()->with('ActivityPub: debug message', []);

    Log::shouldReceive('channel')->once()->with('activitypub')->andReturn($mock);

    $testClass = new TestLoggingClass();
    $testClass->testLog('debug', 'debug message');
});

it('passes context array to logger', function (): void {
    config()->set('activitypub.logging.enabled', true);
    config()->set('activitypub.logging.channel', null);

    $context = ['actorUri' => 'https://example.com/users/test', 'statusCode' => 404];

    $mock = \Mockery::mock();
    $mock->shouldReceive('error')->once()->with('ActivityPub: error occurred', $context);

    Log::shouldReceive('channel')->once()->with()->andReturn($mock);

    $testClass = new TestLoggingClass();
    $testClass->testLog('error', 'error occurred', $context);
});
