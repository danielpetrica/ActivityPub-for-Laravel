<?php

namespace DanielPetrica\LaravelActivityPub\Helpers;

/**
 * Shared SSRF protection used by every outbound federation fetch and delivery.
 *
 * All remote URLs must be HTTPS and must resolve exclusively to public IP
 * addresses. Private, loopback, link-local, carrier-grade NAT and other
 * reserved ranges are rejected to prevent server-side request forgery.
 */
final class SsrfGuard
{
    /**
     * IPv4 ranges that must never be contacted.
     *
     * @var list<string>
     */
    private const PRIVATE_IPV4_CIDRS = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.88.99.0/24',
        '192.168.0.0/16',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '240.0.0.0/4',
        '255.255.255.255/32',
    ];

    /**
     * IPv6 ranges that must never be contacted.
     *
     * @var list<string>
     */
    private const PRIVATE_IPV6_CIDRS = [
        '::/128',
        '::1/128',
        'fc00::/7',
        'fe80::/10',
        'ff00::/8',
        '64:ff9b::/96',
        '2001:db8::/32',
    ];

    /**
     * Determine whether an IP literal falls inside a private or reserved range.
     */
    public static function isPrivateIp(string $ip): bool
    {
        $ip = trim($ip, '[] ');

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        $mapped = self::unwrapIpv4Mapped($ip);

        if ($mapped !== null) {
            return self::isPrivateIp($mapped);
        }

        $cidrs = str_contains($ip, ':') ? self::PRIVATE_IPV6_CIDRS : self::PRIVATE_IPV4_CIDRS;

        foreach ($cidrs as $cidr) {
            if (self::ipInCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether an IPv4 or IPv6 address is contained in a CIDR block.
     */
    public static function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, null);

        if ($subnet === null || $bits === null) {
            return false;
        }

        $ipBinary = @inet_pton($ip);
        $subnetBinary = @inet_pton($subnet);

        if ($ipBinary === false || $subnetBinary === false || strlen($ipBinary) !== strlen($subnetBinary)) {
            return false;
        }

        $bits = (int) $bits;
        $fullBytes = intdiv($bits, 8);
        $remainingBits = $bits % 8;

        if ($fullBytes > 0 && substr($ipBinary, 0, $fullBytes) !== substr($subnetBinary, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($ipBinary[$fullBytes]) & $mask) === (ord($subnetBinary[$fullBytes]) & $mask);
    }

    /**
     * Whether the host is explicitly allow-listed via config.
     *
     * Allow-listed hostnames skip DNS resolution checks (but not the private
     * IP literal checks). Intended for trusted internal test targets only.
     */
    public static function isHostAllowed(string $host): bool
    {
        $allowed = config('activitypub.security.allowed_hosts', []);

        if (! is_array($allowed) || $allowed === []) {
            return false;
        }

        $host = strtolower($host);

        foreach ($allowed as $candidate) {
            if (is_string($candidate) && strtolower($candidate) === $host) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a host resolves exclusively to public IP addresses.
     */
    public static function hostResolvesPublicly(string $host): bool
    {
        $host = trim($host, '[] ');

        if ($host === '') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return ! self::isPrivateIp($host);
        }

        // "localhost" and friends do not always resolve through DNS.
        if (strtolower($host) === 'localhost' || str_ends_with(strtolower($host), '.localhost')) {
            return false;
        }

        if (self::isHostAllowed($host)) {
            return true;
        }

        $ips = self::resolveHost($host);

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (self::isPrivateIp($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve a host to all of its A and AAAA records.
     *
     * @return list<string>
     */
    public static function resolveHost(string $host): array
    {
        $ips = [];

        $ipv4 = @gethostbynamel($host);

        if (is_array($ipv4)) {
            foreach ($ipv4 as $ip) {
                $ips[] = $ip;
            }
        }

        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_AAAA);

            if (is_array($records)) {
                foreach ($records as $record) {
                    if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                        $ips[] = $record['ipv6'];
                    }
                }
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * Validate an outbound URL: HTTPS scheme + host resolving to a public IP.
     */
    public static function isSafeUrl(string $url, bool $requireHttps = true): bool
    {
        $host = self::hostFromUrl(url: $url, requireHttps: $requireHttps);

        return $host !== null && self::hostResolvesPublicly($host);
    }

    /**
     * Parse an outbound URL and return its host when the request is acceptable
     * for an outbound federation call.
     */
    private static function hostFromUrl(string $url, bool $requireHttps): ?string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['host']) || ! is_string($parts['host'])) {
            return null;
        }

        if ($requireHttps && strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return null;
        }

        return $parts['host'];
    }

    /**
     * Validate an outbound URL and build the transport options that pin the
     * connection to an already-validated IP address.
     *
     * This closes the DNS-rebinding window between the pre-flight check and the
     * actual connection by resolving the host exactly once here and passing
     * Guzzle `curl[CURLOPT_RESOLVE]`, so the transport connects to the address
     * that was validated instead of re-resolving the hostname.
     *
     * Literal-IP URLs and hosts on the `security.allowed_hosts` allowlist are
     * not pinned (there is no DNS step to pin / the target is explicitly
     * trusted). Redirects are disabled by every caller, so there is no
     * follow-up host to re-validate.
     *
     * Residual risk: pinning requires the curl extension. When it is absent
     * this returns no `curl` options and only the pre-flight resolution check
     * applies, so a rebinding attack between resolution and connect remains
     * theoretically possible on such installs.
     *
     * @param  list<string>|null  $resolvedIps  Pre-resolved IPs (test seam); DNS is consulted when null.
     * @return array{0: bool, 1: array<string, mixed>}
     */
    public static function validateAndPin(string $url, bool $requireHttps = true, ?array $resolvedIps = null): array
    {
        $host = self::hostFromUrl(url: $url, requireHttps: $requireHttps);

        if ($host === null) {
            return [false, []];
        }

        $bareHost = trim($host, '[] ');

        if (filter_var($bareHost, FILTER_VALIDATE_IP) !== false) {
            return [! self::isPrivateIp($bareHost), []];
        }

        if (strtolower($bareHost) === 'localhost' || str_ends_with(strtolower($bareHost), '.localhost')) {
            return [false, []];
        }

        if (self::isHostAllowed($bareHost)) {
            return [true, []];
        }

        $ips = $resolvedIps ?? self::resolveHost($host);

        if ($ips === []) {
            return [false, []];
        }

        foreach ($ips as $ip) {
            if (self::isPrivateIp($ip)) {
                return [false, []];
            }
        }

        return [true, self::curlPinOptions(host: $host, url: $url, ip: $ips[0])];
    }

    /**
     * Build the Guzzle option array that forces the curl handler to connect to
     * a specific IP for the given host, bypassing a second DNS lookup.
     *
     * @return array<string, mixed>
     */
    private static function curlPinOptions(string $host, string $url, string $ip): array
    {
        if (! defined('CURLOPT_RESOLVE')) {
            return [];
        }

        $port = parse_url($url, PHP_URL_PORT);
        $port = is_int($port) ? $port : 443;

        return ['curl' => [CURLOPT_RESOLVE => [$host.':'.$port.':'.$ip]]];
    }

    /**
     * Whether a response body exceeds the configured size cap.
     */
    public static function responseTooLarge(int $bodyBytes, ?int $declaredBytes = null): bool
    {
        $max = (int) config('activitypub.security.max_response_bytes', 2_000_000);

        if ($max <= 0) {
            return false;
        }

        return $bodyBytes > $max || ($declaredBytes !== null && $declaredBytes > $max);
    }

    /**
     * Whether a response content-type is acceptable for the given fragments.
     */
    public static function contentTypeAllowed(?string $contentType, string ...$fragments): bool
    {
        // Be lenient when the server omits the header entirely.
        if ($contentType === null || trim($contentType) === '') {
            return true;
        }

        $contentType = strtolower($contentType);

        foreach ($fragments as $fragment) {
            if (str_contains($contentType, strtolower($fragment))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Unwrap IPv4-mapped IPv6 addresses so IPv4 ranges still apply.
     */
    private static function unwrapIpv4Mapped(string $ip): ?string
    {
        if (preg_match('/^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$/i', $ip, $matches) === 1) {
            return $matches[1];
        }

        $binary = @inet_pton($ip);

        if ($binary === false || strlen($binary) !== 16) {
            return null;
        }

        if (substr($binary, 0, 10) !== str_repeat("\x00", 10) || substr($binary, 10, 2) !== "\xff\xff") {
            return null;
        }

        $ipv4 = @inet_ntop(substr($binary, 12, 4));

        return is_string($ipv4) ? $ipv4 : null;
    }
}
