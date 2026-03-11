<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Services\DotwService;
use Exception;
use RuntimeException;

/**
 * Resolver for the getAllCountries GraphQL query.
 *
 * Returns all DOTW internal country codes via the getallcountries command.
 * These codes are used as nationality/residenceCountry values in booking passenger details.
 *
 * LOOKUP-01
 */
class DotwGetAllCountries
{
    /**
     * Resolve the getAllCountries query.
     *
     * @param  mixed  $root  Unused GraphQL root value
     * @param  array  $args  GraphQL arguments (none required for this query)
     * @return array GetAllCountriesResponse shape
     */
    public function __invoke(mixed $root, array $args): array
    {
        $companyId = auth()->user()?->company?->id;

        try {
            $dotwService = new DotwService($companyId);
            $countries = $dotwService->getCountryList();
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
                    'error_message' => 'Failed to fetch country list: ' . $e->getMessage(),
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
                'countries' => $countries,
                'total_count' => count($countries),
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
