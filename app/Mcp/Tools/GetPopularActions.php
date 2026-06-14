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

#[Description('Get a curated list of popular GitHub Actions by category. Categories: ci, deployment, security, utility, docker, node, or "all".')]
#[IsReadOnly]
final class GetPopularActions extends Tool
{
    private array $validCategories = ['all', 'ci', 'deployment', 'security', 'utility', 'docker', 'node'];

    public function schema(JsonSchema $schema): array
    {
        return [
            'category' => $schema->string()
                ->enum($this->validCategories)
                ->description('Category of popular actions to fetch')
                ->default('all'),

            'limit' => $schema->integer()
                ->description('Maximum number of actions to return')
                ->default(20),
        ];
    }

    public function handle(Request $request, GitHubActionsService $service): Response|ResponseFactory
    {
        $category = $request->get('category', 'all');
        $limit = (int) $request->get('limit', 20);

        if (! is_string($category) || ! in_array($category, $this->validCategories, true)) {
            return Response::error("Invalid category '{$category}'. Valid categories: ".implode(', ', $this->validCategories));
        }

        if ($limit < 1) {
            $limit = 20;
        }
        if ($limit > 50) {
            $limit = 50;
        }

        $cached = ActionCache::getPopular($category);
        if ($cached !== null) {
            $cached['actions'] = array_slice($cached['actions'], 0, $limit);

            return Response::structured($cached);
        }

        try {
            $data = $service->fetchPopularActions($category, $limit);
            ActionCache::putPopular($category, $data);

            return Response::structured($data);
        } catch (\InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        } catch (\RuntimeException $e) {
            $errorCode = method_exists($e, 'getErrorCode') ? $e->getErrorCode() : 'unknown';

            Log::warning('GitHubActionsMCP: popular fetch failed', [
                'category' => $category,
                'error_code' => $errorCode,
                'message' => $e->getMessage(),
            ]);

            if ($errorCode === 'rate_limited') {
                $retryAfter = property_exists($e, 'retryAfter') ? $e->retryAfter : 3600;

                return Response::error("GitHub API rate limited. Retry after {$retryAfter} seconds.")
                    ->withMeta(['error_code' => 'rate_limited', 'retry_after' => $retryAfter]);
            }

            return Response::error("Failed to fetch popular actions: {$e->getMessage()}")
                ->withMeta(['error_code' => 'unknown']);
        }
    }
}
