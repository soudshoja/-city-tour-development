<?php

namespace App\Http\Middleware;

use Closure;

class ApiCorsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        return $next($request)
            ->header('Access-Control-Allow-Origin', '*') // Allow all origins, or specify domains
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS') // Allowed HTTP methods
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization'); // Allowed headers
    }
}
