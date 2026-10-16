<?php

namespace DanielPetrica\LaravelActivityPub\Services;

use DanielPetrica\LaravelActivityPub\Contracts\ActivityBuilderContract;
use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;
use DanielPetrica\LaravelActivityPub\Contracts\FederatableContentContract;
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
     * Build an Undo(Follow) activity.
     *
     * The Undo MUST reference the id of the original Follow activity that is
     * being undone. Callers that have the real Follow id (e.g. read back from
     * the recorded activity payload) should pass it via $followId. When it is
     * not available we fall back to a deterministic, fabricated id so the
     * envelope is still structurally valid.
     *
     * @return array<string, mixed>
     */
    public function undoFollow(ActorContract $actor, string $objectUrl, ?string $followId = null): array
    {
        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $actor->getActorId().'#undo-follow/'.Str::uuid(),
            'type' => 'Undo',
            'actor' => $actor->getActorId(),
            'object' => [
                'id' => $followId ?? $objectUrl.'#follow',
                'type' => 'Follow',
                'actor' => $actor->getActorId(),
                'object' => $objectUrl,
            ],
            'to' => [$objectUrl],
        ];
    }

    /**
     * Wrap an arbitrary activity in an Undo envelope.
     *
     * @param  array<string, mixed>  $activity
     * @return array<string, mixed>
     */
    public function undo(ActorContract $actor, array $activity): array
    {
        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $actor->getActorId().'#undo/'.Str::uuid(),
            'type' => 'Undo',
            'actor' => $actor->getActorId(),
            'object' => $activity,
            'to' => $this->addressingFor(activity: $activity),
        ];
    }

    /**
     * Build a Block activity targeting the given remote actor.
     *
     * @return array<string, mixed>
     */
    public function block(ActorContract $actor, string $objectUrl): array
    {
        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $actor->getActorId().'#block/'.Str::uuid(),
            'type' => 'Block',
            'actor' => $actor->getActorId(),
            'object' => $objectUrl,
            'to' => [$objectUrl],
        ];
    }

    /**
     * Build an Undo(Block) activity wrapping the Block it undoes.
     *
     * @return array<string, mixed>
     */
    public function undoBlock(ActorContract $actor, string $objectUrl): array
    {
        return $this->undo(actor: $actor, activity: $this->block(actor: $actor, objectUrl: $objectUrl));
    }

    /**
     * Build a Move activity that migrates the local actor to a new IRI.
     *
     * `object` is the actor being moved (the ActivityStreams convention),
     * `target` is the destination actor. `to` addresses both the followers
     * collection and the target actor so the new instance learns about it.
     *
     * @param  array<int, string>  $alsoKnownAs
     * @return array<string, mixed>
     */
    public function move(ActorContract $actor, string $targetUrl, array $alsoKnownAs = []): array
    {
        $activity = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $actor->getActorId().'#move/'.Str::uuid(),
            'type' => 'Move',
            'actor' => $actor->getActorId(),
            'object' => $actor->getActorId(),
            'target' => $targetUrl,
            'to' => [$actor->getFollowersUrl(), $targetUrl],
        ];

        $aliases = array_values(array_filter(
            $alsoKnownAs,
            static fn (string $entry): bool => $entry !== '',
        ));

        if ($aliases !== []) {
            $activity['alsoKnownAs'] = $aliases;
        }

        return $activity;
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
            'id' => $actor->getActorId().'#announce/'.Str::uuid(),
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
     * @param  array<int, string>  $to
     * @param  array<int, string>  $cc
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

        if ($this->isSensitive(source: $actor)) {
            $note['sensitive'] = true;
        }

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

    /**
     * Build the object payload for an outbound Create/Update.
     *
     * @param  array<int, array{href: string, name: string, inbox: string|null}>  $mentions
     * @return array<string, mixed>
     */
    public function buildObject(FederatableContentContract $content, array $mentions = []): array
    {
        $object = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $content->getActivityPubId(),
            'type' => $content->getActivityPubType(),
            'attributedTo' => $content->getActivityPubAttributedTo(),
            'name' => $content->getActivityPubName(),
            'content' => $content->getActivityPubContent(),
            'summary' => $content->getActivityPubSummary(),
            'url' => $content->getActivityPubUrl(),
            'published' => $content->getActivityPubPublishedAt(),
            'to' => $this->normalizeAddresses(value: [$content->getActivityPubTo()]),
            'cc' => $this->normalizeAddresses(value: [$content->getActivityPubCc()]),
        ];

        $object = array_filter($object, fn ($value) => $value !== null);

        if ($this->isSensitive(source: $content)) {
            $object['sensitive'] = true;
        }

        $attachments = $content->getActivityPubAttachments();

        if ($attachments !== []) {
            $object['attachment'] = $attachments;
        }

        $tags = $content->getActivityPubTags();

        if ($tags !== []) {
            $object['tag'] = $tags;
        }

        $emojiTags = $this->emojiTags(source: $content);

        if ($emojiTags !== []) {
            $object['tag'] = array_merge(
                $object['tag'] ?? [],
                $emojiTags,
            );
        }

        if ($mentions !== []) {
            $object['tag'] = array_merge(
                $object['tag'] ?? [],
                $this->mentionTags(mentions: $mentions),
            );

            $object['cc'] = array_values(array_unique(array_merge(
                $object['cc'],
                $this->mentionHrefs(mentions: $mentions),
            )));
        }

        return $object;
    }

    /**
     * Wrap a built object in a Create/Update activity envelope.
     *
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    public function buildActivity(string $type, ActorContract $actor, array $object): array
    {
        $to = $this->normalizeAddresses(value: $object['to'] ?? null);
        $cc = $this->normalizeAddresses(value: $object['cc'] ?? null);

        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $actor->getActorId().'#'.$type.'/'.Str::uuid(),
            'type' => $type,
            'actor' => $actor->getActorId(),
            'object' => $object,
            'to' => $to !== [] ? $to : ['https://www.w3.org/ns/activitystreams#Public'],
            'cc' => $cc !== [] ? $cc : [$actor->getFollowersUrl()],
        ];
    }

    /**
     * Wrap an actor profile object in an Update activity envelope.
     *
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    public function updateActor(ActorContract $actor, array $object): array
    {
        return $this->buildActivity(type: 'Update', actor: $actor, object: $object);
    }

    /**
     * Fold mention tags/addressing into an already-built activity.
     *
     * @param  array<string, mixed>  $activity
     * @param  array<int, array{href: string, name: string, inbox: string|null}>  $mentions
     * @return array<string, mixed>
     */
    public function attachMentions(array $activity, array $mentions): array
    {
        if ($mentions === []) {
            return $activity;
        }

        $tags = $this->mentionTags(mentions: $mentions);
        $hrefs = $this->mentionHrefs(mentions: $mentions);

        if (isset($activity['object']) && is_array($activity['object'])) {
            $activity['object']['tag'] = array_values(array_merge(
                $activity['object']['tag'] ?? [],
                $tags,
            ));

            $activity['object']['cc'] = array_values(array_unique(array_merge(
                $activity['object']['cc'] ?? [],
                $hrefs,
            )));
        }

        $activity['cc'] = array_values(array_unique(array_merge(
            $activity['cc'] ?? [],
            $hrefs,
        )));

        return $activity;
    }

    /**
     * Object-level `sensitive` (NSFW) flag, opt-in.
     *
     * When the content model (or actor) exposes `getActivityPubSensitive()`,
     * `isActivityPubSensitive()`, or a `sensitive` / `activitypub_sensitive`
     * attribute, the value is folded into the object. Absent by default, so the
     * wire format of existing models is unchanged.
     */
    private function isSensitive(mixed $source): bool
    {
        if (! is_object($source)) {
            return false;
        }

        if (method_exists($source, 'getActivityPubSensitive')) {
            return (bool) $source->getActivityPubSensitive();
        }

        if (method_exists($source, 'isActivityPubSensitive')) {
            return (bool) $source->isActivityPubSensitive();
        }

        if (method_exists($source, 'getAttribute')) {
            $value = $source->getAttribute('sensitive') ?? $source->getAttribute('activitypub_sensitive');

            return $value !== null && (bool) $value;
        }

        return false;
    }

    /**
     * Custom emoji tags, opt-in.
     *
     * Reads a shortcode => URL map from `getActivityPubEmoji()`,
     * `getActivityPubEmojis()` or an `activitypub_emoji` attribute and renders
     * ActivityStreams `Emoji` tag objects. Malformed or empty entries are
     * skipped, never thrown.
     *
     * @return array<int, array{type: string, name: string, icon: array{type: string, url: string}}>
     */
    private function emojiTags(mixed $source): array
    {
        if (! is_object($source)) {
            return [];
        }

        if (method_exists($source, 'getActivityPubEmoji')) {
            $raw = $source->getActivityPubEmoji();
        } elseif (method_exists($source, 'getActivityPubEmojis')) {
            $raw = $source->getActivityPubEmojis();
        } elseif (method_exists($source, 'getAttribute')) {
            $raw = $source->getAttribute('activitypub_emoji');
        } else {
            $raw = null;
        }

        if (! is_array($raw)) {
            return [];
        }

        $tags = [];

        foreach ($raw as $shortcode => $value) {
            $url = is_string($value) ? $value : (is_array($value) ? ($value['url'] ?? null) : null);

            if (! is_string($shortcode) || ! is_string($url)) {
                continue;
            }

            $shortcode = trim($shortcode, ':');

            if ($shortcode === '' || $url === '') {
                continue;
            }

            $tags[] = [
                'type' => 'Emoji',
                'name' => ':'.$shortcode.':',
                'icon' => [
                    'type' => 'Image',
                    'url' => $url,
                ],
            ];
        }

        return $tags;
    }

    /**
     * Flatten an addressing value into a list of unique, non-empty IRI strings.
     *
     * @return array<int, string>
     */
    private function normalizeAddresses(mixed $value): array
    {
        if (is_string($value)) {
            $value = [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        $addresses = [];

        array_walk_recursive($value, function ($item) use (&$addresses): void {
            if (is_string($item) && $item !== '') {
                $addresses[] = $item;
            }
        });

        return array_values(array_unique($addresses));
    }

    /**
     * @param  array<int, array{href: string, name: string, inbox: string|null}>  $mentions
     * @return array<int, array{type: string, href: string, name: string}>
     */
    private function mentionTags(array $mentions): array
    {
        return array_map(
            fn (array $mention): array => [
                'type' => 'Mention',
                'href' => $mention['href'],
                'name' => $mention['name'],
            ],
            $mentions,
        );
    }

    /**
     * @param  array<int, array{href: string, name: string, inbox: string|null}>  $mentions
     * @return array<int, string>
     */
    private function mentionHrefs(array $mentions): array
    {
        return array_values(array_unique(array_column($mentions, 'href')));
    }

    /**
     * Extract addressing (to/cc) from an activity so an Undo envelope can
     * address the same recipients.
     *
     * @param  array<string, mixed>  $activity
     * @return array<int, string>
     */
    private function addressingFor(array $activity): array
    {
        foreach (['to', 'cc'] as $field) {
            $value = $activity[$field] ?? null;

            if (is_array($value)) {
                $addresses = array_values(array_filter($value, 'is_string'));

                if ($addresses !== []) {
                    return $addresses;
                }
            }
        }

        $object = $activity['object'] ?? null;

        if (is_string($object) && $object !== '') {
            return [$object];
        }

        return [];
    }
}
