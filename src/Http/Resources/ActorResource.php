<?php

namespace DanielPetrica\LaravelActivityPub\Http\Resources;

use DanielPetrica\LaravelActivityPub\Models\Actor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Actor $resource
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
            ],
            'id' => $url,
            'type' => 'Person',
            'preferredUsername' => $this->resource->username,
            'name' => $this->resource->name ?? $this->resource->username,
            'url' => $url,
            'inbox' => $this->resource->inbox_url,
            'outbox' => $this->resource->outbox_url,
            'followers' => $this->resource->followers_url,
            'following' => $this->resource->following_url,
            'manuallyApprovesFollowers' => $this->resource->manually_approves_followers,
            'publicKey' => [
                'id' => $this->resource->key_id,
                'type' => 'Key',
                'owner' => $url,
                'publicKeyPem' => $this->resource->public_key_pem,
            ],
            'endpoints' => [
                'sharedInbox' => rtrim(string: config(key: 'activitypub.domain'), characters: '/').'/inbox',
            ],
        ];

        if ($this->resource->summary !== null) {
            $actor['summary'] = $this->resource->summary;
        }

        if ($this->resource->icon_url !== null) {
            $actor['icon'] = [
                'type' => 'Image',
                'mediaType' => 'image/jpeg',
                'url' => $this->resource->icon_url,
            ];
        }

        if ($this->resource->image_url !== null) {
            $actor['image'] = [
                'type' => 'Image',
                'mediaType' => 'image/jpeg',
                'url' => $this->resource->image_url,
            ];
        }

        return $actor;
    }
}
