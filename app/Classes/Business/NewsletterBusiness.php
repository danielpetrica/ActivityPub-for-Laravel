<?php

namespace App\Classes\Business;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class NewsletterBusiness
{
    /**
     * Subscribe an email address to the configured Mailcoach email list.
     *
     * @throws \RuntimeException when the Mailcoach API call fails
     */
    public static function subscribe(string $email): void
    {
        $endpoint = rtrim((string) config(key: 'mailcoach.endpoint'), '/');
        $token = (string) config(key: 'mailcoach.api_token');
        $emailListUuid = (string) config(key: 'mailcoach.email_list_uuid');

        if ($endpoint === '' || $token === '' || $emailListUuid === '') {
            Log::warning('NewsletterBusiness: missing Mailcoach configuration');

            // Soft-fail in non-configured environments to avoid blocking UI before keys are added
            return;
        }

        $url = $endpoint.'/email-lists/'.urlencode($emailListUuid).'/subscribers';

        Log::debug('NewsletterBusiness: subscribing email', [
            'email' => $email,
            'url' => Str::of($url)->replaceMatches(pattern: '/(api_token|token)=[^&]+/i', replace: '$1=***')->toString(),
        ]);

        $response = Http::acceptJson()
            ->withToken($token)
            ->post(url: $url, data: [
                'email' => $email,
            ]);

        if ($response->failed()) {
            Log::error('NewsletterBusiness: Mailcoach subscribe failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException(message: 'Unable to subscribe at this time.');
        }
    }
}
