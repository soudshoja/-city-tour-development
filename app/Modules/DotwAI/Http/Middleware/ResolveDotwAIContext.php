<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Http\Middleware;

use App\Modules\DotwAI\Services\DotwAIResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that resolves a phone number to a DotwAIContext.
 *
 * Extracts the telephone from the request (JSON body, query params, or
 * X-DotwAI-Phone header), resolves it to an agent, company, DOTW credentials,
 * and booking track, then attaches the DotwAIContext to the request.
 *
 * Stub: Full implementation with PhoneResolverService in Task 2.
 *
 * @see FOUND-03
 */
class ResolveDotwAIContext
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): Response $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Extract telephone from request
        $telephone = $request->input('telephone')
            ?? $request->query('telephone')
            ?? $request->header('X-DotwAI-Phone');

        if (empty($telephone)) {
            return DotwAIResponse::error(
                DotwAIResponse::VALIDATION_ERROR,
                'Phone number is required',
                "يرجى تقديم رقم الهاتف.\nPlease provide a phone number.",
                'Include telephone in request body, query string, or X-DotwAI-Phone header.',
                422
            );
        }

        // Full resolution logic will be implemented in Task 2
        // For now, pass through (allows health check and route listing to work)
        return $next($request);
    }
}
