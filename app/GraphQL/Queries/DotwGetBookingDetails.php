<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Services\DotwService;
use Exception;

/**
 * Resolver for the getBookingDetails GraphQL query.
 *
 * Returns full details of an existing DOTW booking by booking code.
 * Calls DOTW getbookingdetails command and returns structured booking data.
 *
 * Implemented: returns BookingDetails with schema-correct field names (gap closure 09-05, BOOK-04).
 */
class DotwGetBookingDetails
{
    /**
     * Resolve the getBookingDetails query.
     *
     * @param  mixed  $root   Unused GraphQL root value
     * @param  array  $args   GraphQL arguments — expects 'booking_code' string
     * @return array GetBookingDetailsResponse shape
     */
    public function __invoke(mixed $root, array $args): array
    {
        $companyId = auth()->user()?->company?->id;

        try {
            $dotwService = new DotwService($companyId);
            $bookingCode = $args['booking_code'] ?? '';
            $details = $dotwService->getBookingDetail($bookingCode);
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => [
                    'error_code' => 'API_ERROR',
                    'error_message' => 'Failed to fetch booking details: ' . $e->getMessage(),
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
                'booking_code'       => $details['bookingCode'] ?? '',
                'hotel_code'         => $details['hotelCode'] ?? '',
                'from_date'          => $details['fromDate'] ?? '',
                'to_date'            => $details['toDate'] ?? '',
                'status'             => $details['status'] ?? '',
                'customer_reference' => $details['customerReference'] ?? '',
                'total_amount'       => (float) ($details['totalAmount'] ?? 0.0),
                'currency'           => $details['currency'] ?? '',
                'passengers'         => json_encode($details['passengerDetails'] ?? []),
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
