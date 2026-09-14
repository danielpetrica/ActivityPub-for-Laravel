<?php

namespace DanielPetrica\LaravelActivityPub\Helpers;

use DanielPetrica\LaravelActivityPub\Models\InboxLog;
use Illuminate\Http\Request;

final class InboxLogger
{
    public static function logRejected(Request $request, int $statusCode, string $reason): void
    {
        if (! config('activitypub.debug_display')) {
            return;
        }

        // Find the actor if one is attached to the request
        $actor = $request->attributes->get('remote_actor');
        $actorId = 1; // Default to first local actor
        $actorUrl = null;
        $keyId = null;

        if ($actor !== null) {
            $actorUrl = $actor->actor_url ?? null;
        }

        // Try to extract keyId from signature
        $signatureHeader = $request->header('signature');
        $signatureInput = $request->header('signature-input');

        if ($signatureInput !== null && preg_match('/keyid="([^"]+)"/', $signatureInput, $m)) {
            $keyId = $m[1];
        } elseif ($signatureHeader !== null && preg_match('/keyId="([^"]+)"/', $signatureHeader, $m)) {
            $keyId = $m[1];
        }

        $body = $request->getContent();
        $bodySummary = $body !== '' ? mb_strcut($body, 0, 500) : null;

        $relevantHeaders = array_filter([
            'content-type' => $request->header('content-type'),
            'content-digest' => $request->header('content-digest'),
            'date' => $request->header('date'),
            'user-agent' => $request->header('user-agent'),
        ]);

        InboxLog::create([
            'actor_id' => $actorId,
            'actor_url' => $actorUrl,
            'key_id' => $keyId,
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'status_code' => $statusCode,
            'reason' => $reason,
            'body_summary' => $bodySummary,
            'headers' => $relevantHeaders,
        ]);
    }
}
