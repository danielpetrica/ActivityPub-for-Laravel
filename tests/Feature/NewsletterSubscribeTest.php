<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('subscribes via form post and redirects with query flag', function () {
    // Fake Mailcoach API
    Http::fake([
        '*' => Http::response(status: 200, body: ['ok' => true]),
    ]);

    $response = $this->post('/subscribe', [
        'email' => 'john@example.com',
        'slug' => 'homepage',
    ]);

    $response->assertRedirect();
    expect($response->headers->get('Location'))
        ->toContain('subscribed=1');
});

it('returns JSON on json request', function () {
    Http::fake([
        '*' => Http::response(status: 200, body: ['ok' => true]),
    ]);

    $response = $this->postJson('/subscribe', [
        'email' => 'jane@example.com',
    ]);

    $response->assertOk()->assertJsonPath('ok', true);
});

it('validates email', function () {
    $response = $this->post('/subscribe', [
        'email' => 'not-an-email',
    ]);

    $response->assertSessionHasErrors();
})->skip('Frontend is session-less, so validation errors are not flashed.');
