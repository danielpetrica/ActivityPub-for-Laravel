<?php

namespace DanielPetrica\LaravelActivityPub\Http\Signatures;

/**
 * Pure parsing for the two HTTP signature header formats we accept:
 * draft-cavage (legacy) and RFC 9421 structured fields.
 *
 * No request state, no verification, no side effects — safe to unit test.
 */
final class SignatureParser
{
    /**
     * Parse a draft-cavage `Signature` header into its parameters.
     *
     * @return array{keyId: string, signature: string, headers: string, algorithm?: string}|null
     */
    public function parseDraftCavageHeader(string $header): ?array
    {
        $parts = [];

        foreach (explode(',', $header) as $param) {
            $param = trim($param);

            if (preg_match('/^(\w+)="(.*)"$/', $param, $matches)) {
                $parts[$matches[1]] = $matches[2];
            }
        }

        if (! isset($parts['keyId'], $parts['signature'], $parts['headers'])) {
            return null;
        }

        return $parts;
    }

    /**
     * Normalise the signed header list ("(request-target) host date") to an array.
     *
     * @return array<int, string>
     */
    public function signedComponentNames(string $headers): array
    {
        $parts = preg_split('/\s+/', trim($headers));

        if ($parts === false) {
            return [];
        }

        return array_map(
            static fn (string $part): string => strtolower($part),
            $parts,
        );
    }

    /**
     * Parse the RFC 9421 Signature-Input header, supporting multiple labels.
     *
     * @return list<array{
     *     label: string,
     *     components: list<string>,
     *     value: string,
     *     keyid: string|null,
     *     created: int|null,
     *     expires: int|null,
     *     nonce: string|null,
     *     tag: string|null,
     *     alg: string|null
     * }>|null
     */
    public function parseRfc9421SignatureInput(string $header): ?array
    {
        $entries = [];

        foreach ($this->splitTopLevelHeader(header: trim($header)) as $part) {
            if (preg_match('/^\s*([A-Za-z0-9_\-]+)\s*=\s*(\(.*)$/s', $part, $matches) !== 1) {
                continue;
            }

            $label = $matches[1];
            $value = trim($matches[2]);
            $componentsText = strstr($value, ')', true);

            if ($componentsText === false) {
                continue;
            }

            preg_match_all('/"([^"]+)"/', $componentsText, $componentMatches);

            $entries[] = [
                'label' => $label,
                'components' => $componentMatches[1],
                'value' => $value,
                'keyid' => $this->extractQuotedParam(value: $value, name: 'keyid'),
                'created' => $this->extractIntParam(value: $value, name: 'created'),
                'expires' => $this->extractIntParam(value: $value, name: 'expires'),
                'nonce' => $this->extractQuotedParam(value: $value, name: 'nonce'),
                'tag' => $this->extractQuotedParam(value: $value, name: 'tag'),
                'alg' => $this->extractQuotedParam(value: $value, name: 'alg'),
            ];
        }

        return $entries === [] ? null : $entries;
    }

    /**
     * Extract every label => base64 signature pair from the Signature header.
     *
     * @return array<string, string>
     */
    public function extractRfc9421Signatures(string $header): array
    {
        $signatures = [];

        foreach ($this->splitTopLevelHeader(header: trim($header)) as $part) {
            if (preg_match('/^\s*([A-Za-z0-9_\-]+)\s*=\s*:([^:]+):\s*$/s', $part, $matches) === 1) {
                $signatures[$matches[1]] = $matches[2];
            }
        }

        return $signatures;
    }

    /**
     * Split a structured header on top-level commas, ignoring commas nested
     * inside parentheses or quoted strings.
     *
     * @return list<string>
     */
    public function splitTopLevelHeader(string $header): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;
        $inQuotes = false;
        $length = strlen($header);

        for ($i = 0; $i < $length; $i++) {
            $char = $header[$i];

            if ($char === '"') {
                $inQuotes = ! $inQuotes;
                $buffer .= $char;

                continue;
            }

            if (! $inQuotes) {
                if ($char === '(') {
                    $depth++;
                } elseif ($char === ')') {
                    $depth--;
                } elseif ($char === ',' && $depth === 0) {
                    $parts[] = $buffer;
                    $buffer = '';

                    continue;
                }
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $parts[] = $buffer;
        }

        return $parts;
    }

    /**
     * Extract a quoted parameter (e.g. keyid="..." or tag="...") from a value.
     */
    private function extractQuotedParam(string $value, string $name): ?string
    {
        if (preg_match('/[;\s]'.preg_quote($name, '/').'\s*=\s*"([^"]*)"/i', $value, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract an integer parameter (e.g. created=... or expires=...) from a value.
     */
    private function extractIntParam(string $value, string $name): ?int
    {
        if (preg_match('/[;\s]'.preg_quote($name, '/').'\s*=\s*(\d+)/i', $value, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }
}
