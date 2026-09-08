<?php

namespace DanielPetrica\LaravelActivityPub\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class AuthorizeFediverse
{
    public function handle(Request $request, Closure $next): Response
    {
        $gate = config('activitypub.fediverse.gate');

        if ($gate !== null && ! Gate::allows($gate, $request->user())) {
            abort(403, 'Unauthorized access to Fediverse dashboard.');
        }

        return $next($request);
    }
}
