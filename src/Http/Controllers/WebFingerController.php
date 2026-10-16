<?php

namespace DanielPetrica\LaravelActivityPub\Http\Controllers;

use DanielPetrica\LaravelActivityPub\Http\Requests\WebFingerRequest;
use DanielPetrica\LaravelActivityPub\Http\Resources\WebFingerResource;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class WebFingerController extends Controller
{
    public function __invoke(WebFingerRequest $request): JsonResponse
    {
        $resource = (string) $request->query('resource');

        $cors = ['Access-Control-Allow-Origin' => '*'];

        $account = $this->parseResource(resource: $resource);

        if ($account === null) {
            return response()->json(
                ['error' => 'Invalid resource format.'],
                404,
                $cors,
            );
        }

        [$username, $domain] = $account;

        $parsedUrl = parse_url(config('activitypub.domain'));
        $configuredDomain = $parsedUrl['host'] ?? '';
        if (isset($parsedUrl['port'])) {
            $configuredDomain .= ':'.$parsedUrl['port'];
        }

        if ($domain !== $configuredDomain) {
            return response()->json(
                ['error' => 'Domain not found.'],
                404,
                $cors,
            );
        }

        $actor = Actor::query()
            ->where('username', '=', $username)
            ->first();

        if ($actor === null) {
            return response()->json(
                ['error' => 'User not found.'],
                404,
                $cors,
            );
        }

        $jrd = (new WebFingerResource(resource: $actor))->toArray(request: $request);

        // WebFinger is a cross-origin discovery endpoint; browsers (and some
        // federation tools) call it from other origins.
        $headers = array_merge($cors, ['Content-Type' => 'application/jrd+json']);

        if (config('activitypub.cache.enabled', true)) {
            $headers['Cache-Control'] = 'public, max-age='.config('activitypub.cache.ttl', 86400);
        }

        return response()->json($jrd, 200, $headers);
    }

    /**
     * Normalise the `resource` parameter into a `[username, domain]` pair.
     *
     * Accepts `acct:user@domain`, `https://domain/users/user`, `https://domain/@user`
     * and the bare `user@domain` form.
     *
     * @return array{0: string, 1: string}|null
     */
    protected function parseResource(string $resource): ?array
    {
        if (str_starts_with($resource, 'acct:')) {
            return $this->splitAccount(account: substr($resource, 5));
        }

        if (preg_match('#^https?://#i', $resource) === 1) {
            $host = parse_url($resource, PHP_URL_HOST);

            if (! is_string($host) || $host === '') {
                return null;
            }

            $domain = $host;
            $port = parse_url($resource, PHP_URL_PORT);
            if (is_int($port)) {
                $domain .= ':'.$port;
            }

            $path = parse_url($resource, PHP_URL_PATH);
            $segments = array_values(array_filter(
                explode('/', is_string($path) ? $path : ''),
                static fn (string $segment): bool => $segment !== '',
            ));

            if ($segments === []) {
                return null;
            }

            $username = ltrim((string) end($segments), '@');

            if ($username === '') {
                return null;
            }

            return [$username, $domain];
        }

        if (str_contains($resource, '@')) {
            return $this->splitAccount(account: $resource);
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    protected function splitAccount(string $account): ?array
    {
        $parts = explode('@', $account);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        return [$parts[0], $parts[1]];
    }
}
