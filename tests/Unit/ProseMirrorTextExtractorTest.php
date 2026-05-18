<?php

use App\Classes\SEO\ProseMirrorTextExtractor;

it('extracts plain text from ProseMirror JSON array', function () {
    $content = [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'paragraph',
                'content' => [
                    ['type' => 'text', 'text' => 'Hello world'],
                ],
            ],
            [
                'type' => 'paragraph',
                'content' => [
                    ['type' => 'text', 'text' => 'Second paragraph'],
                ],
            ],
        ],
    ];

    $result = ProseMirrorTextExtractor::extract($content);

    expect($result)->toBe("Hello world\nSecond paragraph");
});

it('extracts plain text from ProseMirror JSON string', function () {
    $content = json_encode([
        'type' => 'doc',
        'content' => [
            [
                'type' => 'paragraph',
                'content' => [
                    ['type' => 'text', 'text' => 'Test content'],
                ],
            ],
        ],
    ]);

    $result = ProseMirrorTextExtractor::extract($content);

    expect($result)->toBe('Test content');
});

it('handles null content gracefully', function () {
    expect(ProseMirrorTextExtractor::extract(null))->toBe('');
});

it('handles empty string content', function () {
    expect(ProseMirrorTextExtractor::extract(''))->toBe('');
});

it('strips HTML tags from string content', function () {
    $result = ProseMirrorTextExtractor::extract('<p>Hello <strong>world</strong></p>');

    expect($result)->toBe('Hello world');
});

it('extracts text from headings and lists', function () {
    $content = [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'heading',
                'attrs' => ['level' => 2],
                'content' => [
                    ['type' => 'text', 'text' => 'Section Title'],
                ],
            ],
            [
                'type' => 'paragraph',
                'content' => [
                    ['type' => 'text', 'text' => 'Section content'],
                ],
            ],
        ],
    ];

    $result = ProseMirrorTextExtractor::extract($content);

    expect($result)->toContain('Section Title');
    expect($result)->toContain('Section content');
});
