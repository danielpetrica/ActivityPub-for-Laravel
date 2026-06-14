<?php

namespace App\Http\Middleware;

use App\Enums\TTLEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCacheControlHeader
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldCacheResponse($request, $response)) {
            // When the controller already set explicit cache directives (e.g. ObjectProxyController),
            // don't override them with the default two-hour cache.
            if ($response->headers->hasCacheControlDirective('public') && $response->headers->hasCacheControlDirective('max-age')) {
                $response->headers->remove('x-powered-by');

                return $response;
            }

            $response->setPublic();
            $response->setMaxAge(TTLEnum::TwoHours->getSeconds());
            $response->setExpires(now()->addSeconds(TTLEnum::OneHour->getSeconds()));
            $response->setStaleWhileRevalidate(TTLEnum::SixHours->getSeconds());

            $response->headers->remove('x-powered-by');
        }

        return $response;
    }

    public function shouldCacheResponse(Request $request, Response $response): bool
    {
        if (! app()->isProduction() && ! app()->runningUnitTests()) {
            return false;
        }

        if (auth()->check()) {
            return false;
        }

        if (! $request->isMethod('GET')) {
            return false;
        }

        if (! $response->isSuccessful()) {
            return false;
        }

        return true;
    }
}
