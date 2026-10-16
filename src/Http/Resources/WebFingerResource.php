<?php

namespace DanielPetrica\LaravelActivityPub\Http\Resources;

use DanielPetrica\LaravelActivityPub\Models\Actor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Actor $resource
 */
final class WebFingerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $parsedUrl = parse_url($this->resource->actor_id);
        $host = $parsedUrl['host'] ?? '';
        $scheme = $parsedUrl['scheme'] ?? 'https';
        $domain = $host;
        if (isset($parsedUrl['port'])) {
            $domain .= ':'.$parsedUrl['port'];
        }

        return [
            'subject' => 'acct:'.$this->resource->username.'@'.$domain,
            'aliases' => [
                $this->resource->actor_id,
            ],
            'links' => [
                [
                    'rel' => 'self',
                    'type' => 'application/activity+json',
                    'href' => $this->resource->actor_id,
                ],
                [
                    // Human-readable profile page, per the WebFinger/Pleroma
                    // convention understood by Mastodon-family clients.
                    'rel' => 'http://webfinger.net/rel/profile-page',
                    'type' => 'text/html',
                    'href' => $this->resource->getProfileUrl(),
                ],
                [
                    // Remote follow / interaction template (OStatus).
                    'rel' => 'http://ostatus.org/schema/1.0/subscribe',
                    'template' => $scheme.'://'.$domain.'/authorize_interaction?uri={uri}',
                ],
            ],
        ];
    }
}
