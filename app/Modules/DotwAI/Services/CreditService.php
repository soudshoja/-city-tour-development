<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Services;

use App\Models\Credit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Credit balance operations with pessimistic locking for B2B bookings.
 *
 * All debit operations use DB::transaction + lockForUpdate to prevent
 * double-spending when concurrent booking confirmations run simultaneously.
 *
 * @see B2B-05 get_company_balance returns accurate credit figures
 * @see B2B-06 Credit deduction uses pessimistic locking (lockForUpdate)
 */
class CreditService
{
    /**
     * Check credit balance and deduct the booking amount atomically.
     *
     * Uses pessimistic locking (SELECT ... FOR UPDATE) inside a transaction
     * to prevent race conditions when multiple agents book concurrently.
     *
     * @param int    $clientId   The client's ID (resolved from company)
     * @param int    $companyId  The company ID for the credit entry
     * @param float  $amount     Booking amount to deduct (positive value)
     * @param string $prebookKey The booking reference for the credit description
     *
     * @return bool True if credit was deducted, false if insufficient balance
     */
    public function checkAndDeductCredit(
        int $clientId,
        int $companyId,
        float $amount,
        string $prebookKey,
    ): bool {
        return DB::transaction(function () use ($clientId, $companyId, $amount, $prebookKey): bool {
            // Pessimistic lock: sum locked balance for this client
            $balance = Credit::where('client_id', $clientId)
                ->lockForUpdate()
                ->sum('amount');

            if ((float) $balance < $amount) {
                Log::info('DotwAI: Insufficient credit for booking', [
                    'client_id'   => $clientId,
                    'company_id'  => $companyId,
                    'required'    => $amount,
                    'available'   => $balance,
                    'prebook_key' => $prebookKey,
                ]);

                return false;
            }

            // Deduct credit (negative amount = Invoice debit)
            Credit::create([
                'company_id'  => $companyId,
                'client_id'   => $clientId,
                'type'        => Credit::INVOICE,
                'amount'      => -$amount,
                'description' => "DOTW Hotel Booking: {$prebookKey}",
            ]);

            return true;
        });
    }

    /**
     * Refund a previously deducted booking amount back to the client's credit.
     *
     * Called when DOTW confirmation fails after credit was already deducted,
     * or when a booking is cancelled and the amount should be restored.
     *
     * @param int    $clientId   The client's ID
     * @param int    $companyId  The company ID
     * @param float  $amount     Amount to refund (positive value)
     * @param string $prebookKey The booking reference for the credit description
     */
    public function refundCredit(
        int $clientId,
        int $companyId,
        float $amount,
        string $prebookKey,
    ): void {
        Credit::create([
            'company_id'  => $companyId,
            'client_id'   => $clientId,
            'type'        => Credit::REFUND,
            'amount'      => $amount,
            'description' => "DOTW Booking Refund: {$prebookKey}",
        ]);
    }

    /**
     * Get the current credit balance breakdown for a client.
     *
     * - credit_limit  = sum of TOPUP + REFUND + INVOICE_REFUND amounts
     * - used_credit   = absolute sum of INVOICE amounts (these are stored negative)
     * - available_credit = credit_limit - used_credit
     *
     * @param int $clientId The client's ID
     * @return array{credit_limit: float, used_credit: float, available_credit: float}
     */
    public function getBalance(int $clientId): array
    {
        $credits = Credit::where('client_id', $clientId)->get();

        $creditLimit = $credits
            ->whereIn('type', [Credit::TOPUP, Credit::REFUND, Credit::INVOICE_REFUND])
            ->sum('amount');

        $usedCredit = abs($credits
            ->where('type', Credit::INVOICE)
            ->sum('amount'));

        $availableCredit = (float) $creditLimit - $usedCredit;

        return [
            'credit_limit'     => round((float) $creditLimit, 3),
            'used_credit'      => round($usedCredit, 3),
            'available_credit' => round($availableCredit, 3),
        ];
    }

    /**
     * Resolve the default client ID for a company.
     *
     * Looks up the first client associated with the company via agents.
     * Returns null if no client is found (caller must handle this as an error).
     *
     * @param int $companyId The company ID
     * @return int|null The first client's ID, or null if none found
     */
    public function getClientIdForCompany(int $companyId): ?int
    {
        try {
            $company = \App\Models\Company::find($companyId);

            if ($company === null) {
                return null;
            }

            // Find first client linked to an agent in this company
            $agent = \App\Models\Agent::whereHas('branch', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })->first();

            if ($agent === null) {
                return null;
            }

            $client = $agent->clients()->first();

            return $client?->id;
        } catch (\Throwable $e) {
            Log::error('DotwAI: Failed to resolve client_id for company', [
                'company_id' => $companyId,
                'error'      => $e->getMessage(),
            ]);

            return null;
        }
    }
}
