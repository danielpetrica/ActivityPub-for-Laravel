<?php

use App\Classes\Business\MediaUrlBusiness;

it('generates media proxy URL for media disk', function () {
    $url = MediaUrlBusiness::forMedia('media/feature/2026/05/image.jpg');

    expect($url)->toBe('/objectproxy/media/media/feature/2026/05/image.jpg');
});

it('generates media proxy URL for og-images disk', function () {
    $url = MediaUrlBusiness::forOgImage('posts/my-post.png');

    expect($url)->toBe('/objectproxy/og-images/posts/my-post.png');
});

it('strips leading slashes from media paths', function () {
    $url = MediaUrlBusiness::forMedia('/media/feature/image.jpg');

    expect($url)->toBe('/objectproxy/media/media/feature/image.jpg');
});

it('strips leading slashes from og-image paths', function () {
    $url = MediaUrlBusiness::forOgImage('/posts/slug.png');

    expect($url)->toBe('/objectproxy/og-images/posts/slug.png');
});
