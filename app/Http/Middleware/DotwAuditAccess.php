<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;

class DotwAuditAccess
{
    public function handle(Request $request, Closure $next): mixed
    {
        $allowed = [Role::ADMIN, Role::COMPANY];

        if (! auth()->check() || ! in_array(auth()->user()->role_id, $allowed)) {
            abort(403, 'Access denied.');
        }

        return $next($request);
    }
}
