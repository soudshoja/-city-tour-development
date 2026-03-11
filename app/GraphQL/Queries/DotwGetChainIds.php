<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Services\DotwService;
use Exception;
use RuntimeException;

/**
 * Resolver for the getChainIds GraphQL query.
 *
 * Returns hotel chain affiliation codes via the DOTW getchainids command.
 * Use these codes to filter hotel search results to specific hotel chains.
 *
 * LOOKUP-07
 */
class DotwGetChainIds
{
    /**
     * Resolve the getChainIds query.
     *
     * @param  mixed  $root  Unused GraphQL root value
     * @param  array  $args  GraphQL arguments (none required for this query)
     * @return array GetChainIdsResponse shape
     */
    public function __invoke(mixed $root, array $args): array
    {
        $companyId = auth()->user()?->company?->id;

        try {
            $dotwService = new DotwService($companyId);
            $chains = $dotwService->getChainIds();
        } catch (RuntimeException $e) {
            return [
                'success' => false,
                'error' => [
                    'error_code' => 'CREDENTIALS_NOT_CONFIGURED',
                    'error_message' => 'DOTW credentials not configured for this company.',
                    'error_details' => $e->getMessage(),
                    'action' => 'RECONFIGURE_CREDENTIALS',
                ],
                'meta' => $this->buildMeta($companyId ?? 0),
                'cached' => false,
                'data' => null,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => [
                    'error_code' => 'API_ERROR',
                    'error_message' => 'Failed to fetch chain IDs: ' . $e->getMessage(),
                    'error_details' => $e->getMessage(),
                    'action' => 'RETRY',
                ],
                'meta' => $this->buildMeta($companyId ?? 0),
                'cached' => false,
                'data' => null,
            ];
        }

        return [
            'success' => true,
            'error' => null,
            'meta' => $this->buildMeta($companyId ?? 0),
            'cached' => false,
            'data' => [
                'chains' => $chains,
                'total_count' => count($chains),
            ],
        ];
    }

    /**
     * Build the DotwMeta array for this response.
     *
     * Uses app('dotw.trace_id') which is bound by DotwTraceMiddleware for every GraphQL request.
     */
    private function buildMeta(int $companyId): array
    {
        return [
            'trace_id' => app('dotw.trace_id'),
            'request_id' => app('dotw.trace_id'),
            'timestamp' => now()->toIso8601String(),
            'company_id' => $companyId,
        ];
    }
}
