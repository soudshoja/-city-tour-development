<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Services;

use App\Modules\DotwAI\DTOs\DotwAIContext;
use App\Modules\DotwAI\Models\DotwAIBooking;
use App\Services\DotwService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Two-step cancellation orchestration for DOTW hotel bookings.
 *
 * Step 1 (confirm=no): Calls DOTW to preview penalty amount without executing cancellation.
 *   Returns penalty preview and transitions booking to STATUS_CANCELLATION_PENDING.
 *
 * Step 2 (confirm=yes): Calls DOTW to execute the cancellation, then creates accounting
 *   entries inside a DB transaction (Invoice + JournalEntry for penalty > 0).
 *   B2B bookings receive a credit refund of (original - penalty) if positive.
 *
 * IMPORTANT: DOTW API call is made BEFORE opening the DB transaction, since HTTP calls
 * cannot be rolled back. If the DB transaction fails after DOTW succeeds, we log a
 * critical error and return success to the user (the cancellation did happen on DOTW side).
 *
 * @see CANC-01 2-step cancel with confirm=no preview
 * @see CANC-02 WhatsApp messages for both steps (with DOTW delay warning on confirm)
 * @see CANC-03 Penalty > 0 triggers Invoice + JournalEntry creation
 * @see CANC-04 Free cancellation updates status only (no accounting entries)
 */
class CancellationService
{
    public function __construct(
        private readonly AccountingService $accountingService,
        private readonly CreditService $creditService,
    ) {}

