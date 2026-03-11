<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Services\DotwService;
use Exception;
use RuntimeException;

/**
 * Resolver for the getHotelClassifications GraphQL query.
 *
 * Returns hotel star rating classification codes via the gethotelclassificationids command.
 * Use these codes as minRating/maxRating filter values in searchHotels.
 *
 * Note: DotwService::parseClassifications() returns 'id' (not 'code') — remapped here
 * to 'code' to match DotwCodeItem schema type.
 *
 * LOOKUP-03
 */
class DotwGetHotelClassifications
{
    /**
     * Resolve the getHotelClassifications query.
     *
     * @param  mixed  $root  Unused GraphQL root value
     * @param  array  $args  GraphQL arguments (none required for this query)
     * @return array GetHotelClassificationsResponse shape
     */
    public function __invoke(mixed $root, array $args): array
    {
        $companyId = auth()->user()?->company?->id;

        try {
            $dotwService = new DotwService($companyId);
            $rawClassifications = $dotwService->getHotelClassifications();
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
                    'error_message' => 'Failed to fetch hotel classifications: ' . $e->getMessage(),
                    'error_details' => $e->getMessage(),
                    'action' => 'RETRY',
                ],
                'meta' => $this->buildMeta($companyId ?? 0),
                'cached' => false,
                'data' => null,
            ];
        }

        // Remap 'id' key to 'code' to match DotwCodeItem schema type
        $classifications = array_map(fn (array $c) => [
            'code' => $c['id'] ?? $c['code'] ?? '',
            'name' => $c['name'] ?? '',
        ], $rawClassifications);

        return [
            'success' => true,
            'error' => null,
            'meta' => $this->buildMeta($companyId ?? 0),
            'cached' => false,
            'data' => [
                'classifications' => $classifications,
                'total_count' => count($classifications),
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
