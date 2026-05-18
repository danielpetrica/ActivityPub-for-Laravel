<?php

namespace App\Classes\SEO;

use Illuminate\Support\Str;

final class ProseMirrorTextExtractor
{
    private const BLOCK_LEVEL_TYPES = [
        'paragraph', 'heading', 'blockquote', 'codeBlock',
        'bulletList', 'orderedList', 'listItem', 'table',
        'tableRow', 'tableCell', 'tableHeader',
        'horizontalRule', 'divider',
    ];

    public static function extract(null|array|string $content): string
    {
        if ($content === null || $content === '') {
            return '';
        }

        if (is_string($content)) {
            $decoded = json_decode($content, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return self::extractFromNodes($decoded);
            }

            return Str::of(strip_tags($content))
                ->trim()
                ->replace("\r\n", "\n")
                ->replaceMatches('/\n{3,}/', "\n\n")
                ->toString();
        }

        return self::extractFromNodes($content);
    }

    private static function extractFromNodes(array $nodes): string
    {
        $text = '';
        $parts = [];

        // ProseMirror doc nodes may have content directly or in 'content' key
        $items = $nodes['content'] ?? [$nodes];

        foreach ($items as $node) {
            if (! is_array($node)) {
                continue;
            }

            if (isset($node['type']) && $node['type'] === 'text' && isset($node['text'])) {
                $parts[] = $node['text'];
            }

            if (isset($node['content']) && is_array($node['content'])) {
                $parts[] = self::extractFromNodes(['content' => $node['content']]);
            }

            if (isset($node['type']) && in_array($node['type'], self::BLOCK_LEVEL_TYPES, true)) {
                $parts[] = "\n";
            }
        }

        $text = implode('', $parts);

        return Str::of($text)
            ->trim()
            ->replaceMatches('/\n{3,}/', "\n\n")
            ->toString();
    }
}
