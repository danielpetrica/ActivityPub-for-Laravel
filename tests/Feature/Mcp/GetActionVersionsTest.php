<?php

use App\Mcp\Servers\GhActionsServer;
use App\Mcp\Tools\GetActionVersions;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
    config()->set('gh-actions.cache.store', 'array');
});

test('it returns structured data for a valid owner/repo input', function (): void {
    Http::fake([
        'api.github.com/repos/actions/checkout/tags?per_page=100' => Http::response([
            ['name' => 'v4.2.2', 'commit' => ['sha' => 'abc']],
            ['name' => 'v4.1.0', 'commit' => ['sha' => 'def']],
            ['name' => 'v3.6.0', 'commit' => ['sha' => 'ghi']],
        ]),
        'api.github.com/repos/actions/checkout/releases?per_page=100' => Http::response([
            [
                'tag_name' => 'v4.2.2',
                'prerelease' => false,
                'published_at' => '2025-12-15T10:30:00Z',
            ],
            [
                'tag_name' => 'v4.1.0',
                'prerelease' => false,
                'published_at' => '2025-06-01T08:00:00Z',
            ],
            [
                'tag_name' => 'v3.6.0',
                'prerelease' => false,
                'published_at' => '2024-07-01T08:00:00Z',
            ],
        ]),
        'api.github.com/repos/actions/checkout/readme' => Http::response([
            'content' => base64_encode('# Checkout V4'),
        ]),
        'api.github.com/repos/actions/checkout' => Http::response([
            'stargazers_count' => 6200,
            'description' => 'Check out your repository onto the runner',
        ]),
    ]);

    $response = GhActionsServer::tool(GetActionVersions::class, [
        'action' => 'actions/checkout',
    ]);

    $response->assertOk();
    $response->assertSee('actions/checkout');
    $response->assertSee('v4.2.2');
});

test('it parses full github urls', function (): void {
    Http::fake([
        'api.github.com/repos/actions/checkout/tags?per_page=100' => Http::response([
            ['name' => 'v4.2.2', 'commit' => ['sha' => 'abc']],
            ['name' => 'v3.6.0', 'commit' => ['sha' => 'def']],
        ]),
        'api.github.com/repos/actions/checkout/releases?per_page=100' => Http::response([
            ['tag_name' => 'v4.2.2', 'prerelease' => false, 'published_at' => '2025-12-15T10:30:00Z'],
            ['tag_name' => 'v3.6.0', 'prerelease' => false, 'published_at' => '2024-07-01T08:00:00Z'],
        ]),
        'api.github.com/repos/actions/checkout/readme' => Http::response([
            'content' => base64_encode('# Checkout'),
        ]),
        'api.github.com/repos/actions/checkout' => Http::response([
            'stargazers_count' => 6200,
            'description' => 'Check out your repository onto the runner',
        ]),
    ]);

    $response = GhActionsServer::tool(GetActionVersions::class, [
        'action' => 'https://github.com/actions/checkout',
    ]);

    $response->assertOk();
});

test('it strips trailing slashes and .git suffix from input', function (): void {
    Http::fake([
        'api.github.com/repos/actions/checkout/tags?per_page=100' => Http::response([
            ['name' => 'v4.2.2', 'commit' => ['sha' => 'abc']],
            ['name' => 'v3.6.0', 'commit' => ['sha' => 'def']],
        ]),
        'api.github.com/repos/actions/checkout/releases?per_page=100' => Http::response([
            ['tag_name' => 'v4.2.2', 'prerelease' => false, 'published_at' => '2025-12-15T10:30:00Z'],
            ['tag_name' => 'v3.6.0', 'prerelease' => false, 'published_at' => '2024-07-01T08:00:00Z'],
        ]),
        'api.github.com/repos/actions/checkout/readme' => Http::response([
            'content' => base64_encode('# Checkout'),
        ]),
        'api.github.com/repos/actions/checkout' => Http::response([
            'stargazers_count' => 6200,
            'description' => 'Check out your repository onto the runner',
        ]),
    ]);

    $response = GhActionsServer::tool(GetActionVersions::class, [
        'action' => 'actions/checkout/',
    ]);

    $response->assertOk();
});

test('it returns error for empty action parameter', function (): void {
    Http::fake();

    $response = GhActionsServer::tool(GetActionVersions::class, [
        'action' => '',
    ]);

    $response->assertHasErrors();
});

test('it returns error for invalid action format', function (string $action): void {
    Http::fake();

    $response = GhActionsServer::tool(GetActionVersions::class, [
        'action' => $action,
    ]);

    $response->assertHasErrors();
})->with([
    'no slash' => ['invalid'],
    'too many slashes' => ['owner/repo/extra'],
    'empty owner' => ['/repo'],
    'empty repo' => ['owner/'],
]);

