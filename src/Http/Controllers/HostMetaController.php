<?php

namespace DanielPetrica\LaravelActivityPub\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

final class HostMetaController extends Controller
{
    public function __invoke(): Response
    {
        $domain = parse_url(url: config('activitypub.domain'), component: PHP_URL_HOST);
        $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'https';

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<XRD xmlns="http://docs.oasis-open.org/ns/xri/xrd-1.0">
    <Link rel="lrdd" type="application/xrd+xml" template="{$scheme}://{$domain}/.well-known/webfinger?resource={uri}"/>
</XRD>
XML;

        return response(content: $xml, headers: ['Content-Type' => 'application/xrd+xml']);
    }
}
