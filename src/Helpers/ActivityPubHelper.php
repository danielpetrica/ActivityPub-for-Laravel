<?php

namespace DanielPetrica\LaravelActivityPub\Helpers;

final class ActivityPubHelper
{
    /**
     * Sanitize HTML content from ActivityPub activities.
     * Allows only safe tags used by Mastodon and ActivityPub clients.
     */
    public static function sanitizeContent(string $html): string
    {
        $allowedTags = '<p><br><a><span><b><strong><i><em><u><del><s><blockquote><pre><code><ul><ol><li><h1><h2><h3><h4><h5><h6>';

        $allowedAttributes = [
            'a' => ['href', 'rel', 'class', 'target', 'translate', 'title'],
            'span' => ['class', 'translate'],
            'p' => ['class'],
            'blockquote' => ['class'],
            'pre' => ['class'],
            'code' => ['class'],
            'ol' => ['start', 'reversed', 'class'],
            'li' => ['value', 'class'],
        ];

        $html = strip_tags($html, $allowedTags);

        // Remove disallowed attributes from each tag
        $html = preg_replace_callback('/<(\w+)([^>]*)>/i', function ($matches) use ($allowedAttributes) {
            $tag = strtolower($matches[1]);
            $attrs = $matches[2];

            if (! isset($allowedAttributes[$tag])) {
                // Tag has no allowed attributes, return bare tag
                return '<'.$tag.'>';
            }

            $allowed = $allowedAttributes[$tag];
            $filtered = '';

            if (preg_match_all('/(\w+)(?:=(?:"([^"]*)"|\'([^\']*)\'|(\S+)))?/', $attrs, $attrMatches, PREG_SET_ORDER)) {
                foreach ($attrMatches as $attrMatch) {
                    $attrName = strtolower($attrMatch[1]);
                    $attrValue = $attrMatch[2] ?? $attrMatch[3] ?? $attrMatch[4] ?? '';

                    if (in_array($attrName, $allowed)) {
                        // For href, ensure it's a safe protocol
                        if ($attrName === 'href' && ! preg_match('/^https?:/i', $attrValue) && ! str_starts_with($attrValue, '#')) {
                            continue;
                        }

                        $filtered .= ' '.$attrName.'="'.htmlspecialchars($attrValue, ENT_QUOTES, 'UTF-8').'"';
                    }
                }
            }

            return '<'.$tag.$filtered.'>';
        }, $html);

        return $html;
    }
}
