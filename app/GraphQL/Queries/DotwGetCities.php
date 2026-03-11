<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Exceptions\DotwTimeoutException;
use App\Services\DotwService;
use Exception;
use RuntimeException;

/**
 * Resolver for the getCities GraphQL query.
 *
 * Returns the list of DOTW-serveable cities for a given country code.
 * Agents use city codes from this response as the `destination` input to searchHotels.
 *
 * Uses per-company credentials from the authenticated user's company context.
 * Returns DotwResponseEnvelope shape (GetCitiesResponse) with trace_id from DotwTraceMiddleware.
 */
class DotwGetCities
{
    /**
     * Resolve the getCities query.
     *
     * @param  mixed  $root  Unused GraphQL root value
     * @param  array  $args  GraphQL arguments — expects 'country_code' string
     * @param  mixed|null  $context  Lighthouse context — provides request() for attribute access
     * @return array GetCitiesResponse shape
     */
    public function __invoke($root, array $args, $context = null): array
    {
        $countryCode = strtoupper(trim($args['country_code'] ?? ''));

        // Resolve company from authenticated user (B2B path always requires auth)
        $companyId = auth()->user()?->company?->id;

        if ($companyId === null) {
            return $this->errorResponse(
                'CREDENTIALS_NOT_CONFIGURED',
                'No authenticated company context. Company credentials are required.',
                'RECONFIGURE_CREDENTIALS'
            );
        }

        if (strlen($countryCode) !== 2) {
            return $this->errorResponse(
                'VALIDATION_ERROR',
                'country_code must be a 2-letter ISO 3166-1 alpha-2 code (e.g. AE, KW, GB).',
                'RETRY'
            );
        }

        try {
            $dotwService = new DotwService($companyId);
            $rawCities = $dotwService->getCityList($countryCode);

            // parseCityList() returns arrays with keys 'code' and 'name' directly
            $cities = array_map(fn (array $c) => [
                'code' => $c['code'] ?? $c['cityCode'] ?? '',
                'name' => $c['name'] ?? $c['cityName'] ?? '',
            ], $rawCities);

            return [
                'success' => true,
                'error' => null,
                'meta' => $this->buildMeta($companyId),
                'data' => [
                    'cities' => $cities,
                    'total_count' => count($cities),
                ],
            ];
        } catch (DotwTimeoutException $e) {
            return $this->errorResponse(
                'API_TIMEOUT',
                'Search taking too long, please try again',
                'RETRY',
                $e->getMessage()
            );
        } catch (RuntimeException $e) {
            return $this->errorResponse(
                'CREDENTIALS_NOT_CONFIGURED',
                'DOTW credentials not configured for this company.',
                'RECONFIGURE_CREDENTIALS',
                $e->getMessage()
            );
        } catch (Exception $e) {
            return $this->errorResponse(
                'API_ERROR',
                'Failed to retrieve city list. Please try again.',
                'RETRY',
                $e->getMessage()
            );
        }
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

    /**
     * Build a structured error response matching GetCitiesResponse shape.
     *
     * @param  string  $code  DotwErrorCode enum value
     * @param  string  $message  User-friendly error message
     * @param  string  $action  DotwErrorAction enum value (e.g. RETRY, RECONFIGURE_CREDENTIALS)
     * @param  string|null  $details  Technical details for debugging (never shown to end users)
     */
    private function errorResponse(
        string $code,
        string $message,
        string $action,
        ?string $details = null
    ): array {
        return [
            'success' => false,
            'error' => [
                'error_code' => $code,
                'error_message' => $message,
                'error_details' => $details,
                'action' => $action,
            ],
            'meta' => [
                'trace_id' => app('dotw.trace_id'),
                'request_id' => app('dotw.trace_id'),
                'timestamp' => now()->toIso8601String(),
                'company_id' => 0,
            ],
            'data' => null,
        ];
    }
}