    /**
     * Execute the 2-step cancellation flow.
     *
     * @param  DotwAIContext  $context  Resolved company/agent context
     * @param  array  $input  Validated input: prebook_key, confirm ('no'|'yes'),
     *                        penalty_amount (required when confirm=yes)
     * @return array Result array suitable for DotwAIResponse::success/error
     */
    public function cancel(DotwAIContext $context, array $input): array
    {
        // Resolve booking for this company
        $booking = DotwAIBooking::where('prebook_key', $input['prebook_key'])
            ->where('company_id', $context->companyId)
            ->first();

        if ($booking === null) {
            return [
                'error' => true,
                'code' => DotwAIResponse::PREBOOK_NOT_FOUND,
                'message' => "Booking not found: {$input['prebook_key']}",
            ];
        }

        // Booking must be in a cancellable state
        $cancellableStatuses = [
            DotwAIBooking::STATUS_CONFIRMED,
            DotwAIBooking::STATUS_CANCELLATION_PENDING,
        ];

        if (! in_array($booking->status, $cancellableStatuses, true)) {
            return [
                'error' => true,
                'code' => DotwAIResponse::CANCELLATION_NOT_ALLOWED,
                'message' => "Booking cannot be cancelled in status: {$booking->status}",
            ];
        }

        // Cannot cancel if the booking was never confirmed with DOTW
        if (empty($booking->booking_ref)) {
            return [
                'error' => true,
                'code' => DotwAIResponse::CANCELLATION_NOT_ALLOWED,
                'message' => 'Booking has no DOTW booking reference — cannot cancel.',
            ];
        }

        $confirm = $input['confirm'];

        return $confirm === 'yes'
            ? $this->executeConfirmedCancellation($booking, $context, $input)
            : $this->executePreviewCancellation($booking, $context);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Step 1: Preview mode — call DOTW with confirm=no to fetch penalty without cancelling.
     *
     * CERT-13: fires a separate cancelBooking call for each booking code in booking_refs,
     * sums charges/refunds across all rooms, and returns the aggregate to the caller.
     */
    private function executePreviewCancellation(
        DotwAIBooking $booking,
        DotwAIContext $context,
    ): array {
        $bookingRefs = $this->getBookingRefs($booking);
        if (empty($bookingRefs)) {
            return [
                'error' => true,
                'code' => DotwAIResponse::CANCELLATION_NOT_ALLOWED,
                'message' => 'Booking has no DOTW booking references — cannot cancel.',
            ];
        }

        $totalCharge = 0.0;
        $totalRefund = 0.0;
        $perRoomResults = [];

        foreach ($bookingRefs as $ref) {
            try {
                $dotwService = new DotwService($context->companyId);
                $result = $dotwService->cancelBooking([
                    'confirm' => 'no',
                    'bookingCode' => $ref,
                ]);
                $totalCharge += (float) ($result['charge'] ?? 0);
                $totalRefund += (float) ($result['refund'] ?? 0);
                $perRoomResults[$ref] = $result;
            } catch (\Throwable $e) {
                Log::channel('dotw')->error('[CancellationService] DOTW cancelBooking preview failed (per-room)', [
                    'prebook_key' => $booking->prebook_key,
                    'booking_ref' => $ref,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'error' => true,
                    'code' => DotwAIResponse::DOTW_API_ERROR,
                    'message' => 'DOTW cancellation preview failed for room '.$ref.': '.$e->getMessage(),
                ];
            }
        }

        // Transition to cancellation_pending so we know preview was viewed
        $booking->update(['status' => DotwAIBooking::STATUS_CANCELLATION_PENDING]);

        $charge = $totalCharge;
        $refund = $totalRefund;
        $currency = $booking->display_currency ?? 'KWD';

        $messageData = [
            'hotel_name' => $booking->hotel_name ?? 'Hotel',
            'check_in' => $booking->check_in?->format('Y-m-d') ?? '',
            'check_out' => $booking->check_out?->format('Y-m-d') ?? '',
            'penalty_amount' => $charge,
            'currency' => $currency,
            'booking_ref' => $booking->booking_ref,
            'refund_amount' => $refund,
            'per_room_results' => $perRoomResults, // CERT-13: per-room breakdown
        ];

        return [
            'prebook_key' => $booking->prebook_key,
            'booking_ref' => $booking->booking_ref,
            'hotel_name' => $booking->hotel_name,
            'penalty_amount' => $charge,
            'refund_amount' => $refund,
            'currency' => $currency,
            'step' => 'preview',
            '_message_data' => $messageData,
        ];
    }

    /**
     * Step 2: Execute mode — call DOTW with confirm=yes, then commit accounting entries.
     *
     * Order of operations:
     * 1. Call DOTW API (cannot be rolled back)
     * 2. Open DB::transaction for Eloquent writes
     * 3. Update booking status, create Invoice + JournalEntry (if penalty > 0)
     * 4. Refund B2B credit if applicable
     *
     * If DOTW succeeds but the DB transaction fails, we log a critical error and
     * still return success to the user (the cancellation happened on DOTW's side).
     */
    private function executeConfirmedCancellation(
        DotwAIBooking $booking,
        DotwAIContext $context,
        array $input,
    ): array {
        $penaltyAmount = (float) ($input['penalty_amount'] ?? 0);

        $bookingRefs = $this->getBookingRefs($booking);
        if (empty($bookingRefs)) {
            return [
                'error' => true,
                'code' => DotwAIResponse::CANCELLATION_NOT_ALLOWED,
                'message' => 'Booking has no DOTW booking references — cannot cancel.',
            ];
        }

        // ── 1. Call DOTW API first (outside any transaction) — one call per ref ─
        // CERT-13: each booking code (one per room) needs its own cancelBooking call.
        // Distribute penalty evenly across refs. This is a reasonable approximation
        // for cert; a future plan can replace with per-room preview-charge memoization.
        // TODO(CERT-13-future): store per-room preview charges in booking_refs metadata
        //   so we can pass the exact penalty per code rather than an even split.
        $totalRefsCount = count($bookingRefs);
        $perCallPenalty = $totalRefsCount > 0 ? ($penaltyAmount / $totalRefsCount) : 0.0;
        $totalCharge = 0.0;
        $totalRefund = 0.0;
        $allResults = [];
        $cancelledRefs = [];

        foreach ($bookingRefs as $ref) {
            try {
                $dotwService = new DotwService($context->companyId);
                $result = $dotwService->cancelBooking([
                    'confirm' => 'yes',
                    'bookingCode' => $ref,
                    'penaltyApplied' => $perCallPenalty,
                ]);
                $totalCharge += (float) ($result['charge'] ?? 0);
                $totalRefund += (float) ($result['refund'] ?? 0);
                $allResults[$ref] = $result;
                $cancelledRefs[] = $ref;
            } catch (\Throwable $e) {
                Log::channel('dotw')->error('[CancellationService] DOTW cancelBooking execute failed (per-room)', [
                    'prebook_key' => $booking->prebook_key,
                    'booking_ref' => $ref,
                    'cancelled_so_far' => $cancelledRefs,
                    'error' => $e->getMessage(),
                ]);

                // Partial-failure: DOTW already cancelled some refs — mixed state.
                // Mark as failed so admin can reconcile; surface which refs succeeded.
                $booking->update(['status' => DotwAIBooking::STATUS_FAILED]);

                return [
                    'error' => true,
                    'code' => DotwAIResponse::DOTW_API_ERROR,
                    'message' => 'DOTW cancellation partially failed at room '.$ref.': '.$e->getMessage()
                        .' (cancelled: '.implode(',', $cancelledRefs).')',
                ];
            }
        }

        $charge = $totalCharge;
        $refund = $totalRefund;
        $currency = $booking->display_currency ?? 'KWD';

        // ── 2. Commit DB writes inside a transaction ──────────────────────
        try {
            DB::transaction(function () use ($booking, $charge, $context): void {
                // Mark booking as cancelled
                $booking->update(['status' => DotwAIBooking::STATUS_CANCELLED]);

                // Create accounting entries only if penalty was charged
                if ($charge > 0) {
                    $this->accountingService->createCancellationEntries($booking, $charge, $context);
                }

                // B2B: refund the net amount back to credit line
                if ($booking->track === DotwAIBooking::TRACK_B2B) {
                    $originalFare = (float) ($booking->display_total_fare ?? 0);
                    $refundAmount = $originalFare - $charge;

                    if ($refundAmount > 0) {
                        $clientId = $this->creditService->getClientIdForCompany($context->companyId);

                        if ($clientId !== null) {
                            $this->creditService->refundCredit(
                                $clientId,
                                $context->companyId,
                                $refundAmount,
                                $booking->prebook_key,
                            );
                        } else {
                            Log::warning('[CancellationService] Could not resolve clientId for B2B credit refund', [
                                'prebook_key' => $booking->prebook_key,
                                'company_id' => $context->companyId,
                            ]);
                        }
                    }
                }
            });
        } catch (\Throwable $e) {
            // CRITICAL: DOTW cancellation succeeded but our DB writes failed.
            // The cancellation did happen — inform the user but log the discrepancy.
            Log::critical('[CancellationService] DB transaction failed after successful DOTW cancellation', [
                'prebook_key' => $booking->prebook_key,
                'booking_ref' => $booking->booking_ref,
                'all_results' => $allResults,
                'error' => $e->getMessage(),
            ]);

            // Best-effort: update booking status outside transaction
            try {
                $booking->status = DotwAIBooking::STATUS_CANCELLED;
                $booking->save();
            } catch (\Throwable $saveException) {
                Log::critical('[CancellationService] Could not save cancelled status after transaction failure', [
                    'prebook_key' => $booking->prebook_key,
                    'error' => $saveException->getMessage(),
                ]);
            }
        }

        $isFree = $charge <= 0;
        $messageData = [
            'hotel_name' => $booking->hotel_name ?? 'Hotel',
            'booking_ref' => $booking->booking_ref,
            'penalty_amount' => $charge,
            'currency' => $currency,
            'is_free_cancellation' => $isFree,
        ];

        return [
            'prebook_key' => $booking->prebook_key,
            'booking_ref' => $booking->booking_ref,
            'hotel_name' => $booking->hotel_name,
            'status' => DotwAIBooking::STATUS_CANCELLED,
            'penalty_amount' => $charge,
            'refund_amount' => $refund,
            'currency' => $currency,
            'is_free_cancellation' => $isFree,
            'step' => 'confirmed',
            '_message_data' => $messageData,
        ];
    }

    /**
     * Return the list of DOTW booking codes for this booking.
     *
     * Multi-room bookings (CERT-13) have a separate code per room — each must be
     * cancelled with its own cancelBooking call. Single-room bookings or bookings
     * created before the booking_refs column was added fall back to [booking_ref].
     *
     * @return string[] List of DOTW booking codes (always at least 1 element when booking_ref set)
     */
    private function getBookingRefs(DotwAIBooking $booking): array
    {
        $refs = $booking->booking_refs ?? [];
        if (is_array($refs) && count($refs) > 0) {
            return array_values(array_filter($refs, fn ($r) => is_string($r) && $r !== ''));
        }

        // Fallback: legacy booking with only the singular booking_ref column populated.
        return $booking->booking_ref ? [$booking->booking_ref] : [];
    }
}
