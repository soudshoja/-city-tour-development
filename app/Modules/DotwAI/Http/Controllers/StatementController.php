<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Http\Controllers;

use App\Modules\DotwAI\Http\Requests\StatementRequest;
use App\Modules\DotwAI\Services\DotwAIResponse;
use App\Modules\DotwAI\Services\StatementService;
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
        try {
            /** @var \App\Modules\DotwAI\DTOs\DotwAIContext $context */
            $context = $request->attributes->get('dotwai_context');

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
