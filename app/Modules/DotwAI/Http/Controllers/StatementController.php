<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Http\Controllers;

use App\Models\Company;
use App\Modules\DotwAI\Http\Requests\StatementRequest;
use App\Modules\DotwAI\Services\DotwAIResponse;
use App\Modules\DotwAI\Services\StatementService;
use App\Support\Modules;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * Statement controller for the DotwAI module.
 *
 * Thin controller that delegates aggregation to StatementService and
 * wraps the result in the standard DotwAIResponse envelope.
 *
 * Endpoint:
 * - GET /api/dotwai/statement — Returns bookings, journal entries, credits, and totals
 *
 * @see ACCT-02 Company statement for date-range reconciliation
 */
class StatementController extends Controller
{
    public function __construct(
        private readonly StatementService $statementService,
    ) {}

    /**
     * Generate a company statement for a given date range.
     *
     * GET /api/dotwai/statement
     *
     * Returns all bookings, journal entries, and credit transactions for the
     * company over the requested period, plus a WhatsApp-formatted summary.
     *
     * @param StatementRequest $request Validated request with phone, date_from, date_to
     * @return JsonResponse
     */
    public function getStatement(StatementRequest $request): JsonResponse
    {
        /** @var \App\Modules\DotwAI\DTOs\DotwAIContext $context */
        $context = $request->attributes->get('dotwai_context');

        // Accounting Gap blocker 8 (16-phase1-verification-findings-2026-08.md):
        // this endpoint has no Laravel auth guard and the dotwai.resolve
        // middleware's phone lookup identifies a COMPANY, not a caller -- a
        // phone number is not a credential. The route now carries
        // 'verify.webhook.signature' (see Routes/api.php), but that
        // middleware only VERIFIES a signature when one is presented; it
        // does not require one. Exactly like App\Http\Webhooks\
        // TaskWebhook::webhook(), this top-of-body check is what turns
        // "verified if present" into "mandatory for this endpoint" without
        // touching the shared middleware's own skip-when-absent default.
        if (! $request->attributes->get('webhook_client')) {
            Log::channel('dotw')->warning('[DotwAI] getStatement rejected -- no verified signature', [
                'ip' => $request->ip(),
            ]);

            return DotwAIResponse::error(
                DotwAIResponse::UNAUTHORIZED,
                'A verified webhook signature is required for this endpoint.',
                httpStatus: 401,
            );
        }

        // Accounting Gap blocker 8: a company without the accounting module
        // enabled must not be able to pull journal entries and credit
        // transactions through the bot path. This mirrors the 404-not-403
        // philosophy of App\Http\Middleware\EnsureModuleEnabled (see that
        // class's docblock) without reusing it directly -- that middleware
        // resolves its company via $request->user(), and this endpoint has
        // no authenticated web user at all (the company comes from the
        // phone-resolved DotwAIContext instead). Company::hasModule() is the
        // exact same single source of truth EnsureModuleEnabled itself
        // defers to, so the entitlement check stays identical; only how we
        // reach a Company row differs.
        $company = Company::find($context->companyId);
        if ($company === null || ! $company->hasModule(Modules::ACCOUNTING)) {
            abort(404);
        }

        try {
            $statementData = $this->statementService->getStatement(
                $context->companyId,
                $request->date_from,
                $request->date_to,
            );

            $whatsappMessage = StatementService::formatStatementWhatsApp(
                $statementData,
                $request->date_from,
                $request->date_to,
            );

            return DotwAIResponse::success(
                $statementData,
                $whatsappMessage,
                ['Download statement', 'View credit history'],
            );
        } catch (\Throwable $e) {
            Log::channel('dotw')->error('[DotwAI] getStatement exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return DotwAIResponse::error(
                DotwAIResponse::DOTW_API_ERROR,
                'Statement generation failed: ' . $e->getMessage(),
            );
        }
    }
}
