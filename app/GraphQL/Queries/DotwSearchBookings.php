<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Services\DotwService;
use Exception;

/**
 * Resolver for the searchBookings GraphQL query.
 *
 * Searches DOTW bookings by date range and/or customer reference.
 * Calls DOTW searchbookings command and returns a list of booking summaries.
 *
 * Implemented as part of Phase 9 Plan 01 (BOOK-05).
 * This stub was created by Plan 03 to unblock schema compilation during parallel execution.
 */
class DotwSearchBookings
{
    /**
     * Resolve the searchBookings query.
     *
     * @param  mixed  $root   Unused GraphQL root value
     * @param  array  $args   GraphQL arguments — from_date, to_date, customer_reference
     * @return array SearchBookingsResponse shape
     */
    public function __invoke(mixed $root, array $args): array
    {
        $companyId = auth()->user()?->company?->id;

        try {
            $dotwService = new DotwService($companyId);

            $params = [];
            if (! empty($args['from_date'])) {
                $params['fromDate'] = $args['from_date'];
            }
            if (! empty($args['to_date'])) {
                $params['toDate'] = $args['to_date'];
            }
            if (! empty($args['customer_reference'])) {
                $params['customerReference'] = $args['customer_reference'];
            }

            $bookings = $dotwService->searchBookings($params);
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => [
                    'error_code' => 'API_ERROR',
                    'error_message' => 'Failed to search bookings: ' . $e->getMessage(),
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
                'bookings' => array_map(fn (array $b) => [
                    'booking_code' => $b['bookingCode'] ?? '',
                    'customer_reference' => $b['customerReference'] ?? '',
                    'status' => $b['status'] ?? '',
                    'hotel_id' => $b['hotelId'] ?? '',
                    'from_date' => $b['fromDate'] ?? '',
                    'to_date' => $b['toDate'] ?? '',
                    'total_amount' => $b['totalAmount'] ?? 0.0,
                    'currency' => $b['currency'] ?? '',
                ], $bookings),
                'total_count' => count($bookings),
            ],
        ];
    }

    /**
     * Build the DotwMeta array for this response.
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
