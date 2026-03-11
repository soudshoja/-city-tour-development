<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Services\DotwService;
use Exception;
use RuntimeException;

/**
 * Resolver for the getLocationIds GraphQL query.
 *
 * Returns location filtering codes via the DOTW getlocationids command.
 * Use these codes as location filter values to narrow hotel search to specific areas.
 *
 * LOOKUP-04
 */
class DotwGetLocationIds
{
    /**
     * Resolve the getLocationIds query.
     *
     * @param  mixed  $root  Unused GraphQL root value
     * @param  array  $args  GraphQL arguments (none required for this query)
     * @return array GetLocationIdsResponse shape
     */
    public function __invoke(mixed $root, array $args): array
    {
        $companyId = auth()->user()?->company?->id;

        try {
            $dotwService = new DotwService($companyId);
            $locations = $dotwService->getLocationIds();
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
                    'error_message' => 'Failed to fetch location IDs: ' . $e->getMessage(),
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
                'locations' => $locations,
                'total_count' => count($locations),
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
