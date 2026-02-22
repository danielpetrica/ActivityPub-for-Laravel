<?php

use App\Actions\RenderPostHtmlAction;

it('rewrites relative image src to storage asset url in rendered HTML', function () {
    $htmlInput = '<p><img src="media/content/2026/02/example.jpg" alt="Example"></p>';

    $ref = new ReflectionClass(RenderPostHtmlAction::class);
    $method = $ref->getMethod('rewriteImageSrcs');
    $method->setAccessible(true);

    $html = $method->invoke(null, $htmlInput);

    expect($html)
        ->toContain('/storage/media/content/2026/02/example.jpg')
        ->and($html)
        ->toContain('<img');
});
