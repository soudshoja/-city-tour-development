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
        ?float $apiServiceCharge = null,
        ?float $gatewayFee = null,
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
            'gatewayFee' => $gatewayFee,
        ];
    }

    /**
     * Calculate charge amount based on type (Percent or Fixed) and round up
     */
    private static function calculateChargeAmount(float $baseAmount, float $chargeValue, string $chargeType): float
    {
        if ($chargeType === 'Percent') {
            $calculated = ($chargeValue / 100) * $baseAmount;
        } else {
            $calculated = $chargeValue;
        }

        // Round up to nearest whole number (ceiling)
        return ceil($calculated);
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

        // Calculate the fee and round up
        $fee = ceil(self::calculateChargeAmount($amount, $chargeValue, $chargeType));

        $totalFee = 0;
        // Calculate final amounts based on who pays
        if ($paidBy === 'Client') {
            $totalFee = $fee;
        }

        $finalAmount = $amount + $totalFee;
        $netReceived = $amount - $totalFee;

        Log::info('Tap Gateway charge calculated', [
            'using_self_charge' => !is_null($charge->self_charge),
            'charge_value' => $chargeValue,
            'charge_type' => $chargeType,
            'paid_by' => $paidBy,
            'total_fee' => $totalFee,
            'finalAmount' => $finalAmount,
            'netReceived' => $netReceived,
            'company_id' => $companyId,
            'gateway' => $gatewayName,
            'gatewayFee' => $fee,
        ]);

        return self::standardReturn(
            finalAmount: $finalAmount,
            fee: $totalFee,
            paidBy: $paidBy,
            netReceived: $netReceived,
            chargeType: $charge->charge_type,
            selfChargeType: $charge->self_charge_type,
            selfCharge: $charge->self_charge,
            gatewayFee: $fee,
        );
    }

    public static function FatoorahCharge($amount, $methodCode, $companyId)
    {
        $method = PaymentMethod::find($methodCode);

        if (!$method) {
            throw new \Exception("Payment method [$methodCode] not found.");
        }

        $paidBy = $method->paid_by;
        $apiServiceCharge = $method->service_charge ?? 0;

        // Determine which self charge to use: self_charge takes priority over service_charge (amount)
        $selfChargeValue = $method->self_charge ? $method->self_charge : $method->service_charge;
        $selfChargeType = $method->charge_type ?? 'Flat Rate';
        // Calculate self charge amount and round up
        $selfChargeAmount = 0;
        if ($selfChargeValue > 0) {
            $selfChargeAmount = ceil(self::calculateChargeAmount($amount, $selfChargeValue, $selfChargeType));
        }

        $totalFee = 0;
        // Calculate final amounts based on who pays
        if ($paidBy === 'Client') {
            $totalFee = $selfChargeAmount;
        }

        $finalAmount = $amount + $totalFee;
        $netReceived = $amount - $totalFee;

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
            'gatewayFee' => $selfChargeAmount,
        ]);

        return self::standardReturn(
            finalAmount: $finalAmount,
            fee: $totalFee,
            paidBy: $paidBy,
            netReceived: $netReceived,
            chargeType: $method->charge_type,
            selfChargeType: $method->self_charge_type,
            selfCharge: $method->self_charge,
            apiServiceCharge: $apiServiceCharge,
            gatewayFee: $selfChargeAmount,
        );
    }

    /**
     * Calculate UPayment charges
     */
    public static function UPaymentCharge($amount, $methodCode, $companyId)
    {
        // Try to find UPayment charge configuration
        $charge = Charge::where('name', 'UPayment')
            ->where('company_id', $companyId)
            ->first();

        if (!$charge) {
            // Fallback to payment method if charge not found
            $method = PaymentMethod::find($methodCode);

            if (!$method) {
                Log::warning('No UPayment charge or payment method found', [
                    'method_code' => $methodCode,
                    'company_id' => $companyId
                ]);
                return self::standardReturn(
                    finalAmount: $amount,
                    fee: 0,
                    paidBy: null,
                    netReceived: $amount
                );
            }

            $paidBy = $method->paid_by;
            $chargeValue = $method->self_charge ?? $method->service_charge ?? 0;
            $chargeType = $method->charge_type ?? 'Flat Rate';
        } else {
            $paidBy = $charge->paid_by;
            $chargeValue = !is_null($charge->self_charge) ? $charge->self_charge : $charge->amount;
            $chargeType = !is_null($charge->self_charge) ? ($charge->self_charge_type ?? 'Flat Rate') : $charge->charge_type;
        }

        // Calculate the fee and round up
        $fee = $chargeValue > 0 ? ceil(self::calculateChargeAmount($amount, $chargeValue, $chargeType)) : 0;

        $totalFee = 0;
        // Calculate final amounts based on who pays
        if ($paidBy === 'Client') {
            $totalFee = $fee;
        }

        $finalAmount = $amount + $totalFee;
        $netReceived = $amount - $totalFee;

        Log::info('UPayment charge calculated', [
            'amount' => $amount,
            'charge_value' => $chargeValue,
            'charge_type' => $chargeType,
            'total_fee' => $totalFee,
            'finalAmount' => $finalAmount,
            'netReceived' => $netReceived,
            'paid_by' => $paidBy,
            'company_id' => $companyId
        ]);

        return self::standardReturn(
            finalAmount: $finalAmount,
            fee: $totalFee,
            paidBy: $paidBy,
            netReceived: $netReceived,
            chargeType: $chargeType
        );
    }

    public static function HesabeCharge($amount, $methodCode)
    {
        $method = PaymentMethod::find($methodCode);

        if (!$method) {
            throw new \Exception("Payment method [$methodCode] not found.");
        }

        $paidBy = $method->paid_by;
        $apiServiceCharge = $method->service_charge ?? 0;

        // Determine which self charge to use: self_charge takes priority over service_charge (amount)
        $selfChargeValue = $method->self_charge ? $method->self_charge : $method->service_charge;
        $selfChargeType = $method->charge_type ?? 'Flat Rate';
        // Calculate self charge amount and round up
        $selfChargeAmount = 0;
        if ($selfChargeValue > 0) {
            $selfChargeAmount = ceil(self::calculateChargeAmount($amount, $selfChargeValue, $selfChargeType));
        }

        $totalFee = 0;
        // Calculate final amounts based on who pays
        if ($paidBy === 'Client') {
            $totalFee = $selfChargeAmount;
        }

        $finalAmount = $amount + $totalFee;
        $netReceived = $amount - $totalFee;

        Log::info('Hesabe Gateway charge calculated from PaymentMethod table', [
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
            'gatewayFee' => $selfChargeAmount,
        ]);

        return self::standardReturn(
            finalAmount: $finalAmount,
            fee: $totalFee,
            paidBy: $paidBy,
            netReceived: $netReceived,
            chargeType: $method->charge_type,
            selfChargeType: $method->self_charge_type,
            selfCharge: $method->self_charge,
            apiServiceCharge: $apiServiceCharge,
            gatewayFee: $selfChargeAmount,
        );
    }

    /**
     * Unified method to get fees for any gateway
     * Supports both payment method-based gateways (MyFatoorah, Hesabe, UPayment)
     * and gateway-level gateways (Tap, custom gateways)
     * 
     * @param string $gatewayName The gateway name (e.g., 'Tap', 'MyFatoorah', 'Hesabe')
     * @param float $amount The transaction amount
     * @param int|null $methodCode The payment method ID (null for gateway-level charges)
     * @param int $companyId The company ID
     * @param string $currency The currency code (default: 'KWD')
     * @return array Standardized charge calculation result
     */
    public static function getFee(string $gatewayName, float $amount, ?int $methodCode = null, int $companyId, string $currency = 'KWD'): array
    {
        // Scenario 1: Payment method provided - use payment method charges
        if ($methodCode !== null && $methodCode > 0) {
            $method = PaymentMethod::where('id', $methodCode)
                ->where('company_id', $companyId)
                ->where('type', $gatewayName)
                ->first();

            if (!$method) {
                Log::warning('Payment method not found, falling back to gateway-level charges', [
                    'method_code' => $methodCode,
                    'gateway' => $gatewayName,
                    'company_id' => $companyId
                ]);
                // Fall through to Scenario 2
            } else {
                $paidBy = $method->paid_by;
                $apiServiceCharge = $method->service_charge ?? 0;

                $selfChargeValue = $method->self_charge ?? $method->service_charge ?? 0;
                $selfChargeType = $method->charge_type ?? 'Flat Rate';

                $selfChargeAmount = 0;
                if ($selfChargeValue > 0) {
                    $selfChargeAmount = ceil(self::calculateChargeAmount($amount, $selfChargeValue, $selfChargeType));
                }

                $totalFee = 0;
                if ($paidBy === 'Client') {
                    $totalFee = $selfChargeAmount;
                }

                $finalAmount = $amount + $totalFee;
                $netReceived = $amount - $totalFee;

                Log::info('Gateway charge calculated via getFee (payment method)', [
                    'gateway' => $gatewayName,
                    'amount' => $amount,
                    'currency' => $currency,
                    'method_code' => $methodCode,
                    'company_id' => $companyId,
                    'using_self_charge' => !is_null($method->self_charge),
                    'api_service_charge' => $apiServiceCharge,
                    'self_charge_value' => $selfChargeValue,
                    'self_charge_amount' => $selfChargeAmount,
                    'self_charge_type' => $selfChargeType,
                    'total_fee' => $totalFee,
                    'finalAmount' => $finalAmount,
                    'netReceived' => $netReceived,
                    'paid_by' => $paidBy,
                    'gatewayFee' => $selfChargeAmount,
                ]);

                return self::standardReturn(
                    finalAmount: $finalAmount,
                    fee: $totalFee,
                    paidBy: $paidBy,
                    netReceived: $netReceived,
                    chargeType: $method->charge_type,
                    selfChargeType: $method->self_charge_type,
                    selfCharge: $method->self_charge,
                    apiServiceCharge: $apiServiceCharge,
                    gatewayFee: $selfChargeAmount,
                );
            }
        }

        // Scenario 2: No payment method or payment method not found - use gateway-level charges
        $charge = Charge::where('name', $gatewayName)
            ->where('company_id', $companyId)
            ->first();


        if (!$charge) {
            Log::warning('No charge configuration found for gateway', [
                'gateway' => $gatewayName,
                'company_id' => $companyId,
                'method_code' => $methodCode
            ]);
            return self::standardReturn(
                finalAmount: $amount,
                fee: 0,
                paidBy: null,
                netReceived: $amount
            );
        }

        $paidBy = $charge->paid_by;
        $chargeValue = !is_null($charge->self_charge) ? $charge->self_charge : $charge->amount;
        $chargeType = !is_null($charge->self_charge) ? ($charge->self_charge_type ?? 'Flat Rate') : $charge->charge_type;

        $fee = $chargeValue > 0 ? ceil(self::calculateChargeAmount($amount, $chargeValue, $chargeType)) : 0;

        $totalFee = 0;
        if ($paidBy === 'Client') {
            $totalFee = $fee;
        }

        $finalAmount = $amount + $totalFee;
        $netReceived = $amount - $totalFee;

        Log::info('Gateway charge calculated via getFee (gateway-level)', [
            'gateway' => $gatewayName,
            'amount' => $amount,
            'currency' => $currency,
            'method_code' => $methodCode,
            'company_id' => $companyId,
            'using_self_charge' => !is_null($charge->self_charge),
            'charge_value' => $chargeValue,
            'charge_type' => $chargeType,
            'total_fee' => $totalFee,
            'finalAmount' => $finalAmount,
            'netReceived' => $netReceived,
            'paid_by' => $paidBy,
            'gatewayFee' => $fee,
        ]);

        return self::standardReturn(
            finalAmount: $finalAmount,
            fee: $totalFee,
            paidBy: $paidBy,
            netReceived: $netReceived,
            chargeType: $charge->charge_type,
            selfChargeType: $charge->self_charge_type,
            selfCharge: $charge->self_charge,
            gatewayFee: $fee,
        );
    }

    /**
     * Calculate gateway fee from Payment object
     * Unified method for use in addCredit(), fix commands, etc.
     * 
     * @param Payment $payment The payment object
     * @param int $companyId The company ID
     * @return array ['gatewayFee' => float, 'paidBy' => string]
     */
    public static function calculateGatewayFeeFromPayment($payment, int $companyId): array
    {
        try {
            $gateway = strtolower($payment->payment_gateway ?? '');
            $methodId = $payment->payment_method_id;

            $result = null;

            if ($gateway === 'myfatoorah') {
                $result = self::FatoorahCharge($payment->amount, $methodId, $companyId);
            } elseif ($gateway === 'tap') {
                $result = self::TapCharge([
                    'amount' => $payment->amount,
                    'client_id' => $payment->client_id,
                    'agent_id' => $payment->agent_id,
                    'currency' => $payment->currency ?? 'KWD'
                ], $payment->payment_gateway);
            } elseif ($gateway === 'hesabe') {
                $result = self::HesabeCharge($payment->amount, $methodId);
            } elseif ($gateway === 'upayment') {
                $result = self::UPaymentCharge($payment->amount, $methodId, $companyId);
            } else {
                $chargeRecord = Charge::where('name', 'LIKE', '%' . $payment->payment_gateway . '%')
                    ->where('company_id', $companyId)
                    ->first();

                return [
                    'gatewayFee' => (float) ($chargeRecord?->amount ?? 0),
                    'paidBy' => $chargeRecord?->paid_by ?? 'Company',
                ];
            }

            return [
                'gatewayFee' => $result['gatewayFee'] ?? 0,
                'paidBy' => $result['paid_by'] ?? 'Company',
            ];
        } catch (\Exception $e) {
            Log::warning('calculateGatewayFeeFromPayment error', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);
            return [
                'gatewayFee' => 0,
                'paidBy' => 'Company',
            ];
        }
    }
}
