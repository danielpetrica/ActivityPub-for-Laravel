<?php

namespace DanielPetrica\LaravelActivityPub\Actions\Handlers;

use DanielPetrica\LaravelActivityPub\Enums\ActivityType;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Services\ActivityPubService;
use DanielPetrica\LaravelActivityPub\Services\RemoteActorResolver;
use Illuminate\Support\Facades\Log;

/**
 * Handles an inbound `Move` activity (an account migration).
 *
 * Recording is intentionally defensive: the mover, the `target` (movedTo) and
 * `alsoKnownAs` may be missing or unresolvable. We record whatever we can and
 * never throw. If the mover is a known remote actor we persist the target and
 * the alsoKnownAs aliases so operators (and future follower migration) can act
 * on them.
 *
 * Automatic follower migration is intentionally NOT performed here: moving a
 * follower to a target that we cannot verify would silently redirect delivery
 * to a potentially hostile inbox. The recorded `moved_to` is the safe,
 * auditable building block.
 */
final class HandleMoveAction implements ActivityHandler
{
    public function __construct(
        private ActivityPubService $activityPubService,
        private RemoteActorResolver $remoteActorResolver,
    ) {}

    public function handles(): string
    {
        return 'Move';
    }

    /** @param array<string, mixed> $payload */
    public function handle(Actor $actor, array $payload): void
    {
        $remoteActor = null;

        try {
            $remoteActor = $this->remoteActorResolver->resolveFromPayload(payload: $payload);
        } catch (\Throwable $e) {
            Log::warning('HandleMoveAction: could not resolve moving actor', [
                'error' => $e->getMessage(),
            ]);
        }

        $target = $this->extractTarget(payload: $payload);
        $alsoKnownAs = $this->extractAlsoKnownAs(payload: $payload);

        if ($remoteActor !== null && ($target !== null || $alsoKnownAs !== [])) {
            try {
                $remoteActor->forceFill(array_filter([
                    'moved_to' => $target,
                    'also_known_as' => $alsoKnownAs !== [] ? $alsoKnownAs : null,
                ]))->save();
            } catch (\Throwable $e) {
                Log::warning('HandleMoveAction: could not persist move target', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $this->activityPubService->recordActivity(
                localActor: $actor,
                type: ActivityType::Move,
                remoteActor: $remoteActor,
                payload: $payload,
                isIncoming: true,
            );
        } catch (\Throwable $e) {
            Log::warning('HandleMoveAction: could not record Move activity', [
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('HandleMoveAction: inbound account migration received', [
            'local_actor_id' => $actor->id,
            'mover' => $remoteActor?->actor_url,
            'target' => $target,
            'also_known_as' => $alsoKnownAs,
        ]);
    }

    /**
     * Resolve the `target` of the Move. Accepts an explicit `target` string, a
     * `movedTo` string, or an embedded object exposing `id`.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function extractTarget(array $payload): ?string
    {
        $candidates = [
            $payload['target'] ?? null,
            $payload['movedTo'] ?? null,
        ];

        $object = $payload['object'] ?? null;

        if (is_array($object)) {
            $candidates[] = $object['target'] ?? null;
            $candidates[] = $object['movedTo'] ?? null;
            $candidates[] = $object['id'] ?? null;
        }

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }

            if (is_array($candidate) && is_string($candidate['id'] ?? null) && $candidate['id'] !== '') {
                return $candidate['id'];
            }
        }

        return null;
    }

    /**
     * Extract `alsoKnownAs` from the activity or its embedded object.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    protected function extractAlsoKnownAs(array $payload): array
    {
        $object = $payload['object'] ?? null;

        $value = $payload['alsoKnownAs']
            ?? (is_array($object) ? ($object['alsoKnownAs'] ?? null) : null);

        if (is_string($value)) {
            $value = [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            $value,
            static fn (mixed $entry): bool => is_string($entry) && $entry !== '',
        ));
    }
}
