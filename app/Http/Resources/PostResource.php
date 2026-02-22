<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\Post $resource
 */
final class PostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'slug' => $this->resource->slug,
            'content_json' => $this->resource->content,
            'rendered_html' => $this->resource->rendered_html ?? null,
            'status' => $this->resource->status->value,
            'seo_metadata' => $this->resource->seo_metadata,
            'published_at' => $this->resource->published_at,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }
}
