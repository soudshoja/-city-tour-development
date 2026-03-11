<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Services\DotwService;
use Exception;
use RuntimeException;

/**
 * Resolver for the getAmenityIds GraphQL query.
 *
 * Returns merged amenity, leisure, and business facility codes from three DOTW commands:
 * - getamenitiesids (category: 'amenity')
 * - getleisureids (category: 'leisure')
 * - getbusinessids (category: 'business')
 *
 * DotwService::getAmenityIds() handles the merge and partial failure tolerance.
 * Use these codes as amenity filter values in searchHotels requests.
 *
 * LOOKUP-05, LOOKUP-06, LOOKUP-07 (amenity subset)
 */
class DotwGetAmenityIds
{
    /**
     * Resolve the getAmenityIds query.
     *
     * @param  mixed  $root  Unused GraphQL root value
     * @param  array  $args  GraphQL arguments (none required for this query)
     * @return array GetAmenityIdsResponse shape
     */
    public function __invoke(mixed $root, array $args): array
    {
        $companyId = auth()->user()?->company?->id;

        try {
            $dotwService = new DotwService($companyId);
            // getAmenityIds merges 3 commands — partial failure is tolerated internally
            $amenities = $dotwService->getAmenityIds();
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
                    'error_message' => 'Failed to fetch amenity IDs: ' . $e->getMessage(),
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
                'amenities' => $amenities,
                'total_count' => count($amenities),
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
