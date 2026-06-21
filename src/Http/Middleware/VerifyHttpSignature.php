<?php

namespace DanielPetrica\LaravelActivityPub\Http\Middleware;

use Closure;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use DanielPetrica\LaravelActivityPub\Services\RemoteActorResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class VerifyHttpSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('activitypub.http_signatures.enabled')) {
            return $next($request);
        }

        $signatureHeader = $request->header(key: 'signature');

        if ($signatureHeader === null) {
            return response()->json(
                data: ['error' => 'Signature header required.'],
                status: 401,
            );
        }

        $parsed = $this->parseSignatureHeader(header: $signatureHeader);

        if ($parsed === null) {
            return response()->json(
                data: ['error' => 'Invalid Signature header format.'],
                status: 401,
            );
        }

        $keyId = $parsed['keyId'];

        $dateHeader = $request->header(key: 'date');

        if ($dateHeader === null) {
            return response()->json(
                data: ['error' => 'Date header is required.'],
                status: 401,
            );
        }

        $requestTime = strtotime(datetime: $dateHeader);

        if ($requestTime === false || abs(num: $requestTime - now()->timestamp) > config('activitypub.http_signatures.max_clock_skew', 120)) {
            return response()->json(
                data: ['error' => 'Request date is outside the allowed clock skew.'],
                status: 401,
            );
        }

        $cacheKey = 'sig-replay:'.md5($keyId.'|'.$signatureHeader.'|'.$dateHeader);

        if (! Cache::add(key: $cacheKey, value: true, ttl: 120)) {
            return response()->json(
                data: ['error' => 'Duplicate request detected.'],
                status: 401,
            );
        }

        $method = strtolower($request->method());

        if ($method === 'post' && $request->getContent() !== '') {
            $digestHeader = $request->header(key: 'digest');

            if ($digestHeader === null) {
                return response()->json(
                    data: ['error' => 'Digest header is required for POST requests.'],
                    status: 401,
                );
            }
        } else {
            $digestHeader = $request->header(key: 'digest');
        }

        if ($digestHeader !== null) {
            $bodyContent = $request->getContent();

            if ($bodyContent !== '') {
                $computedDigest = 'SHA-256='.base64_encode(string: hash(
                    algo: 'sha256',
                    data: $bodyContent,
                    binary: true,
                ));

                $normalizedDigest = preg_replace_callback(
                    pattern: '/^([a-z][a-z0-9]*)=/i',
                    callback: fn (array $m): string => strtoupper($m[1]).'=',
                    subject: $digestHeader,
                );

                if (! hash_equals(known_string: $normalizedDigest, user_string: $computedDigest)) {
                    return response()->json(
                        data: ['error' => 'Digest header does not match body.'],
                        status: 401,
                    );
                }
            }
        }

        $actorUrl = str_replace(search: '#main-key', replace: '', subject: $keyId);

        if (parse_url($actorUrl, PHP_URL_SCHEME) !== 'https') {
            return response()->json(
                data: ['error' => 'keyId must use HTTPS.'],
                status: 401,
            );
        }

        $remoteActor = RemoteActor::query()
            ->where(column: 'actor_url', operator: '=', value: $actorUrl)
            ->first();

        if ($remoteActor === null) {
            $resolver = app(RemoteActorResolver::class);
            $remoteActor = $resolver->resolve(actorUri: $actorUrl);
        }

        if ($remoteActor === null) {
            return response()->json(
                data: ['error' => 'Unknown remote actor.'],
                status: 401,
            );
        }

        $verified = $this->verifySignature(
            request: $request,
            parsed: $parsed,
            remoteActor: $remoteActor,
        );

        if (! $verified) {
            return response()->json(
                data: ['error' => 'Signature verification failed.'],
                status: 401,
            );
        }

        $request->attributes->set(key: 'remote_actor', value: $remoteActor);

        return $next($request);
    }

    protected function parseSignatureHeader(string $header): ?array
    {
        $parts = [];

        foreach (explode(separator: ',', string: $header) as $param) {
            $param = trim(string: $param);

            if (preg_match(pattern: '/^(\w+)="(.*)"$/', subject: $param, matches: $matches)) {
                $parts[$matches[1]] = $matches[2];
            }
        }

        if (! isset($parts['keyId'], $parts['signature'], $parts['headers'])) {
            return null;
        }

        return $parts;
    }

    protected function verifySignature(Request $request, array $parsed, RemoteActor $remoteActor): bool
    {
        $publicKey = $remoteActor->public_key_pem;

        if ($publicKey === null) {
            return false;
        }

        $signature = base64_decode(string: $parsed['signature']);

        if ($signature === false) {
            Log::debug('VerifyHttpSignature: invalid base64 signature');

            return false;
        }

        $configHost = parse_url(url: config('activitypub.domain'), component: PHP_URL_HOST) ?? $request->getHost();

        $signingString = $this->buildSigningString(
            request: $request,
            parsed: $parsed,
            host: $configHost,
        );

        $result = $this->verifyWithString(
            signingString: $signingString,
            signature: $signature,
            publicKey: $publicKey,
        );

        if ($result) {
            return true;
        }

        $opensslError = openssl_error_string();

        $configHost = parse_url(url: config('activitypub.domain'), component: PHP_URL_HOST);

        if ($configHost !== null && $configHost !== $request->getHost()) {
            $altSigningString = $this->buildSigningString(
                request: $request,
                parsed: $parsed,
                host: $configHost,
            );

            $result = $this->verifyWithString(
                signingString: $altSigningString,
                signature: $signature,
                publicKey: $publicKey,
            );

            if ($result) {
                return true;
            }
        }

        Log::debug('VerifyHttpSignature: verification failed', [
            'actorUrl' => $remoteActor->actor_url,
            'requestHost' => $request->getHost(),
            'configHost' => $configHost,
            'opensslError' => $opensslError,
            'signedHeaders' => $parsed['headers'],
            'signingString' => $signingString,
        ]);

        return false;
    }

    protected function buildSigningString(Request $request, array $parsed, string $host): string
    {
        $headers = explode(separator: ' ', string: $parsed['headers']);
        $path = $request->getPathInfo();
        $method = strtolower(string: $request->method());

        $signingString = '';

        foreach ($headers as $header) {
            $header = trim(string: $header);

            if ($header === '(request-target)') {
                $signingString .= '(request-target): '.$method.' '.$path."\n";
            } elseif ($header === 'host') {
                $signingString .= 'host: '.$host."\n";
            } elseif ($header === 'date') {
                $signingString .= 'date: '.$request->header(key: 'date', default: '')."\n";
            } elseif ($header === 'digest') {
                $signingString .= 'digest: '.$request->header(key: 'digest', default: '')."\n";
            } elseif ($header === 'content-type') {
                $signingString .= 'content-type: '.$request->header(key: 'content-type', default: '')."\n";
            }
        }

        return rtrim(string: $signingString);
    }

    protected function verifyWithString(string $signingString, string $signature, string $publicKey): bool
    {
        return openssl_verify(
            data: $signingString,
            signature: $signature,
            public_key: $publicKey,
            algorithm: OPENSSL_ALGO_SHA256,
        ) === 1;
    }
}
