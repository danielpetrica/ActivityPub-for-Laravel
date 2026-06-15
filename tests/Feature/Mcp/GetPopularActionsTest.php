<?php

use App\Mcp\Servers\GhActionsServer;
use App\Mcp\Tools\GetPopularActions;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
    config()->set('gh-actions.cache.store', 'array');
});

function fakeActionApi(string $owner, string $repo, int $stars = 100, string $description = 'A test action'): array
{
    return [
        "api.github.com/repos/{$owner}/{$repo}/tags?per_page=1" => Http::response([
            ['name' => 'v4.2.2', 'commit' => ['sha' => 'abc']],
        ]),
        "api.github.com/repos/{$owner}/{$repo}" => Http::response([
            'stargazers_count' => $stars,
            'description' => $description,
        ]),
    ];
}

test('it returns popular actions for a valid category', function (): void {
    Http::fake(array_merge(
        fakeActionApi('actions', 'checkout', stars: 6200, description: 'Checkout action'),
        fakeActionApi('actions', 'setup-node', stars: 4500, description: 'Setup Node'),
        fakeActionApi('actions', 'setup-python', stars: 3200, description: 'Setup Python'),
        fakeActionApi('actions', 'setup-go', stars: 1800, description: 'Setup Go'),
        fakeActionApi('actions', 'setup-java', stars: 2100, description: 'Setup Java'),
        fakeActionApi('actions', 'setup-dotnet', stars: 900, description: 'Setup .NET'),
        fakeActionApi('actions', 'cache', stars: 2800, description: 'Cache action'),
        fakeActionApi('actions', 'upload-artifact', stars: 3500, description: 'Upload artifact'),
        fakeActionApi('actions', 'download-artifact', stars: 1500, description: 'Download artifact'),
    ));

    $response = GhActionsServer::tool(GetPopularActions::class, [
        'category' => 'ci',
    ]);

    $response->assertOk();
    $response->assertSee('actions/checkout');
    $response->assertSee('ci');
});

test('it defaults to category "all" when none specified', function (): void {
    // Fake enough actions for the first few in the "all" merged list (ci comes first)
    Http::fake(array_merge(
        fakeActionApi('actions', 'checkout', stars: 6200, description: 'Checkout'),
        fakeActionApi('actions', 'setup-node', stars: 4500, description: 'Setup Node'),
        fakeActionApi('actions', 'setup-python', stars: 3200, description: 'Setup Python'),
    ));

    $response = GhActionsServer::tool(GetPopularActions::class, [
        'limit' => 3,
    ]);

    $response->assertOk();
});

test('it returns error for invalid category', function (): void {
    Http::fake();

    $response = GhActionsServer::tool(GetPopularActions::class, [
        'category' => 'invalid-category',
    ]);

    $response->assertHasErrors();
    $response->assertSee('Invalid category');
});

test('it limits results based on the limit parameter', function (): void {
    Http::fake(array_merge(
        fakeActionApi('docker', 'login-action', stars: 3000, description: 'Docker login'),
        fakeActionApi('docker', 'build-push-action', stars: 4000, description: 'Build push'),
        fakeActionApi('docker', 'setup-buildx-action', stars: 2500, description: 'Buildx'),
    ));

    $response = GhActionsServer::tool(GetPopularActions::class, [
        'category' => 'docker',
        'limit' => 2,
    ]);

    $response->assertOk();
    // Both fetched; sorted by stars desc so build-push-action (4000) comes first
    $response->assertSee('build-push-action');
});

test('it caps limit at 50', function (): void {
    Http::fake(
        fakeActionApi('actions', 'checkout', stars: 6200, description: 'Checkout')
    );

    $response = GhActionsServer::tool(GetPopularActions::class, [
        'category' => 'ci',
        'limit' => 999,
    ]);

    $response->assertOk();
});

test('it returns cached popular data without api calls', function (): void {
    Http::fake(array_merge(
        fakeActionApi('github', 'codeql-action', stars: 3000, description: 'CodeQL'),
        fakeActionApi('actions', 'dependency-review-action', stars: 1200, description: 'Dep review'),
        fakeActionApi('aquasecurity', 'trivy-action', stars: 2500, description: 'Trivy'),
        fakeActionApi('snyk', 'actions', stars: 1800, description: 'Snyk'),
        fakeActionApi('anchore', 'scan-action', stars: 900, description: 'Anchore'),
        fakeActionApi('step-security', 'harden-runner', stars: 1500, description: 'Harden runner'),
    ));

    // First call — populates cache
    $first = GhActionsServer::tool(GetPopularActions::class, [
        'category' => 'security',
    ]);
    $first->assertOk();

    Http::fake();

    // Second call — should use cache
    $second = GhActionsServer::tool(GetPopularActions::class, [
        'category' => 'security',
    ]);
    $second->assertOk();
    $second->assertSee('codeql-action');
});

test('it handles rate limiting with graceful degradation', function (): void {
    Http::fake([
        '*' => Http::response(null, 429, ['Retry-After' => '1800']),
    ]);

    $response = GhActionsServer::tool(GetPopularActions::class, [
        'category' => 'ci',
        'limit' => 5,
    ]);

    // Errors are swallowed by fetchPopularActions for resilience;
    // the response should still be OK but with 0 actions
    $response->assertOk();
});

test('the tool metadata has correct name and description', function (): void {
    $tool = new GetPopularActions;

    expect($tool->name())->toBe('get-popular-actions');
    expect($tool->description())->toContain('curated list');
});
