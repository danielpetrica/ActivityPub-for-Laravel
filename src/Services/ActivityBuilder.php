<?php

namespace DanielPetrica\LaravelActivityPub\Services;

use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;
use DanielPetrica\LaravelActivityPub\Contracts\ActivityBuilderContract;
use Illuminate\Support\Str;

final class ActivityBuilder implements ActivityBuilderContract
{
    /**
     * @return array<string, mixed>
     */
    public function follow(ActorContract $actor, string $objectUrl): array
    {
        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $actor->getActorId().'#follow/'.Str::uuid(),
            'type' => 'Follow',
            'actor' => $actor->getActorId(),
            'object' => $objectUrl,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function undoFollow(ActorContract $actor, string $objectUrl): array
    {
        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $actor->getActorId().'#undo-follow/'.time(),
            'type' => 'Undo',
            'actor' => $actor->getActorId(),
            'object' => [
                'id' => $objectUrl.'#follow',
                'type' => 'Follow',
                'actor' => $actor->getActorId(),
                'object' => $objectUrl,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function accept(ActorContract $actor, array $originalPayload): array
    {
        $remoteActorUrl = is_array($originalPayload['actor'] ?? null)
            ? ($originalPayload['actor']['url'] ?? $originalPayload['actor']['id'] ?? null)
            : ($originalPayload['actor'] ?? null);

        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $actor->getActorId().'#accepts/'.Str::uuid(),
            'type' => 'Accept',
            'actor' => $actor->getActorId(),
            'object' => $originalPayload,
            'to' => $remoteActorUrl ? [$remoteActorUrl] : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function reject(ActorContract $actor, array $originalPayload): array
    {
        $remoteActorUrl = is_array($originalPayload['actor'] ?? null)
            ? ($originalPayload['actor']['url'] ?? $originalPayload['actor']['id'] ?? null)
            : ($originalPayload['actor'] ?? null);

        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $actor->getActorId().'#rejects/'.Str::uuid(),
            'type' => 'Reject',
            'actor' => $actor->getActorId(),
            'object' => $originalPayload,
            'to' => $remoteActorUrl ? [$remoteActorUrl] : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function like(ActorContract $actor, string $objectUrl): array
    {
        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $actor->getActorId().'#like/'.Str::uuid(),
            'type' => 'Like',
            'actor' => $actor->getActorId(),
            'object' => $objectUrl,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function announce(ActorContract $actor, string $objectUrl): array
    {
        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $actor->getActorId().'#announce/'.time(),
            'type' => 'Announce',
            'actor' => $actor->getActorId(),
            'object' => $objectUrl,
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
            'cc' => [$actor->getFollowersUrl()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(ActorContract $actor, string $objectId): array
    {
        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $actor->getActorId().'#delete/'.Str::uuid(),
            'type' => 'Delete',
            'actor' => $actor->getActorId(),
            'object' => [
                'id' => $objectId,
                'type' => 'Tombstone',
            ],
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createNote(ActorContract $actor, string $content, string $inReplyToUrl, array $to, array $cc = []): array
    {
        $noteId = $actor->getActorId().'/note/'.Str::uuid();

        $note = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $noteId,
            'type' => 'Note',
            'content' => $content,
            'inReplyTo' => $inReplyToUrl,
            'attributedTo' => $actor->getActorId(),
            'to' => $to,
            'cc' => $cc,
            'url' => $noteId,
            'published' => now()->toIso8601String(),
        ];

        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $actor->getActorId().'#create/'.Str::uuid(),
            'type' => 'Create',
            'actor' => $actor->getActorId(),
            'object' => $note,
            'to' => $to,
            'cc' => $cc,
        ];
    }
}
