<?php

use App\Actions\RenderPostHtmlAction;

it('rewrites relative media/ image src to proxy URL', function () {
    $htmlInput = '<p><img src="media/content/2026/02/example.jpg" alt="Example"></p>';

    $ref = new ReflectionClass(RenderPostHtmlAction::class);
    $method = $ref->getMethod('rewriteImageSrcs');
    $method->setAccessible(true);

    $html = $method->invoke(null, $htmlInput);

    expect($html)
        ->toContain('/objectproxy/media/media/content/2026/02/example.jpg')
        ->and($html)
        ->toContain('<img');
});

it('rewrites raw S3 URLs to proxy URLs in rendered HTML', function () {
    $endpoint = config('filesystems.disks.hetzner.endpoint');
    $bucket = config('filesystems.disks.hetzner.bucket');
    $prefix = config('filesystems.disks.hetzner.prefix');
    $s3Url = rtrim($endpoint, '/').'/'.$bucket.'/'.$prefix.'/media/content/2025/06/old.png';

    $htmlInput = '<p><img src="'.$s3Url.'" alt="Old import"></p>';

    $ref = new ReflectionClass(RenderPostHtmlAction::class);
    $method = $ref->getMethod('rewriteImageSrcs');
    $method->setAccessible(true);

    $html = $method->invoke(null, $htmlInput);

    expect($html)
        ->toContain('/objectproxy/media/media/content/2025/06/old.png')
        ->and($html)
        ->toContain('<img');
});
