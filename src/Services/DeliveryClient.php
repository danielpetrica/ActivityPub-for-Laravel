<?php

namespace DanielPetrica\LaravelActivityPub\Services;

use DanielPetrica\LaravelActivityPub\Models\Actor;
use Illuminate\Support\Facades\Http;

final readonly class DeliveryClient
{
    public function __construct(
        private HttpSignatureService $httpSignatureService,
    ) {}

    public function deliver(string $inboxUrl, array $activity, Actor $actor): ?int
    {
        $body = json_encode($activity);

        if ($body === false) {
            return null;
        }

        $date = gmdate('D, d M Y H:i:s T');

        $digest = 'SHA-256='.base64_encode(hash('sha256', $body, binary: true));

        $headers = [
            'Content-Type' => 'application/activity+json',
            'Date' => $date,
            'Host' => parse_url($inboxUrl, PHP_URL_HOST),
            'Digest' => $digest,
        ];

        $signedHeaders = $this->httpSignatureService->sign(
            method: 'POST',
            url: $inboxUrl,
            headers: $headers,
            actor: $actor,
        );

        $response = Http::withHeaders($signedHeaders)
            ->timeout(config('activitypub.federation.delivery_timeout', 10))
            ->withBody($body, 'application/activity+json')
            ->post($inboxUrl);

        return $response->status();
    }
}
