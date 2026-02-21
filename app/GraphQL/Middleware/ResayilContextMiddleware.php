<?php

namespace App\GraphQL\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ResayilContextMiddleware — extract Resayil WhatsApp tracking headers into request attributes.
 *
 * Runs on every GraphQL request via Lighthouse's route middleware stack.
 * Extracts X-Resayil-Message-ID and X-Resayil-Quote-ID HTTP headers and
 * stores them on $request->attributes so GraphQL resolvers can retrieve them
 * without coupling to the HTTP layer directly.
 *
 * Header → request attribute mapping:
 *   X-Resayil-Message-ID  → resayil_message_id
 *   X-Resayil-Quote-ID    → resayil_quote_id
 *
 * Resolvers access via:
 *   $context->request()->attributes->get('resayil_message_id')
 *   $context->request()->attributes->get('resayil_quote_id')
 *
 * Both attributes are nullable strings — null when the header is absent.
 * This middleware satisfies MSG-02 (message linkage) and MSG-03 (quote linkage).
 */
class ResayilContextMiddleware
{
    /**
     * Handle the incoming GraphQL HTTP request.
     *
     * Extracts Resayil tracking headers (case-insensitive via Laravel's
     * header() method) and attaches them to request attributes for
     * downstream resolver access.
     *
     * @param  Request  $request  The incoming HTTP request
     * @param  Closure(Request): Response  $next  Next middleware handler
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Extract headers (case-insensitive via Laravel's header() method)
        $messageId = $request->header('X-Resayil-Message-ID');
        $quoteId   = $request->header('X-Resayil-Quote-ID');

        // Attach to request attributes for resolver access via $context->request()
        $request->attributes->set('resayil_message_id', $messageId);
        $request->attributes->set('resayil_quote_id', $quoteId);

        return $next($request);
    }
}
