<?php

namespace DanielPetrica\LaravelActivityPub\Helpers;

use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\InboxLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Records rejected inbox requests for operators.
 *
 * This runs on the rejection path (401/400/422), so it must never throw: a
 * failure to persist the audit row is swallowed after being reported to the
 * application log, leaving the real response intact.
 */
final class InboxLogger
{
    public static function logRejected(Request $request, int $statusCode, string $reason): void
    {
        if (! config('activitypub.inbox_log.enabled', true)) {
            return;
        }

        try {
            // The inbound signature's remote actor identifies who called, not
            // the local actor row the log row may reference.
            $remoteActor = $request->attributes->get('remote_actor');
            $actorUrl = null;

            if ($remoteActor !== null) {
                $actorUrl = $remoteActor->actor_url ?? null;
            }

            InboxLog::create([
                'actor_id' => self::resolveLocalActorId($request),
                'actor_url' => $actorUrl,
                'key_id' => self::extractKeyId($request),
                'ip' => $request->ip(),
                'method' => $request->method(),
                'path' => $request->path(),
                'status_code' => $statusCode,
                'reason' => $reason,
                'body_summary' => self::summarizeBody($request),
                'headers' => self::relevantHeaders($request),
            ]);
        } catch (\Throwable $e) {
            Log::warning(
                'ActivityPub: failed to record rejected inbox request.',
                ['error' => $e->getMessage(), 'status_code' => $statusCode],
            );
        }
    }

    /**
     * Best-effort local actor id for the inbox route, or null when the request
     * is not addressed to a resolvable local actor (e.g. the shared inbox).
     * Never invents an id, which previously produced a foreign key violation.
     */
    protected static function resolveLocalActorId(Request $request): ?int
    {
        $routeActor = $request->route('actor');

        if ($routeActor instanceof Actor) {
            return $routeActor->id;
        }

        if (is_string($routeActor) && $routeActor !== '') {
            $id = Actor::query()
                ->where('username', '=', $routeActor)
                ->value('id');

            return $id === null ? null : (int) $id;
        }

        return null;
    }

    protected static function extractKeyId(Request $request): ?string
    {
        $signatureInput = $request->header('signature-input');
        $signatureHeader = $request->header('signature');

        if (is_string($signatureInput) && preg_match('/keyid="([^"]+)"/', $signatureInput, $m)) {
            return $m[1];
        }

        if (is_string($signatureHeader) && preg_match('/keyId="([^"]+)"/', $signatureHeader, $m)) {
            return $m[1];
        }

        return null;
    }

    protected static function summarizeBody(Request $request): ?string
    {
        $body = $request->getContent();

        return $body !== '' ? mb_strcut($body, 0, 500) : null;
    }

    /**
     * @return array<string, string>
     */
    protected static function relevantHeaders(Request $request): array
    {
        $headers = array_filter([
            'content-type' => $request->header('content-type'),
            'content-digest' => $request->header('content-digest'),
            'date' => $request->header('date'),
            'user-agent' => $request->header('user-agent'),
        ]);

        return array_map(static fn ($value): string => (string) $value, $headers);
    }
}
