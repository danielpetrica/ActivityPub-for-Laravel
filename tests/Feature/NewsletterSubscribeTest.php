<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    // The route is now CSRF-protected via web middleware.
    // Disable CSRF in tests since the JS layer handles it in production.
    $this->withoutMiddleware(VerifyCsrfToken::class);
});

it('subscribes via form post and redirects with query flag', function () {
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
});

it('returns csrf token endpoint', function () {
    $response = $this->get('/csrf-token');

    $response->assertOk();
    $response->assertJsonStructure(['csrf_token']);
    expect($response->json('csrf_token'))->toBeString();
});
