<?php

namespace DanielPetrica\LaravelActivityPub\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait RespondsToAccept
{
    protected function wantsJson(Request $request): bool
    {
        return $request->accepts(['application/activity+json', 'application/ld+json']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function activityPubResponse(Request $request, array $data, int $status = 200): JsonResponse
    {
        $headers = [
            'Content-Type' => $this->activityPubContentType($request),
            'Vary' => 'Accept',
        ];

        if (config('activitypub.cache.enabled', true)) {
            $headers['Cache-Control'] = 'public, max-age='.config('activitypub.cache.ttl', 86400);
        }

        return response()->json(
            $data,
            $status,
            $headers,
        );
    }

    /**
     * Echo the media type the client asked for. Strict JSON-LD consumers
     * (and some relays) send `Accept: application/ld+json; profile="..."` and
     * expect the matching content type; ActivityPub-native consumers send
     * `application/activity+json`.
     */
    protected function activityPubContentType(Request $request): string
    {
        $accept = (string) $request->header('Accept', '');

        $ldJsonPosition = stripos($accept, 'application/ld+json');
        $activityJsonPosition = stripos($accept, 'application/activity+json');

        if ($ldJsonPosition !== false
            && ($activityJsonPosition === false || $ldJsonPosition < $activityJsonPosition)) {
            return 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"';
        }

        return 'application/activity+json';
    }
}
