<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\DotwPrebook;
use App\Services\DotwAuditService;
use App\Services\DotwService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Resolver for the blockRates GraphQL mutation.
 *
 * Locks a selected hotel rate for 3 minutes via DOTW V4 getRooms(blocking=true).
 * Creates a dotw_prebooks record with prebook_key (UUID) and expires_at.
 *
 * Key rules:
 * - BLOCK-08: expire-old + create-new wrapped in DB::transaction() to prevent race condition.
 * - DotwService instantiated inside __invoke (per-request credentials — not constructor).
 * - Audit logging strategy (two-phase):
 *     Phase A: DotwService::getRooms() logs the raw blocking API call internally.
 *     Phase B: After DB::transaction(), DotwAuditService logs prebook_key + expires_at (BLOCK-07).
 *              This supplementary call is required because prebook_key does not exist at Phase A time.
 * - allocationDetails token passed to DotwService unmodified — any encoding corrupts it.
 * - hotel_name is optional input — DOTW getRooms does not return hotel metadata (SEARCH-06).
 * - BLOCK-06: reject if countdown < 60 seconds (allocation too close to expiry).
 *
 * @see graphql/dotw.graphql BlockRatesResponse, BlockRatesInput, BlockRatesData
 * @see \App\Models\DotwPrebook::activeForUser()
 * @see \App\Services\DotwService::getRooms()
 * @see \App\Services\DotwAuditService
 */
class DotwBlockRates
{
    /**
     * Rate basis ID to human-readable name mapping (same as DotwGetRoomRates).
     */
    private const RATE_BASIS_NAMES = [
        '1331' => 'Room Only',
        '1332' => 'Bed & Breakfast',
        '1333' => 'Half Board',
        '1334' => 'Full Board',
        '1335' => 'All Inclusive',
        '1336' => 'Self Catering',
    ];

    public function __construct(private readonly DotwAuditService $auditService) {}

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
        $hotelName = trim($input['hotel_name'] ?? '');   // optional — DOTW does not provide it
        $checkin = trim($input['checkin'] ?? '');
        $checkout = trim($input['checkout'] ?? '');
        $selectedRoomType = trim($input['selected_room_type'] ?? '');
        $selectedRateBasis = trim($input['selected_rate_basis'] ?? '');
        $allocationDetails = $input['allocation_details'] ?? '';  // raw — no trim, no encoding
        $rooms = $this->buildRoomsFromInput($input['rooms'] ?? []);
        $currency = trim($input['currency'] ?? '') ?: null;

        // BLOCK-02: Basic validation — allocation_details must be present
        if (empty($allocationDetails)) {
            return $this->errorResponse(
                'VALIDATION_ERROR',
                'allocationDetails token is required for rate blocking.',
                'RESUBMIT'
            );
        }

        // Build params for blocking call
        $params = [
            'fromDate' => $checkin,
            'toDate' => $checkout,
            'productId' => $hotelCode,
            'rooms' => $rooms,
            'roomTypeSelected' => [
                'code' => $selectedRoomType,
                'selectedRateBasis' => $selectedRateBasis,
                'allocationDetails' => $allocationDetails,   // raw token — not encoded (Pitfall 1)
            ],
        ];
        if ($currency !== null) {
            $params['currency'] = $currency;
        }

        // BLOCK-03: Call getRooms with blocking=true — locks rate for 3 minutes.
        // DotwService::getRooms() logs Phase A audit entry (raw API call) internally.
        // prebook_key is not yet known at this point — Phase B audit log follows after transaction.
        try {
            $dotwService = new DotwService($companyId);
            $blockedRooms = $dotwService->getRooms(
                $params,
                true,
                $resayilMessageId,
                $resayilQuoteId,
                $companyId
            );
        } catch (RuntimeException $e) {
            return $this->errorResponse(
                'CREDENTIALS_NOT_CONFIGURED',
                'DOTW credentials not configured for this company.',
                'RECONFIGURE_CREDENTIALS',
                $e->getMessage()
            );
        } catch (\Exception $e) {
            // DOTW blocking rejected — rate may no longer be available
            return $this->errorResponse(
                'RATE_UNAVAILABLE',
                'Rate is no longer available. Please search again.',
                'RESEARCH',
                $e->getMessage()
            );
        }

        // Extract the locked rate from blocking response
        // Blocking with roomTypeSelected returns one room with one rateBasis detail (Pitfall 5)
        $rate = $blockedRooms[0]['details'][0] ?? null;
        if ($rate === null) {
            return $this->errorResponse(
                'API_ERROR',
                'DOTW returned an unexpected response after blocking. Please try again.',
                'RETRY'
            );
        }

        // Compute expiry and countdown (always 3 minutes from now per DOTW spec)
        $expiresAt = now()->addMinutes(config('dotw.allocation_expiry_minutes', 3));
        $countdownSeconds = max(0, (int) now()->diffInSeconds($expiresAt, false));

