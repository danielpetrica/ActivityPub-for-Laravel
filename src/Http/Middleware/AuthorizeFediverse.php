<?php

namespace DanielPetrica\LaravelActivityPub\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the Fediverse dashboard.
 *
 * Applied without a parameter it enforces the optional
 * `activitypub.fediverse.gate`; this is how the service provider mounts it on
 * the whole route group.
 *
 * Applied as `activitypub.authorize-fediverse:admin` it additionally requires
 * an admin authorization. Only the mutating, instance-wide routes use that
 * form, so read-only pages stay reachable for any authenticated actor while
 * blocking domains/actors, pruning and delivery maintenance require an admin.
 */
final class AuthorizeFediverse
{
    /**
     * Conventional ability checked for admin-only routes when
     * `activitypub.fediverse.admin_gate` is not configured.
     */
    public const DEFAULT_ADMIN_ABILITY = 'activitypub.fediverse.admin';

    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $this->authorizeGate($request);

        if ($mode === 'admin') {
            $this->authorizeAdmin($request);
        }

        return $next($request);
    }

    /**
     * Enforce the optional dashboard-wide gate.
     */
    protected function authorizeGate(Request $request): void
    {
        $gate = config('activitypub.fediverse.gate');

        if ($gate !== null && ! Gate::allows($gate, $request->user())) {
            abort(403, 'Unauthorized access to Fediverse dashboard.');
        }
    }

    /**
     * Require the configured admin ability to pass. The ability is denied when
     * undefined, so an unconfigured install fails closed rather than exposing
     * instance-wide mutations to every authenticated actor.
     */
    protected function authorizeAdmin(Request $request): void
    {
        $ability = config('activitypub.fediverse.admin_gate');

        if (! is_string($ability) || $ability === '') {
            $ability = self::DEFAULT_ADMIN_ABILITY;
        }

        $user = $request->user();

        if ($user === null || ! Gate::forUser($user)->allows($ability)) {
            abort(403, 'Admin authorization required for this action.');
        }
    }
}
