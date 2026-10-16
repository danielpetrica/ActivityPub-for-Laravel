<?php

namespace DanielPetrica\LaravelActivityPub;

use DanielPetrica\LaravelActivityPub\Services\ActivityPubService;
use Illuminate\Support\Facades\Facade;

/**
 * Static facade for the ActivityPub federation service.
 *
 * Exposes the supported public API surface. Every current public method of
 * {@see ActivityPubService} is proxied below.
 *
 * Low-level primitives are called out as "@internal" in their description and
 * exist for extension, diagnostics and tests; application code should prefer
 * the `send*` helpers.
 *
 * @api
 *
 * @method static void sendCreate(\DanielPetrica\LaravelActivityPub\Contracts\FederatableContentContract $content)
 * @method static void sendUpdate(\DanielPetrica\LaravelActivityPub\Contracts\FederatableContentContract $content)
 * @method static void sendDelete(string $objectId, \DanielPetrica\LaravelActivityPub\Contracts\ActorContract $actor)
 * @method static \DanielPetrica\LaravelActivityPub\Models\Activity sendCreateForActor(\DanielPetrica\LaravelActivityPub\Contracts\FederatableContentContract $content, \DanielPetrica\LaravelActivityPub\Models\Actor $actor, ?\DanielPetrica\LaravelActivityPub\Models\RemoteActor $target = null, ?string $to = null)
 * @method static array{record: \DanielPetrica\LaravelActivityPub\Models\Activity, results: list<array{actor_url: string, inbox_url: string, response_code: int|null}>} sendCreateForActorSync(\DanielPetrica\LaravelActivityPub\Contracts\FederatableContentContract $content, \DanielPetrica\LaravelActivityPub\Models\Actor $actor)
 * @method static void handleInbox(array<string, mixed> $payload)
 * @method static ?\DanielPetrica\LaravelActivityPub\Models\RemoteActor resolveRemoteActor(string $actorUri)
 * @method static ?array<string, mixed> resolveWebFinger(string $resource)
 * @method static \DanielPetrica\LaravelActivityPub\Models\Activity recordActivity(\DanielPetrica\LaravelActivityPub\Models\Actor $localActor, \DanielPetrica\LaravelActivityPub\Enums\ActivityType $type, ?\DanielPetrica\LaravelActivityPub\Models\RemoteActor $remoteActor, array<string, mixed> $payload, bool $isIncoming = false) @internal Persist an activity without dispatching it.
 * @method static void sendFollow(\DanielPetrica\LaravelActivityPub\Contracts\ActorContract $actor, \DanielPetrica\LaravelActivityPub\Models\RemoteActor $target)
 * @method static void sendUnfollow(\DanielPetrica\LaravelActivityPub\Contracts\ActorContract $actor, \DanielPetrica\LaravelActivityPub\Models\Follower $follower)
 * @method static void sendBlock(\DanielPetrica\LaravelActivityPub\Contracts\ActorContract $actor, string $objectUrl)
 * @method static void sendUnblock(\DanielPetrica\LaravelActivityPub\Contracts\ActorContract $actor, \DanielPetrica\LaravelActivityPub\Models\RemoteActor $remoteActor)
 * @method static bool approveFollower(\DanielPetrica\LaravelActivityPub\Contracts\ActorContract $actor, \DanielPetrica\LaravelActivityPub\Models\Follower $follower)
 * @method static bool rejectFollower(\DanielPetrica\LaravelActivityPub\Contracts\ActorContract $actor, \DanielPetrica\LaravelActivityPub\Models\Follower $follower)
 * @method static void forwardRecentPostsToNewFollower(\DanielPetrica\LaravelActivityPub\Models\Actor $actor, \DanielPetrica\LaravelActivityPub\Models\RemoteActor $remoteActor) @internal Forward recent posts to a newly accepted follower.
 * @method static void updateActorProfile(\DanielPetrica\LaravelActivityPub\Models\Actor $actor)
 * @method static void sendMove(\DanielPetrica\LaravelActivityPub\Contracts\ActorContract $actor, string $targetUrl, array<int, string> $alsoKnownAs = [])
 * @method static void sendLike(\DanielPetrica\LaravelActivityPub\Contracts\ActorContract $actor, string $objectUrl)
 * @method static void sendAnnounce(\DanielPetrica\LaravelActivityPub\Contracts\ActorContract $actor, string $objectUrl)
 * @method static void sendNote(\DanielPetrica\LaravelActivityPub\Contracts\ActorContract $actor, string $content, string $inReplyToUrl)
 *
 * @see ActivityPubService
 */
final class ActivityPub extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'activitypub';
    }
}
