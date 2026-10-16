<?php

namespace DanielPetrica\LaravelActivityPub\Helpers;

/**
 * Normalizes domain names so blocked-domain comparisons cannot be bypassed by
 * casing, trailing dots, optional `www.` prefixes or internationalized
 * (Unicode) host names.
 *
 * Every stored block and every lookup must run through this helper so that
 * `Example.com`, `example.com.` and their Punycode equivalents all resolve to
 * the same identity.
 */
final class DomainNormalizer
{
    /**
     * Lowercase, strip a trailing dot, drop a numeric port and convert an
     * internationalized domain to its ASCII (Punycode) form when the intl
     * extension is available.
     */
    public static function normalize(?string $domain): ?string
    {
        if (! is_string($domain)) {
            return null;
        }

        $domain = strtolower(trim($domain, "[] \t\n\r\0\x0B"));

        if ($domain === '') {
            return null;
        }

        // Drop a numeric port suffix (but never mangle bare IPv6 literals).
        if (preg_match('/^(.*):(\d+)$/', $domain, $matches) === 1 && ! str_contains($matches[1], ':')) {
            $domain = $matches[1];
        }

        $domain = rtrim($domain, '.');

        if ($domain === '') {
            return null;
        }

        if (function_exists('idn_to_ascii')) {
            $ascii = @idn_to_ascii($domain);

            if (is_string($ascii) && $ascii !== '') {
                $domain = strtolower($ascii);
            }
        }

        $domain = rtrim($domain, '.');

        return $domain === '' ? null : $domain;
    }

    /**
     * Extract a normalized host from a URL.
     */
    public static function hostFromUrl(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return self::normalize($host);
    }

    /**
     * Every spelling that should be treated as the same host, including the
     * `www.` prefix in both directions.
     *
     * @return list<string>
     */
    public static function candidates(?string $domain): array
    {
        $domain = self::normalize($domain);

        if ($domain === null) {
            return [];
        }

        $candidates = [$domain];

        if (str_starts_with($domain, 'www.')) {
            $candidates[] = substr($domain, 4);
        } else {
            $candidates[] = 'www.'.$domain;
        }

        return array_values(array_unique(array_filter(
            $candidates,
            static fn (string $candidate): bool => $candidate !== '',
        )));
    }
}
