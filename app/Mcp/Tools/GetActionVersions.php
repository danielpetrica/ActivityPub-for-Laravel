<?php

namespace App\Mcp\Tools;

use App\Mcp\Cache\ActionCache;
use App\Mcp\Services\GitHubActionsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get the latest version and release history for any GitHub Action. Provide the action as "owner/repo" or a full GitHub URL.')]
#[IsReadOnly]
final class GetActionVersions extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->description('The GitHub Action to look up (e.g. "actions/checkout" or "https://github.com/actions/checkout")')
                ->required(),
        ];
    }

    public function handle(Request $request, GitHubActionsService $service): Response|ResponseFactory
    {
        $action = $request->get('action');

        if (! is_string($action) || trim($action) === '') {
            return Response::error('The "action" parameter is required and must be a string.');
        }

        try {
            ['owner' => $owner, 'repo' => $repo] = $service->parseActionInput($action);
        } catch (\InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }

        if (ActionCache::hasNegative($owner, $repo)) {
            return Response::error("GitHub action '{$owner}/{$repo}' was previously not found. Cache expires after 24 hours.")
                ->withMeta(['error_code' => 'not_found']);
        }

        $cached = ActionCache::getAction($owner, $repo);
        if ($cached !== null) {
            return Response::structured($cached);
        }

        try {
            $data = $service->fetchAction($owner, $repo);
            ActionCache::putAction($owner, $repo, $data);

            return Response::structured($data);
        } catch (\RuntimeException $e) {
            $errorCode = method_exists($e, 'getErrorCode') ? $e->getErrorCode() : 'unknown';

            Log::warning('GitHubActionsMCP: fetch failed', [
                'action' => "{$owner}/{$repo}",
                'error_code' => $errorCode,
                'message' => $e->getMessage(),
            ]);

            if ($errorCode === 'not_found') {
                ActionCache::putNegative($owner, $repo);

                return Response::error("GitHub action '{$owner}/{$repo}' not found.")
                    ->withMeta(['error_code' => 'not_found']);
            }

            if ($errorCode === 'rate_limited') {
                $retryAfter = property_exists($e, 'retryAfter') ? $e->retryAfter : 3600;

                return Response::error("GitHub API rate limited. Retry after {$retryAfter} seconds.")
                    ->withMeta(['error_code' => 'rate_limited', 'retry_after' => $retryAfter]);
            }

            if ($errorCode === 'no_versions') {
                return Response::error("No tagged releases found for '{$owner}/{$repo}'.")
                    ->withMeta(['error_code' => 'no_versions']);
            }

            if ($errorCode === 'unauthorized') {
                return Response::error($e->getMessage())
                    ->withMeta(['error_code' => 'unauthorized']);
            }

            return Response::error("Failed to fetch action data: {$e->getMessage()}")
                ->withMeta(['error_code' => 'unknown']);
        }
    }
}
