<?php

namespace DanielPetrica\LaravelActivityPub\Contracts;

interface ActivityBuilderContract
{
    /** @return array<string, mixed> */
    public function follow(ActorContract $actor, string $objectUrl): array;

    /** @return array<string, mixed> */
    public function undoFollow(ActorContract $actor, string $objectUrl, ?string $followId = null): array;

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

    /** @param array<string, mixed> $activity @return array<string, mixed> */
    public function undo(ActorContract $actor, array $activity): array;

    /** @return array<string, mixed> */
    public function block(ActorContract $actor, string $objectUrl): array;

    /**
     * Build a Move activity migrating the local actor to a new IRI.
     *
     * @param  array<int, string>  $alsoKnownAs  additional actor IRIs the new account is known by
     * @return array<string, mixed>
     */
    public function move(ActorContract $actor, string $targetUrl, array $alsoKnownAs = []): array;

    /** @return array<string, mixed> */
    public function undoBlock(ActorContract $actor, string $objectUrl): array;

    /**
     * @param  array<int, string>  $to
     * @param  array<int, string>  $cc
     * @return array<string, mixed>
     */
    public function createNote(ActorContract $actor, string $content, string $inReplyToUrl, array $to, array $cc = []): array;

    /**
     * Build the object payload (e.g. a Note) for an outbound Create/Update.
     *
     * Any already-resolved mentions are folded into the object `tag` and `cc`
     * fields so overriding the builder changes the wire format end to end.
     *
     * @param  array<int, array{href: string, name: string, inbox: string|null}>  $mentions
     * @return array<string, mixed>
     */
    public function buildObject(FederatableContentContract $content, array $mentions = []): array;

    /**
     * Wrap a built object in a Create/Update activity envelope.
     *
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    public function buildActivity(string $type, ActorContract $actor, array $object): array;

    /**
     * Wrap an actor profile object in an Update activity envelope.
     *
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    public function updateActor(ActorContract $actor, array $object): array;

    /**
     * Fold mention tags/addressing into an already-built activity.
     *
     * @param  array<string, mixed>  $activity
     * @param  array<int, array{href: string, name: string, inbox: string|null}>  $mentions
     * @return array<string, mixed>
     */
    public function attachMentions(array $activity, array $mentions): array;
}
