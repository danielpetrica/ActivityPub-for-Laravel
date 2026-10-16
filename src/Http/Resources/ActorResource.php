<?php

namespace DanielPetrica\LaravelActivityPub\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serialises an actor model into an ActivityStreams 2.0 actor document.
 *
 * The underlying resource is intentionally not narrowed to the package's own
 * `Actor` model: consumers may swap in a custom actor model, and the optional
 * `sensitive` / `alsoKnownAs` extensions are detected on it dynamically.
 */
final class ActorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $url = $this->resource->actor_id;

        $actor = [
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                'https://w3id.org/security/v1',
                'https://w3id.org/security/v2',
                ['discoverable' => 'http://joinmastodon.org/ns#discoverable'],
            ],
            'id' => $url,
            'type' => config('activitypub.actor_type', 'Person'),
            'preferredUsername' => $this->resource->username,
            'name' => $this->resource->name ?? $this->resource->username,
            'url' => $this->resource->getProfileUrl(),
            // inbox/outbox are collection IRIs. Emitting the collections inline
            // would require loading their contents (and produced a stale
            // `totalItems: 0`); strict AS2 consumers dereference the IRI.
            'inbox' => $this->resource->inbox_url,
            'outbox' => $this->resource->outbox_url,
            'followers' => $this->resource->followers_url,
            'following' => $this->resource->following_url,
            'featured' => $url.'/featured',
            'manuallyApprovesFollowers' => $this->resource->manually_approves_followers,
            'published' => $this->resource->published?->toIso8601String(),
            'discoverable' => $this->resource->discoverable,
            'publicKey' => [
                'id' => $this->resource->key_id,
                'type' => 'Key',
                'owner' => $url,
                'publicKeyPem' => $this->resource->public_key_pem,
            ],
            'endpoints' => [
                'sharedInbox' => rtrim(config('activitypub.domain'), '/').'/inbox',
            ],
        ];

        if ($this->resource->summary !== null) {
            $actor['summary'] = $this->resource->summary;
        }

        if ($this->resource->icon_url !== null) {
            $actor['icon'] = [
                'type' => 'Image',
                'mediaType' => $this->mediaType(url: $this->resource->icon_url),
                'url' => $this->resource->icon_url,
            ];
        }

        if ($this->resource->image_url !== null) {
            $actor['image'] = [
                'type' => 'Image',
                'mediaType' => $this->mediaType(url: $this->resource->image_url),
                'url' => $this->resource->image_url,
            ];
        }

        // Opt-in flags. Neither is part of the base actor model, so they only
        // appear when a consumer's model explicitly exposes them — keeping the
        // default payload unchanged.
        if ($this->isSensitive()) {
            $actor['sensitive'] = true;
        }

        $alsoKnownAs = $this->alsoKnownAs();

        if ($alsoKnownAs !== []) {
            $actor['alsoKnownAs'] = $alsoKnownAs;
        }

        return $actor;
    }

    /**
     * Derive the media type from the image URL extension instead of assuming
     * JPEG, which misled consumers about webp/png/gif/svg avatars.
     */
    protected function mediaType(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo(is_string($path) ? $path : '', PATHINFO_EXTENSION));

        return match ($extension) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'avif' => 'image/avif',
            'svg' => 'image/svg+xml',
            default => 'image/jpeg',
        };
    }

    /**
     * `sensitive` is opt-in: a consumer model may implement
     * `getActivityPubSensitive(): bool`, `isActivityPubSensitive(): bool`, or
     * expose a `sensitive` / `activitypub_sensitive` attribute. When absent the
     * property is omitted entirely.
     */
    protected function isSensitive(): bool
    {
        $resource = $this->resource;

        if (method_exists($resource, 'getActivityPubSensitive')) {
            return (bool) $resource->getActivityPubSensitive();
        }

        if (method_exists($resource, 'isActivityPubSensitive')) {
            return (bool) $resource->isActivityPubSensitive();
        }

        $value = $resource->getAttribute('sensitive') ?? $resource->getAttribute('activitypub_sensitive');

        return $value !== null && (bool) $value;
    }

    /**
     * `alsoKnownAs` is opt-in: resolved from `getActivityPubAlsoKnownAs()`,
     * `getAlsoKnownAs()` or an `also_known_as` attribute when present.
     *
     * @return array<int, string>
     */
    protected function alsoKnownAs(): array
    {
        $resource = $this->resource;

        if (method_exists($resource, 'getActivityPubAlsoKnownAs')) {
            $value = $resource->getActivityPubAlsoKnownAs();
        } elseif (method_exists($resource, 'getAlsoKnownAs')) {
            $value = $resource->getAlsoKnownAs();
        } else {
            $value = $resource->getAttribute('also_known_as');
        }

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
