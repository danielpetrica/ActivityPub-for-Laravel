<?php

namespace DanielPetrica\LaravelActivityPub\Http\Resources;

use DanielPetrica\LaravelActivityPub\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Activity $resource
 */
final class ActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public static function make(Activity $activity, Request $request): array
    {
        $instance = new self(resource: $activity);

        $result = $instance->toArray(request: $request);

        $result['@context'] ??= 'https://www.w3.org/ns/activitystreams';

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = $this->resource->payload;

        if ($payload === null || $payload === []) {
            return [
                '@context' => 'https://www.w3.org/ns/activitystreams',
                'id' => $this->resource->actor->actor_id.'#'.$this->resource->type->value.'/'.$this->resource->id,
                'type' => $this->resource->type->value,
                'actor' => $this->resource->actor->actor_id,
                'object' => $this->resource->object_id ?? $this->resource->actor->actor_id,
                'published' => $this->resource->created_at->toIso8601String(),
            ];
        }

        return $payload;
    }
}
