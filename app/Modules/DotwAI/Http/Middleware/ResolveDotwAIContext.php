<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Http\Middleware;

use App\Modules\DotwAI\Services\DotwAIResponse;
use App\Modules\DotwAI\Services\PhoneResolverService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that resolves a phone number to a DotwAIContext.
 *
 * Extracts the telephone from the request (JSON body, query params, or
 * X-DotwAI-Phone header), calls PhoneResolverService to resolve the full
 * context chain (agent -> company -> credentials -> track), validates the
 * track is enabled, and attaches the DotwAIContext to the request.
 *
 * On failure, returns a DotwAIResponse error with appropriate error code
 * and human-friendly WhatsApp message.
 *
 * @see FOUND-03
 */
class ResolveDotwAIContext
{
    /**
     * @param PhoneResolverService $resolver
     */
    public function __construct(
        private readonly PhoneResolverService $resolver,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): Response $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Extract telephone from multiple sources
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

        // Resolve phone to full context
        $context = $this->resolver->resolve((string) $telephone);

        if ($context === null) {
            return DotwAIResponse::error(
                DotwAIResponse::PHONE_NOT_FOUND,
                "Could not resolve phone number: {$telephone}",
            );
        }

        // Validate track is enabled
        if ($context->isB2B() && !$context->b2bEnabled) {
            return DotwAIResponse::error(
                DotwAIResponse::TRACK_DISABLED,
                'B2B track is disabled for this company',
                "عذرا، خدمة B2B غير متاحة لشركتك حاليا.\nSorry, B2B service is currently disabled for your company.",
                'Contact admin to enable B2B track.',
            );
        }

        if ($context->isB2C() && !$context->b2cEnabled) {
            return DotwAIResponse::error(
                DotwAIResponse::TRACK_DISABLED,
                'B2C track is disabled for this company',
                "عذرا، خدمة B2C غير متاحة لشركتك حاليا.\nSorry, B2C service is currently disabled for your company.",
                'Contact admin to enable B2C track.',
            );
        }

        // Attach context to request
        $request->attributes->set('dotwai_context', $context);

        Log::channel('dotw')->info('[DotwAI] Context resolved for request', [
            'phone' => $telephone,
            'company_id' => $context->companyId,
            'track' => $context->track,
            'route' => $request->path(),
        ]);

        return $next($request);
    }
}
