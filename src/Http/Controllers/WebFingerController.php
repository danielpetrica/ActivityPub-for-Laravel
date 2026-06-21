<?php

namespace DanielPetrica\LaravelActivityPub\Http\Controllers;

use DanielPetrica\LaravelActivityPub\Http\Resources\WebFingerResource;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class WebFingerController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $resource = $request->query(key: 'resource');

        if ($resource === null || ! is_string($resource)) {
            return response()->json(
                data: ['error' => 'The "resource" parameter is required.'],
                status: 400,
            );
        }

        if (! str_starts_with(haystack: $resource, needle: 'acct:')) {
            return response()->json(
                data: ['error' => 'Only "acct:" URI scheme is supported.'],
                status: 400,
            );
        }

        $account = substr(string: $resource, offset: 5);
        $parts = explode(separator: '@', string: $account);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return response()->json(
                data: ['error' => 'Invalid acct URI format. Expected acct:user@domain.'],
                status: 400,
            );
        }

        [$username, $domain] = $parts;

        $parsedUrl = parse_url(url: config('activitypub.domain'));
        $configuredDomain = $parsedUrl['host'];
        if (isset($parsedUrl['port'])) {
            $configuredDomain .= ':'.$parsedUrl['port'];
        }

        if ($domain !== $configuredDomain) {
            return response()->json(
                data: ['error' => 'Domain not found.'],
                status: 404,
            );
        }

        $actor = Actor::query()
            ->where(column: 'username', operator: '=', value: $username)
            ->first();

        if ($actor === null) {
            return response()->json(
                data: ['error' => 'User not found.'],
                status: 404,
            );
        }

        $jrd = (new WebFingerResource(resource: $actor))->toArray(request: $request);

        return response()->json(data: $jrd, headers: ['Content-Type' => 'application/jrd+json']);
    }
}
