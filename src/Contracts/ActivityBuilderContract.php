<?php

namespace DanielPetrica\LaravelActivityPub\Contracts;

interface ActivityBuilderContract
{
    /** @return array<string, mixed> */
    public function follow(ActorContract $actor, string $objectUrl): array;

    /** @return array<string, mixed> */
    public function undoFollow(ActorContract $actor, string $objectUrl): array;

    /** @return array<string, mixed> */
    public function accept(ActorContract $actor, array $originalPayload): array;

    /** @return array<string, mixed> */
    public function reject(ActorContract $actor, array $originalPayload): array;

    /** @return array<string, mixed> */
    public function like(ActorContract $actor, string $objectUrl): array;

    /** @return array<string, mixed> */
    public function announce(ActorContract $actor, string $objectUrl): array;

    /** @return array<string, mixed> */
    public function delete(ActorContract $actor, string $objectId): array;

    /** @return array<string, mixed> */
    public function createNote(ActorContract $actor, string $content, string $inReplyToUrl, array $to, array $cc = []): array;
}
