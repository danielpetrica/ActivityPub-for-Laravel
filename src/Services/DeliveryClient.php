<?php

namespace DanielPetrica\LaravelActivityPub\Services;

use DanielPetrica\LaravelActivityPub\Helpers\SsrfGuard;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final readonly class DeliveryClient
{
    public function __construct(
        private HttpSignatureService $httpSignatureService,
    ) {}

    /**
     * @return array{status: int, body: string}|null
     */
    public function deliver(string $inboxUrl, array $activity, Actor $actor): ?array
    {
        // Never sign or POST to a private / loopback / link-local target. The
        // returned options pin the connection to the validated IP so Guzzle
        // cannot re-resolve the host (DNS rebinding) after this check.
        [$safe, $pinOptions] = SsrfGuard::validateAndPin(url: $inboxUrl, requireHttps: true);

        if (! $safe) {
            Log::warning('DeliveryClient: refusing to deliver to unsafe inbox URL', [
                'inboxUrl' => $inboxUrl,
            ]);

            return null;
        }

        $body = json_encode($activity);

        if ($body === false) {
            return null;
        }

        $digest = 'SHA-256='.base64_encode(hash('sha256', $body, true));

        $headers = [
            'Content-Type' => 'application/activity+json',
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
            ->withOptions(array_merge(['allow_redirects' => false], $pinOptions))
            ->withBody($body, 'application/activity+json')
            ->post($inboxUrl);

        return [
            'status' => $response->status(),
            'body' => $response->body(),
        ];
    }
}
