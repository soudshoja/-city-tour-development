<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel\Dusk\DuskServiceProvider registers `_dusk/login/{userId}/{guard?}`
 * (impersonate any user by id, no auth) whenever APP_ENV !== 'production' --
 * i.e. on every dev/staging box, including ones reachable on the internet.
 * That route is registered by the vendor package itself (not a route we
 * define), so it can't be re-scoped with normal route middleware without
 * fighting provider/route registration order. Instead this runs as global
 * web middleware and gates the whole `_dusk/*` prefix (login/logout/user)
 * before Dusk's controller ever runs: reachable ONLY for an authenticated
 * platform super admin (Role::ADMIN, the same "unscoped admin" definition
 * AdminUsersController uses throughout -- see e.g. :190, :234, :355).
 * Everyone else gets a 404, indistinguishable from the route not existing.
 *
 * Kept intentionally (owner decision, 2026-09-04): super admins use this as
 * a support "log in as this user" tool. Every use is logged.
 *
 * Registered as an addition to the shared 'web' middleware GROUP (not the
 * app-wide middleware stack) -- DuskServiceProvider registers its routes
 * with `'middleware' => config('dusk.middleware', 'web')` (no config/dusk.php
 * is published in this app, so that's the literal 'web' group), which means
 * by the time this runs the route has already been matched and
 * `$request->route(...)` is safe to call -- true global/kernel middleware
 * runs before routing and would not have that guarantee.
 */
class RestrictDuskRoutesToSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('_dusk/*') && ! $request->is('_dusk')) {
            return $next($request);
        }

        $user = Auth::user();

        if (! $user || (int) $user->role_id !== Role::ADMIN) {
            Log::warning('dusk.support_route_blocked', [
                'path' => $request->path(),
                'user_id' => $user?->id,
                'ip' => $request->ip(),
            ]);

            abort(404);
        }

        Log::warning('dusk.support_route_used', [
            'path' => $request->path(),
            'admin_user_id' => $user->id,
            'target_user_id' => $request->route('userId'),
            'guard' => $request->route('guard'),
            'ip' => $request->ip(),
        ]);

        return $next($request);
    }
}