test('it returns error when repo is not found (404)', function (): void {
    Http::fake([
        'api.github.com/repos/owner/nonexistent/tags?per_page=100' => Http::response([]),
        'api.github.com/repos/owner/nonexistent/releases?per_page=100' => Http::response([]),
        'api.github.com/repos/owner/nonexistent/readme' => Http::response(['content' => '']),
        'api.github.com/repos/owner/nonexistent' => Http::response(null, 404),
    ]);

    $response = GhActionsServer::tool(GetActionVersions::class, [
        'action' => 'owner/nonexistent',
    ]);

    $response->assertHasErrors();
    $response->assertSee('not found');
});

test('it returns error when rate limited (429)', function (): void {
    Http::fake([
        'api.github.com/repos/owner/repo/tags?per_page=100' => Http::response([]),
        'api.github.com/repos/owner/repo/releases?per_page=100' => Http::response([]),
        'api.github.com/repos/owner/repo/readme' => Http::response(['content' => '']),
        'api.github.com/repos/owner/repo' => Http::response(null, 429, [
            'Retry-After' => '3600',
        ]),
    ]);

    $response = GhActionsServer::tool(GetActionVersions::class, [
        'action' => 'owner/repo',
    ]);

    $response->assertHasErrors();
    $response->assertSee('rate limited');
});

test('it returns error when rate limited with 403', function (): void {
    Http::fake([
        'api.github.com/repos/owner/repo/tags?per_page=100' => Http::response([]),
        'api.github.com/repos/owner/repo/releases?per_page=100' => Http::response([]),
        'api.github.com/repos/owner/repo/readme' => Http::response(['content' => '']),
        'api.github.com/repos/owner/repo' => Http::response(null, 403),
    ]);

    $response = GhActionsServer::tool(GetActionVersions::class, [
        'action' => 'owner/repo',
    ]);

    $response->assertHasErrors();
    $response->assertSee('rate limited');
});

test('it hits negative cache for previously not-found repos', function (): void {
    Http::fake([
        'api.github.com/repos/owner/missing/tags?per_page=100' => Http::response([]),
        'api.github.com/repos/owner/missing/releases?per_page=100' => Http::response([]),
        'api.github.com/repos/owner/missing/readme' => Http::response(['content' => '']),
        'api.github.com/repos/owner/missing' => Http::response(null, 404),
    ]);

    // First call — should hit API and get 404
    $first = GhActionsServer::tool(GetActionVersions::class, [
        'action' => 'owner/missing',
    ]);
    $first->assertHasErrors();

    Http::fake();

    // Second call — should hit negative cache (no HTTP requests)
    $second = GhActionsServer::tool(GetActionVersions::class, [
        'action' => 'owner/missing',
    ]);
    $second->assertHasErrors();
    $second->assertSee('previously not found');
});

test('it returns cached data without making api calls', function (): void {
    Http::fake([
        'api.github.com/repos/cached/repo/tags?per_page=100' => Http::response([
            ['name' => 'v1.0.0', 'commit' => ['sha' => 'abc']],
        ]),
        'api.github.com/repos/cached/repo/releases?per_page=100' => Http::response([
            ['tag_name' => 'v1.0.0', 'prerelease' => false, 'published_at' => '2025-01-01T00:00:00Z'],
        ]),
        'api.github.com/repos/cached/repo/readme' => Http::response([
            'content' => base64_encode('# Cached'),
        ]),
        'api.github.com/repos/cached/repo' => Http::response([
            'stargazers_count' => 100,
            'description' => 'Cached',
        ]),
    ]);

    // First call — populates cache
    $first = GhActionsServer::tool(GetActionVersions::class, [
        'action' => 'cached/repo',
    ]);
    $first->assertOk();

    Http::fake();

    // Second call — should use cache, no HTTP needed
    $second = GhActionsServer::tool(GetActionVersions::class, [
        'action' => 'cached/repo',
    ]);
    $second->assertOk();
    $second->assertSee('Cached');
});

test('it handles repos with no tagged releases', function (): void {
    Http::fake([
        'api.github.com/repos/empty/tags/releases*' => Http::response([]),
        'api.github.com/repos/empty/tags/tags*' => Http::response([]),
        'api.github.com/repos/empty/tags/readme' => Http::response(['content' => '']),
        'api.github.com/repos/empty/tags' => Http::response(['stargazers_count' => 0, 'description' => '']),
    ]);

    $response = GhActionsServer::tool(GetActionVersions::class, [
        'action' => 'empty/tags',
    ]);

    $response->assertHasErrors();
    $response->assertSee('No tagged releases found');
});

test('the tool metadata has correct name and description', function (): void {
    $tool = new GetActionVersions;

    expect($tool->name())->toBe('get-action-versions');
    expect($tool->description())->toContain('Get the latest version');
});
