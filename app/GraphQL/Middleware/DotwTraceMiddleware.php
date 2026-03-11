<?php

declare(strict_types=1);

namespace App\GraphQL\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * DOTW Trace Middleware
 *
 * Injects a per-request UUID trace ID and timing headers into every
 * DOTW GraphQL response. Enables log correlation between N8N workflows,
 * Resayil WhatsApp conversations, and server-side audit logs.
 *
 * Sets:
 *  - X-Trace-ID: UUID v4 unique to this request
 *  - X-Request-Time-Ms: Total processing time in milliseconds
 *
 * The trace_id is also bound in the service container as 'dotw.trace_id'
 * so GraphQL resolvers can include it in their DotwMeta response object.
 */
class DotwTraceMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $traceId = Str::uuid()->toString();
        $startTime = microtime(true);

        // Make trace_id available to resolvers
        $request->attributes->set('dotw_trace_id', $traceId);
        app()->instance('dotw.trace_id', $traceId);

        /** @var Response $response */
        $response = $next($request);

        $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

        $response->headers->set('X-Trace-ID', $traceId);
        $response->headers->set('X-Request-Time-Ms', (string) $elapsedMs);

        return $response;
    }
}
