<?php

namespace App\Http\Middleware;

use App\Policies\Concerns\RequiresCompanyModule;
use Closure;
use Illuminate\Http\Request;

/**
 * Gates a route (or an entire route group) behind a per-company module
 * entitlement, e.g. `module.accounting` on the `settings` table (see
 * App\Support\Modules and Company::hasModule()).
 *
 * Usage (mirrors the existing spatie 'role:xxx' / 'permission:xxx' alias
 * style already used in bootstrap/app.php):
 *
 *     Route::middleware(['auth', 'module:accounting'])->group(function () { ... });
 *     Route::get('/reports/trial-balance', ...)->middleware('module:accounting');
 *
 * This reuses RequiresCompanyModule::moduleEnabled() — the exact same check
 * every gated Policy method (COAPolicy, AccountPolicy, TaskPolicy, etc.)
 * now runs first. That's deliberate, not incidental: the route layer and
 * the policy layer must agree on who has a module, or a user could clear
 * this middleware and still be denied by the controller's own
 * Gate::authorize() (or the reverse — never actually possible here, since
 * this middleware runs before any controller/policy code, but an
 * inconsistent definition would still produce confusing dead links). There
 * is no separate "admin always bypasses" branch here for the same reason
 * none exists in moduleEnabled() itself: an admin's access still depends on
 * whichever company they're currently resolved to (session('company_id'),
 * defaulting to company 1 — see getCompanyId()) having the module enabled.
 * Individual Policy methods layer their own hasRole('admin') bypass AFTER
 * the module check only for the underlying permission — never for the
 * module gate — and this middleware follows that same shape.
 *
 * IMPORTANT: this middleware only ever narrows access — it never widens it.
 * Always pair it with 'auth' (either the route's own middleware stack or an
 * enclosing group) so unauthenticated visitors are handled by the normal
 * login redirect rather than by this middleware's fail-closed 404 below.
 */
class EnsureModuleEnabled
{
    use RequiresCompanyModule;

    /**
     * Handle an incoming request.
     *
     * Aborts with 404 (never 403) when the requesting user's company does
     * not have the given module enabled. A 403 would confirm to the caller
     * that the route exists and simply refuses them — which itself leaks
     * the presence of a module the product deliberately hides from package
     * clients (nav item removed, no mention anywhere in their UI). A 404
     * makes the route indistinguishable from one that was never built, so a
     * disabled module stays truly invisible rather than merely "locked".
     */
    public function handle(Request $request, Closure $next, string $module): mixed
    {
        $user = $request->user();

        // No authenticated user to resolve a company/module for. This branch
        // should be unreachable in practice (an 'auth' middleware placed
        // before this one already redirects guests to login), but if this
        // middleware is ever applied without 'auth' in front, fail closed
        // instead of leaking anything.
        if (! $user) {
            abort(404);
        }

        // moduleEnabled() fails closed (false) for a user who resolves to no
        // company at all, same as every gated Policy method — see that
        // trait's docblock for exactly how each role resolves its company.
        if (! $this->moduleEnabled($user, $module)) {
            abort(404);
        }

        return $next($request);
    }
}