        // BLOCK-06: Reject if < 60 seconds remaining (too close to expiry)
        if ($countdownSeconds < 60) {
            return $this->errorResponse(
                'ALLOCATION_EXPIRED',
                'Rate offer is too close to expiry. Please search again.',
                'RESEARCH'
            );
        }

        // Apply markup to the locked rate — MARKUP-03, MARKUP-04
        $markup = $dotwService->applyMarkup((float) ($rate['price'] ?? 0));
        $totalTax = (float) ($rate['taxes'] ?? 0);
        $isRefundable = ! ($rate['nonRefundable'] ?? false);
        $cancellationRules = $this->formatCancellationRules($rate['cancellationRules'] ?? []);

        // Generate prebook key — now that we have the key, we can log Phase B audit entry
        $prebookKey = (string) Str::uuid();

        // BLOCK-04 + BLOCK-08: Wrap expire-old + create-new in transaction (Pitfall 2 — race condition)
        DB::transaction(function () use (
            $companyId, $resayilMessageId, $prebookKey, $allocationDetails,
            $hotelCode, $hotelName, $selectedRoomType, $selectedRateBasis,
            $markup, $totalTax, $currency, $isRefundable, $expiresAt, $cancellationRules
        ) {
            // BLOCK-08: Expire all active prebooks for this (company, WhatsApp user) pair
            if ($resayilMessageId) {
                DotwPrebook::activeForUser($companyId, $resayilMessageId)
                    ->update(['expired_at' => now()]);
            }

            // BLOCK-04: Create new prebook record
            DotwPrebook::create([
                'prebook_key' => $prebookKey,
                'allocation_details' => $allocationDetails,   // raw token
                'hotel_code' => $hotelCode,
                'hotel_name' => $hotelName ?: $hotelCode,  // fallback to hotel_code if name not provided
                'room_type' => $selectedRoomType,
                'room_rate_basis' => $selectedRateBasis,
                'total_fare' => $markup['final_fare'],  // marked-up price stored
                'total_tax' => $totalTax,
                'original_currency' => $currency ?? '',
                'is_refundable' => $isRefundable,
                'expired_at' => $expiresAt,
                'company_id' => $companyId,
                'resayil_message_id' => $resayilMessageId,
                'booking_details' => [
                    'cancellation_rules' => $cancellationRules,
                    'markup' => $markup,
                    'trace_id' => app('dotw.trace_id'),
                    'rate_basis_name' => self::RATE_BASIS_NAMES[$selectedRateBasis] ?? 'Unknown',
                ],
            ]);
        });

        // BLOCK-07: Supplementary audit log entry after transaction completes.
        // Phase A (DotwService internal log) recorded the raw blocking API call but could not
        // include prebook_key (not yet generated). This Phase B entry records the commitment:
        // prebook_key, expires_at, and hotel_code so the audit trail is complete.
        // Use a non-throwing log — audit failure must not fail the booking response.
        try {
            $this->auditService->log(
                DotwAuditService::OP_BLOCK,
                [
                    'operation' => 'blockRates_prebook_created',
                    'prebook_key' => $prebookKey,
                    'allocation_expiry' => $expiresAt->toIso8601String(),
                    'hotel_code' => $hotelCode,
                    'room_type' => $selectedRoomType,
                    'rate_basis' => $selectedRateBasis,
                    'trace_id' => app('dotw.trace_id'),
                ],
                [
                    'prebook_key' => $prebookKey,
                    'expires_at' => $expiresAt->toIso8601String(),
                    'hotel_code' => $hotelCode,
                ],
                $resayilMessageId,
                $resayilQuoteId,
                $companyId
            );
        } catch (\Throwable) {
            // Audit failure is non-fatal — prebook was already created successfully
        }

        // BLOCK-05: Return prebook details with countdown
        return [
            'success' => true,
            'error' => null,
            'cached' => false,
            'data' => [
                'prebook_key' => $prebookKey,
                'expires_at' => $expiresAt->toIso8601String(),
                'countdown_timer_seconds' => $countdownSeconds,
                'hotel_code' => $hotelCode,
                'hotel_name' => $hotelName ?: $hotelCode,
                'room_type' => $selectedRoomType,
                'rate_basis' => $selectedRateBasis,
                'total_fare' => $markup['final_fare'],
                'total_tax' => $totalTax,
                'markup' => $markup,
                'is_refundable' => $isRefundable,
                'cancellation_rules' => $cancellationRules,
            ],
            'meta' => $this->buildMeta($companyId),
        ];
    }

    /**
     * Map GraphQL SearchHotelRoomInput to DotwService rooms array format.
     * Identical pattern to DotwSearchHotels and DotwGetRoomRates.
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
     * Build error response matching BlockRatesResponse shape.
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
