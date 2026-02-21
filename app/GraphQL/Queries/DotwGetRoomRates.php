<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Services\DotwService;
use RuntimeException;

/**
 * Resolver for the getRoomRates GraphQL query.
 *
 * Retrieves all room types and meal plans for a specific hotel via DOTW V4 getRooms
 * (blocking=false). Returns allocationDetails tokens required for blockRates.
 *
 * Key rules:
 * - getRoomRates is NEVER cached. Rates change and allocationDetails tokens expire.
 * - Audit logging handled internally by DotwService::getRooms() — do NOT call
 *   DotwAuditService here (avoids double-logging, established pattern from Phase 4).
 * - DotwService instantiated once inside __invoke (not constructor) — per-request credentials.
 * - allocationDetails token passed through raw — any modification corrupts it.
 *
 * @see graphql/dotw.graphql GetRoomRatesResponse, GetRoomRatesInput, RateDetail
 * @see \App\Services\DotwService::getRooms()
 */
class DotwGetRoomRates
{
    /**
     * Rate basis ID to human-readable name mapping.
     * Source: DOTW V4 API spec and DotwService constants.
     */
    private const RATE_BASIS_NAMES = [
        '1331' => 'Room Only',
        '1332' => 'Bed & Breakfast',
        '1333' => 'Half Board',
        '1334' => 'Full Board',
        '1335' => 'All Inclusive',
        '1336' => 'Self Catering',
    ];

    public function __invoke($root, array $args, $context = null): array
    {
        $input = $args['input'] ?? [];
        $request = $context?->request();

        // Extract Resayil IDs from request attributes (ResayilContextMiddleware)
        $resayilMessageId = $request?->attributes->get('resayil_message_id');
        $resayilQuoteId = $request?->attributes->get('resayil_quote_id');

        // Resolve company — B2B always requires authenticated company
        $companyId = auth()->user()?->company?->id;
        if ($companyId === null) {
            return $this->errorResponse(
                'CREDENTIALS_NOT_CONFIGURED',
                'No authenticated company context. Company credentials are required.',
                'RECONFIGURE_CREDENTIALS'
            );
        }

        $hotelCode = trim($input['hotel_code'] ?? '');
        $checkin = trim($input['checkin'] ?? '');
        $checkout = trim($input['checkout'] ?? '');
        $rooms = $this->buildRoomsFromInput($input['rooms'] ?? []);
        $currency = trim($input['currency'] ?? '') ?: null;

        // Build getRooms params — include allocationDetails in fields array (required)
        $params = [
            'fromDate' => $checkin,
            'toDate' => $checkout,
            'productId' => $hotelCode,
            'rooms' => $rooms,
            'fields' => ['cancellation', 'allocationDetails', 'tariffNotes'],
        ];
        if ($currency !== null) {
            $params['currency'] = $currency;
        }

        try {
            // Instantiate DotwService once inside __invoke (per-request credentials, not constructor).
            // The same $dotwService instance is passed to formatRooms() — single DB credential load.
            $dotwService = new DotwService($companyId);

            // blocking=false — browse only; audit logged internally by DotwService::getRooms()
            $rawRooms = $dotwService->getRooms(
                $params,
                false,
                $resayilMessageId,
                $resayilQuoteId,
                $companyId
            );
        } catch (RuntimeException $e) {
            // CRED-05: missing or invalid credentials
            return $this->errorResponse(
                'CREDENTIALS_NOT_CONFIGURED',
                'DOTW credentials not configured for this company.',
                'RECONFIGURE_CREDENTIALS',
                $e->getMessage()
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'API_ERROR',
                'Failed to retrieve room rates. Please try again.',
                'RETRY',
                $e->getMessage()
            );
        }

        // Format rooms — apply markup to every rate (MARKUP-03, MARKUP-04)
        // Pass the already-instantiated $dotwService — do NOT create a second instance here.
        $formattedRooms = $this->formatRooms($rawRooms, $dotwService, $currency);

        return [
            'success' => true,
            'error' => null,
            'cached' => false,   // getRoomRates is never cached
            'data' => [
                'hotel_code' => $hotelCode,
                'rooms' => $formattedRooms,
                'total_count' => count($formattedRooms),
            ],
            'meta' => $this->buildMeta($companyId),
        ];
    }

