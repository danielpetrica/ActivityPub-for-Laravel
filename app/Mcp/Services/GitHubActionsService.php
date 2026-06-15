<?php

namespace App\Mcp\Services;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GitHubActionsService
{
    private string $token;

    private string $baseUrl;

    public function __construct(?Factory $http = null)
    {
        $this->token = (string) config('gh-actions.github.token');
        $this->baseUrl = (string) config('gh-actions.github.api_base_url');
    }

    /**
     * Parse action input — accepts "owner/repo" or "https://github.com/owner/repo".
     *
     * @return array{owner: string, repo: string}
     */
    public function parseActionInput(string $input): array
    {
        // Strip https://github.com/ prefix if present
        $input = preg_replace('#^https?://github\.com/#i', '', $input);
        // Strip trailing slashes
        $input = rtrim($input, '/');
        // Remove .git suffix if present
        $input = preg_replace('/\.git$/', '', $input);

        if (! str_contains($input, '/') || substr_count($input, '/') !== 1) {
            throw new \InvalidArgumentException(
                'Invalid action format. Use "owner/repo" or a GitHub URL.'
            );
        }

        [$owner, $repo] = explode('/', $input);
        $owner = trim($owner);
        $repo = trim($repo);

        if ($owner === '' || $repo === '') {
            throw new \InvalidArgumentException(
                'Invalid action format. Owner and repo must not be empty.'
            );
        }

        return ['owner' => $owner, 'repo' => $repo];
    }

