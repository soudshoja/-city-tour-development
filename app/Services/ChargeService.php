<?php

namespace App\Services;

use App\Models\PaymentMethod;
use App\Models\Charge;
use Illuminate\Support\Facades\Log;

class ChargeService
{
    /**
     * Calculate charge for PAYMENT (what client pays)
     * - Percent: Round UP (ceil)
     * - Flat Rate: Exact amount
     */
    public static function calculateChargeForPayment(float $baseAmount, float $chargeValue, string $chargeType): float
    {
        if ($chargeType === 'Percent') {
            return ceil(($chargeValue / 100) * $baseAmount);
        }
        return $chargeValue;
    }

    /**
     * Calculate charge for COA/PROFIT (internal accounting)
     * - NEVER round - always exact calculation
     */
    public static function calculateChargeForAccounting(float $baseAmount, float $chargeValue, string $chargeType): float
    {
        if ($chargeType === 'Percent') {
            return round(($chargeValue / 100) * $baseAmount, 3);
        }
        return $chargeValue;
    }

    /**
     * MAIN METHOD - Calculate charges for any gateway
     * 
     * @param float $amount Transaction amount
     * @param int $companyId Company ID
     * @param int|null $methodId Payment method ID (from payment_methods table)
     * @param string|null $gatewayName Gateway name (fallback if no methodId)
     * @return array Charge calculation result
     */
    public static function calculate(
        float $amount,
        int $companyId,
        ?int $methodId = null,
        ?string $gatewayName = null
    ): array {
        $chargeValue = 0;
        $chargeType = 'Flat Rate';
        $paidBy = 'Company';
        $selfCharge = null;
        $serviceCharge = null;

        // Priority 1: Get from payment_methods table
        if ($methodId) {
            $method = PaymentMethod::find($methodId);
            if ($method) {
                // Priority: self_charge > service_charge
                $selfCharge = $method->self_charge;
                $serviceCharge = $method->service_charge;
                $chargeValue = $selfCharge ?? $serviceCharge ?? 0;
                $chargeType = $method->charge_type ?? 'Flat Rate';
                $paidBy = $method->paid_by ?? 'Company';
            }
        }

        // Priority 2: Fallback to charges table (gateway-level) if no method or no charge found
        if ($chargeValue <= 0 && $gatewayName) {
            $charge = Charge::where('name', $gatewayName)
                ->where('company_id', $companyId)
                ->first();

            if (!$charge) {
                $charge = Charge::where('name', 'LIKE', '%' . $gatewayName . '%')
                    ->where('company_id', $companyId)
                    ->first();
            }

            if ($charge) {
                $selfCharge = $charge->self_charge;
                $chargeValue = !is_null($selfCharge) ? $selfCharge : ($charge->amount ?? 0);
                $chargeType = !is_null($selfCharge)
                    ? ($charge->self_charge_type ?? 'Flat Rate')
                    : ($charge->charge_type ?? 'Percent');
                $paidBy = $charge->paid_by ?? 'Company';
            }
        }

        // No charge configuration found
        if ($chargeValue <= 0) {
            return [
                'finalAmount' => $amount,
                'serviceCharge' => 0,
                'accountingFee' => 0,
                'paid_by' => $paidBy,
                'charge_type' => $chargeType,
                'self_charge' => null,
                'service_charge' => null,
            ];
        }

        $feeForPayment = self::calculateChargeForPayment($amount, $chargeValue, $chargeType);
        $feeForAccounting = self::calculateChargeForAccounting($amount, $chargeValue, $chargeType);

        $clientPays = ($paidBy === 'Client') ? $feeForPayment : 0;
        $finalAmount = $amount + $clientPays;

        Log::info('ChargeService::calculate', [
            'amount' => $amount,
            'charge_value' => $chargeValue,
            'charge_type' => $chargeType,
            'paid_by' => $paidBy,
            'fee_for_payment' => $feeForPayment,
            'fee_for_accounting' => $feeForAccounting,
            'client_pays' => $clientPays,
            'final_amount' => $finalAmount,
            'company_id' => $companyId,
            'method_id' => $methodId,
        ]);

        return [
            'finalAmount' => $finalAmount,           // Total client pays (amount + fee if client pays)
            'gatewayFee' => $clientPays,         // Fee added to invoice after round up (0 if company pays)
            'accountingFee' => $feeForAccounting,   // For COA/profit: exact service charge
            'paid_by' => $paidBy,
            'charge_type' => $chargeType,
            'self_charge' => $selfCharge,
            'service_charge' => $serviceCharge,
        ];
    }
}
