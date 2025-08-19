<?php

namespace App\Services;

use App\Models\PaymentMethod;
use App\Models\Charge;
use App\Models\Agent;
use Illuminate\Support\Facades\Log;

class ChargeService
{
    /**
     * Standard return structure for all charge calculations
     */
    private static function standardReturn(
        float $finalAmount,
        float $fee,
        ?string $paidBy,
        float $netReceived,
        ?string $chargeType = null,
        ?string $selfChargeType = null,
        ?float $selfCharge = null,
        ?float $apiServiceCharge = null
    ): array {
        return [
            'finalAmount' => $finalAmount,
            'fee' => $fee,
            'paid_by' => $paidBy,
            'netReceived' => $netReceived,
            'charge_type' => $chargeType,
            'self_charge_type' => $selfChargeType,
            'self_charge' => $selfCharge,
            'api_service_charge' => $apiServiceCharge,
            'amount' => $fee,
        ];
    }

    /**
     * Calculate charge amount based on type (Percent or Fixed)
     */
    private static function calculateChargeAmount(float $baseAmount, float $chargeValue, string $chargeType): float
    {
        if ($chargeType === 'Percent') {
            return ($chargeValue / 100) * $baseAmount;
        }
        return $chargeValue;
    }

    public static function TapCharge(array $data, $gatewayName)
    {
        $amount = $data['amount'];
        $clientId = $data['client_id'] ?? null;
        $agentId = $data['agent_id'] ?? null;
        $currency = $data['currency'] ?? null;

        $agent = Agent::with('branch')->find($agentId);
        $companyId = $agent?->branch?->company_id;

        if (!$companyId) {
            Log::error('TapCharge failed: Missing company_id', [
                'agent_id' => $agentId,
                'client_id' => $clientId
            ]);
            throw new \Exception('Company ID not found for TapCharge.');
        }

        $charge = Charge::where('name', $gatewayName)
            ->where('company_id', $companyId)
            ->first();

        if (!$charge) {
            return self::standardReturn(
                finalAmount: $amount,
                fee: 0,
                paidBy: null,
                netReceived: $amount
            );
        }

        // Determine which charge to use: self_charge takes priority over amount
        $chargeValue = !is_null($charge->self_charge) ? $charge->self_charge : $charge->amount;
        $chargeType = !is_null($charge->self_charge) ? ($charge->self_charge_type ?? 'Flat Rate') : $charge->charge_type;
        $paidBy = $charge->paid_by;

        // Calculate the fee
        $fee = self::calculateChargeAmount($amount, $chargeValue, $chargeType);

        // Calculate final amounts based on who pays
        if ($paidBy === 'Client') {
            $finalAmount = $amount + $fee;
            $netReceived = $amount;
        } else {
            $finalAmount = $amount;
            $netReceived = $amount - $fee;
        }

        Log::info('Tap Gateway charge calculated', [
            'using_self_charge' => !is_null($charge->self_charge),
            'charge_value' => $chargeValue,
            'charge_type' => $chargeType,
            'paid_by' => $paidBy,
            'fee' => $fee,
            'finalAmount' => $finalAmount,
            'netReceived' => $netReceived,
            'company_id' => $companyId,
            'gateway' => $gatewayName
        ]);

        return self::standardReturn(
            finalAmount: $finalAmount,
            fee: $fee,
            paidBy: $paidBy,
            netReceived: $netReceived,
            chargeType: $charge->charge_type,
            selfChargeType: $charge->self_charge_type,
            selfCharge: $charge->self_charge
        );
    }

    public static function FatoorahCharge($amount, $methodCode, $companyId)
    {
        $method = PaymentMethod::findOrFail($methodCode);

        if (!$method) {
            throw new \Exception("Payment method [$methodCode] not found.");
        }

        $paidBy = $method->paid_by;
        $apiServiceCharge = $method->service_charge ?? 0;
        
        // Determine which self charge to use: self_charge takes priority over self_charge (amount)
        $selfChargeValue = !is_null($method->self_charge) ? $method->self_charge : 0;
        $selfChargeType = $method->self_charge_type ?? 'Flat Rate';
        
        // Calculate self charge amount
        $selfChargeAmount = 0;
        if ($selfChargeValue > 0) {
            $selfChargeAmount = self::calculateChargeAmount($amount, $selfChargeValue, $selfChargeType);
        }

        // If self_charge is set, we ignore gateway charges and use only our charge
        if (!is_null($method->self_charge)) {
            $totalFee = $selfChargeAmount;
        } else {
            // Use both API service charge and self charge
            $totalFee = $apiServiceCharge + $selfChargeAmount;
        }

        // Calculate final amounts based on who pays
        if ($paidBy === 'Client') {
            $finalAmount = $amount + $totalFee;
            $netReceived = $amount;
        } else {
            $finalAmount = $amount;
            $netReceived = $amount - $totalFee;
        }

        Log::info('MyFatoorah Gateway charge calculated from PaymentMethod table', [
            'amount' => $amount,
            'using_self_charge' => !is_null($method->self_charge),
            'api_service_charge' => $apiServiceCharge,
            'self_charge_value' => $selfChargeValue,
            'self_charge_amount' => $selfChargeAmount,
            'self_charge_type' => $selfChargeType,
            'total_fee' => $totalFee,
            'finalAmount' => $finalAmount,
            'netReceived' => $netReceived,
            'paid_by' => $paidBy,
        ]);

        return self::standardReturn(
            finalAmount: $finalAmount,
            fee: $totalFee,
            paidBy: $paidBy,
            netReceived: $netReceived,
            chargeType: $method->charge_type,
            selfChargeType: $method->self_charge_type,
            selfCharge: $method->self_charge,
            apiServiceCharge: $apiServiceCharge
        );
    }
}
