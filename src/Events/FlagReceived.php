<?php

namespace DanielPetrica\LaravelActivityPub\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Emitted when a remote actor sends an inbound `Flag` activity (a report).
 *
 * Consumers can listen for this to notify moderators or run automation. The
 * event is best-effort: it carries the raw report identifiers and never
 * requires the referenced objects or actors to be resolvable.
 */
final class FlagReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $localActorId,
        public readonly ?string $remoteActorUrl,
        public readonly ?string $objectId,
        public readonly ?string $objectType,
        public readonly ?string $reason,
    ) {}
}