    /**
     * Format raw rooms from DotwService::getRooms() into GetRoomRatesData shape.
     *
     * parseRooms() returns: [['roomTypeCode' => '...', 'roomName' => '...', 'details' => [...]]]
     * Each detail has: id (rateBasisId), price, taxes, allocationDetails, cancellationRules, status.
     *
     * Applies applyMarkup() to every detail.price — MARKUP-04 consistency requirement.
     * allocationDetails passed through raw — no encoding (Pitfall 1 in RESEARCH.md).
     *
     * RATE-05: original_currency extracted from DOTW rate detail when present; falls back to
     * the currency passed in the request (or empty string). exchange_rate from DOTW when provided.
     * final_currency matches original_currency (DOTW handles conversion server-side when requested).
     *
     * @param  string|null  $requestCurrency  Currency from the input (may be null)
     */
    private function formatRooms(array $rawRooms, DotwService $dotwService, ?string $requestCurrency = null): array
    {
        return array_map(function (array $room) use ($dotwService, $requestCurrency) {
            return [
                'room_type_code' => $room['roomTypeCode'] ?? '',
                'room_name' => $room['roomName'] ?? '',
                'rate_details' => array_map(function (array $detail) use ($dotwService, $requestCurrency) {
                    $rateBasisId = (string) ($detail['id'] ?? '');
                    $markup = $dotwService->applyMarkup((float) ($detail['price'] ?? 0));
                    $totalFare = (float) ($detail['price'] ?? 0);
                    $totalTaxes = (float) ($detail['taxes'] ?? 0);

                    // RATE-05: currency fields — DOTW may include 'currency' and 'exchangeRate'
                    // per rate detail. Fall back to request currency or empty string.
                    $originalCurrency = (string) ($detail['currency'] ?? $requestCurrency ?? '');
                    $exchangeRate = isset($detail['exchangeRate']) ? (float) $detail['exchangeRate'] : null;
                    $finalCurrency = $originalCurrency; // DOTW handles conversion server-side

                    return [
                        'rate_basis_id' => $rateBasisId,
                        'rate_basis_name' => self::RATE_BASIS_NAMES[$rateBasisId] ?? 'Unknown',
                        'is_refundable' => ! ($detail['nonRefundable'] ?? false),
                        'total_fare' => $totalFare,
                        'total_taxes' => $totalTaxes,
                        'total_price' => $totalFare + $totalTaxes,
                        'markup' => $markup,       // RateMarkup shape: original_fare, markup_percent, markup_amount, final_fare
                        'allocation_details' => $detail['allocationDetails'] ?? '',  // raw token — never modified
                        'cancellation_rules' => $this->formatCancellationRules($detail['cancellationRules'] ?? []),
                        'original_currency' => $originalCurrency,   // RATE-05
                        'exchange_rate' => $exchangeRate,            // RATE-05 — null when no conversion
                        'final_currency' => $finalCurrency,          // RATE-05
                    ];
                }, $room['details'] ?? []),
            ];
        }, $rawRooms);
    }

    /**
     * Map GraphQL SearchHotelRoomInput to DotwService rooms array format.
     * Identical to DotwSearchHotels::buildRoomsFromInput() — shared pattern.
     */
    private function buildRoomsFromInput(array $roomInputs): array
    {
        return array_map(fn (array $room) => [
            'adultsCode' => $room['adultsCode'],
            'children' => $room['children'] ?? [],
            'passengerNationality' => $room['passengerNationality'] ?? null,
            'passengerCountryOfResidence' => $room['passengerCountryOfResidence'] ?? null,
        ], $roomInputs);
    }

    /**
     * Format DOTW cancellation rules into CancellationRule schema shape.
     * DOTW returns: [['fromDate' => '...', 'toDate' => '...', 'charge' => ..., 'cancelCharge' => ...]]
     */
    private function formatCancellationRules(array $rules): array
    {
        return array_map(fn (array $rule) => [
            'from_date' => $rule['fromDate'] ?? '',
            'to_date' => $rule['toDate'] ?? '',
            'charge' => (float) ($rule['charge'] ?? 0),
            'cancel_charge' => (float) ($rule['cancelCharge'] ?? 0),
        ], $rules);
    }

    /**
     * Build DotwMeta array — identical pattern across all DOTW resolvers.
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
     * Build error response matching GetRoomRatesResponse shape.
     */
    private function errorResponse(string $code, string $message, string $action, ?string $details = null): array
    {
        return [
            'success' => false,
            'error' => [
                'error_code' => $code,
                'error_message' => $message,
                'error_details' => $details,
                'action' => $action,
            ],
            'cached' => false,
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