    /**
     * Fetch action data from GitHub API — parallel calls to tags, releases, readme, repo.
     */
    public function fetchAction(string $owner, string $repo): array
    {
        $http = Http::withToken($this->token)
            ->accept('application/vnd.github+json')
            ->withUserAgent('danielpetrica-mcp/1.0');

        // Fire all 4 requests in parallel
        $responses = [
            'tags' => $http->async()->get("{$this->baseUrl}/repos/{$owner}/{$repo}/tags?per_page=100"),
            'releases' => $http->async()->get("{$this->baseUrl}/repos/{$owner}/{$repo}/releases?per_page=100"),
            'readme' => $http->async()->get("{$this->baseUrl}/repos/{$owner}/{$repo}/readme"),
            'repo' => $http->async()->get("{$this->baseUrl}/repos/{$owner}/{$repo}"),
        ];

        // Wait for all responses
        $results = [];
        foreach ($responses as $key => $promise) {
            $results[$key] = $promise->wait();
        }

        // Check for 404
        if ($results['repo']->status() === 404) {
            throw new class("GitHub action '{$owner}/{$repo}' not found.") extends RuntimeException
            {
                public function getErrorCode(): string
                {
                    return 'not_found';
                }
            };
        }

        // Check for authentication failure
        if ($results['repo']->status() === 401) {
            throw new class('GitHub API authentication failed. Check your GITHUB_TOKEN configuration.') extends RuntimeException
            {
                public function getErrorCode(): string
                {
                    return 'unauthorized';
                }
            };
        }

        // Check for rate limiting
        if ($results['repo']->status() === 429 || $results['repo']->status() === 403) {
            $retryAfter = (int) ($results['repo']->header('Retry-After') ?? 3600);
            throw new class("GitHub API rate limited. Retry after {$retryAfter} seconds.", $retryAfter) extends RuntimeException
            {
                public function __construct(string $message, public int $retryAfter)
                {
                    parent::__construct($message);
                }

                public function getErrorCode(): string
                {
                    return 'rate_limited';
                }
            };
        }

        $repoData = $results['repo']->json();
        $tagsData = $results['tags']->json();
        $releasesData = $results['releases']->json();
        $readmeData = $results['readme']->json();

        $stars = $repoData['stargazers_count'] ?? 0;
        $repoDescription = $repoData['description'] ?? '';

        // Build a map of tag → release info (for prerelease flag, published_at)
        $releaseMap = [];
        if (is_array($releasesData)) {
            foreach ($releasesData as $release) {
                $tag = $release['tag_name'] ?? '';
                if ($tag !== '') {
                    $releaseMap[$tag] = [
                        'is_prerelease' => (bool) ($release['prerelease'] ?? false),
                        'published_at' => $release['published_at'] ?? null,
                    ];
                }
            }
        }

        // Process tags into major version groups
        $majorVersions = [];
        $latest = null;

        if (is_array($tagsData) && count($tagsData) > 0) {
            // Ensure tags data is a list of tag objects, not a single repo object
            if (! isset($tagsData[0]['name'])) {
                throw new class("No tagged releases found for '{$owner}/{$repo}'.") extends RuntimeException
                {
                    public function getErrorCode(): string
                    {
                        return 'no_versions';
                    }
                };
            }

            // Sort tags — newest first (by commit date if available)
            usort($tagsData, function (mixed $a, mixed $b) {
                if (! is_array($a) || ! is_array($b)) {
                    return 0;
                }
                $dateA = $a['commit']['sha'] ?? '';
                $dateB = $b['commit']['sha'] ?? '';

                // We can't easily sort by date from tags endpoint, so try by name
                return version_compare($this->normalizeVersion($b['name']), $this->normalizeVersion($a['name']));
            });

            $grouped = [];
            foreach ($tagsData as $tag) {
                $version = $this->normalizeVersion($tag['name']);
                $major = $this->extractMajor($version);

                if (! isset($grouped[$major])) {
                    $release = $releaseMap[$tag['name']] ?? null;
                    $grouped[$major] = [
                        'major' => $major,
                        'latest_patch' => $version,
                        'latest_tag' => $tag['name'],
                        'released_at' => $release['published_at'] ?? null,
                        'is_prerelease' => $release['is_prerelease'] ?? false,
                    ];
                }

                // Track deprecated major versions (v1, v2 when v4 is current)
                if ($latest === null) {
                    $release = $releaseMap[$tag['name']] ?? null;
                    $latest = [
                        'version' => $tag['name'],
                        'version_clean' => $version,
                        'major' => $major,
                        'released_at' => $release['published_at'] ?? null,
                        'is_prerelease' => $release['is_prerelease'] ?? false,
                    ];
                }
            }

            // Build major_versions list, newest first
            $sorted = array_values($grouped);
            usort($sorted, function (array $a, array $b) {
                return version_compare($this->normalizeVersion($b['latest_patch']), $this->normalizeVersion($a['latest_patch']));
            });

            $currentMajor = $latest['major'] ?? '';
            foreach ($sorted as $entry) {
                $entry['is_stable'] = ! $entry['is_prerelease'];
                $entry['deprecated'] = ($entry['major'] !== $currentMajor);
                $majorVersions[] = $entry;
            }
        } else {
            throw new class("No tagged releases found for '{$owner}/{$repo}'.") extends RuntimeException
            {
                public function getErrorCode(): string
                {
                    return 'no_versions';
                }
            };
        }

        // Decode readme from base64
        $readme = '';
        if (is_array($readmeData) && isset($readmeData['content'])) {
            $readme = base64_decode($readmeData['content'], true) ?: '';
            // Truncate readme to ~8000 chars to keep responses manageable
            if (strlen($readme) > 8000) {
                $readme = substr($readme, 0, 8000)."\n\n... (truncated)";
            }
        }

        return [
            'name' => "{$owner}/{$repo}",
            'description' => $repoDescription,
            'owner' => $owner,
            'repo' => $repo,
            'stars' => $stars,
            'latest' => $latest,
            'major_versions' => $majorVersions,
            'readme' => $readme,
            'fetched_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Fetch latest versions for popular actions in a category.
     * All API requests are fired concurrently for maximum speed.
     */
    public function fetchPopularActions(string $category, int $limit): array
    {
        $popularList = $this->getPopularActionNames($category);

        if ($popularList === []) {
            throw new \InvalidArgumentException("Unknown category: '{$category}'. Valid categories: ci, deployment, security, utility, docker, node, all");
        }

        $targetActions = array_slice($popularList, 0, $limit);

        $http = Http::withToken($this->token)
            ->accept('application/vnd.github+json')
            ->withUserAgent('danielpetrica-mcp/1.0');

        // Fire all requests concurrently — then wait for all
        $promises = [];
        foreach ($targetActions as $actionName) {
            [$owner, $repo] = explode('/', $actionName);
            $key = str_replace('/', '_', $actionName);
            $promises["repo_{$key}"] = $http->async()->get("{$this->baseUrl}/repos/{$owner}/{$repo}");
            $promises["tags_{$key}"] = $http->async()->get("{$this->baseUrl}/repos/{$owner}/{$repo}/tags?per_page=1");
        }

        $responses = [];
        foreach ($promises as $key => $promise) {
            try {
                $responses[$key] = $promise->wait();
            } catch (\Throwable) {
                $responses[$key] = null;
            }
        }

        $actions = [];
        $errors = [];

        foreach ($targetActions as $actionName) {
            $key = str_replace('/', '_', $actionName);

            $repoResponse = $responses["repo_{$key}"] ?? null;
            $tagsResponse = $responses["tags_{$key}"] ?? null;

            if (! $repoResponse || $repoResponse->failed()) {
                $errors[] = $actionName;

                continue;
            }

            $repoData = $repoResponse->json();
            $tagsData = $tagsResponse?->json() ?? [];

            $latestTag = null;
            if (is_array($tagsData) && count($tagsData) > 0) {
                $latestTag = $tagsData[0]['name'] ?? null;
            }

            $actions[] = [
                'name' => $actionName,
                'category' => $category,
                'latest_version' => $latestTag,
                'description' => $repoData['description'] ?? '',
                'stars' => $repoData['stargazers_count'] ?? 0,
            ];
        }

        // Sort by stars descending
        usort($actions, fn (array $a, array $b) => ($b['stars'] ?? 0) <=> ($a['stars'] ?? 0));

        return [
            'actions' => $actions,
            'category' => $category,
            'errors' => $errors,
            'fetched_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get the curated popular actions for "all" category.
     */
    public function getPopularActionNames(string $category): array
    {
        if ($category === 'all') {
            $all = [];
            $categories = ['ci', 'deployment', 'security', 'utility', 'docker', 'node'];
            foreach ($categories as $cat) {
                $list = config("gh-actions.popular_actions.{$cat}", []);
                $all = array_merge($all, $list);
            }

            return array_unique($all);
        }

        return config("gh-actions.popular_actions.{$category}", []);
    }

    /**
     * Normalize a tag name to a semver-compatible string like "v4.2.2" → "4.2.2".
     */
    private function normalizeVersion(string $tag): string
    {
        return ltrim($tag, 'vV');
    }

    /**
     * Extract major version from a normalized version string.
     * "4.2.2" → "v4"
     */
    private function extractMajor(string $version): string
    {
        $parts = explode('.', $version);

        return 'v'.($parts[0] ?? '0');
    }
}
