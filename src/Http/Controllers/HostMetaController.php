<?php

namespace DanielPetrica\LaravelActivityPub\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

final class HostMetaController extends Controller
{
    public function __invoke(): Response
    {
        // Derive both the scheme and the host from the same source so the
        // template can never disagree (e.g. https host served over http).
        $domainUrl = (string) config('activitypub.domain');
        $parts = parse_url($domainUrl);

        $host = is_array($parts) && isset($parts['host'])
            ? $parts['host']
            : $domainUrl;

        $scheme = is_array($parts) && isset($parts['scheme'])
            ? $parts['scheme']
            : (parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https');

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<XRD xmlns="http://docs.oasis-open.org/ns/xri/xrd-1.0">
    <Link rel="lrdd" type="application/jrd+json" template="{$scheme}://{$host}/.well-known/webfinger?resource={uri}"/>
</XRD>
XML;

        $headers = ['Content-Type' => 'application/xrd+xml'];

        if (config('activitypub.cache.enabled', true)) {
            $headers['Cache-Control'] = 'public, max-age='.config('activitypub.cache.ttl', 86400);
        }

        return response($xml, 200, $headers);
    }
}
