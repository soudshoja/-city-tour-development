<?php

namespace App\Services;

use App\Models\PaymentMethod;
use App\Models\Charge;
use Illuminate\Support\Facades\Log;

class ChargeService
{
    /**
     * Calculate charge for PAYMENT (what client pays)
     * Uses: self_charge (API + Markup) + extra_charge
     * 
     * ROUNDING RULE:
     * 1. Calculate % charge first. Round UP the % charge
     * 2. Then add flat extra charge (no rounding on extra)
     */
    public static function calculateChargeForPayment(
        float $baseAmount,
        float $backOfficeCharge, // self_charge (API + Markup combined)
        string $backOfficeChargeType,
        ?float $extraCharge = 0
    ): array {
        // Step 1: Calculate Back Office charge (API + Markup)
        if ($backOfficeChargeType === 'Percent') {
            $backOfficeAmount = ($backOfficeCharge / 100) * $baseAmount;
            // Step 2: Round UP the percentage charge
            $backOfficeAmountRounded = ceil($backOfficeAmount);
        } else {
            // Flat rate - no rounding needed
            $backOfficeAmount = $backOfficeCharge;
            $backOfficeAmountRounded = $backOfficeCharge;
        }

        $totalCharge = $backOfficeAmountRounded + $extraCharge;

        // Rounding profit = difference between rounded and actual % charge
        $roundingProfit = $backOfficeAmountRounded - $backOfficeAmount;

        return [
            'back_office_charge' => $backOfficeAmountRounded,
            'extra_charge' => round($extraCharge, 3),
            'total_charge' => round($totalCharge, 3), // What client pays
            'rounding_profit' => round($roundingProfit, 3), // Always company profit
        ];
    }

    /**
     * Calculate charge for ACCOUNTING/COA (internal cost tracking)
     * Uses: service_charge (API charge only) + extra_charge
     * NO markup, NO rounding - this is actual cost to company
     */
    public static function calculateChargeForAccounting(
        float $baseAmount,
        float $contractCharge, // service_charge/amount (API charge only)
        string $contractChargeType,
        ?float $extraCharge = null
    ): array {
        // Contract charge (actual gateway cost - API charge only)
        if ($contractChargeType === 'Percent') {
            $contractAmount = round(($contractCharge / 100) * $baseAmount, 3);
        } else {
            $contractAmount = $contractCharge;
        }

        // Extra charge (always flat, if exists)
        $extraAmount = ($extraCharge !== null && $extraCharge > 0) ? $extraCharge : 0;

        // Total accounting fee = API + Extra (no markup, no rounding)
        $totalAccountingFee = round($contractAmount + $extraAmount, 3);

        return [
            'contract_charge' => $contractAmount, // API charge only
            'extra_charge' => round($extraAmount, 3),
            'accounting_fee' => $totalAccountingFee,  // API + Extra (actual cost)
        ];
    }

    /**
     * Calculate markup profit (company profit from markup)
     * Markup = Back Office Charge - Contract Charge (the difference is pure profit)
     */
    public static function calculateMarkupProfit(
        float $baseAmount,
        float $contractCharge,      // API charge (service_charge/amount)
        float $backOfficeCharge,    // API + Markup (self_charge)
        string $chargeType
    ): float {
        if ($chargeType === 'Percent') {
            // Markup is the difference in percentages
            $markupPercent = $backOfficeCharge - $contractCharge;
            return round(($markupPercent / 100) * $baseAmount, 3);
        }

        // Flat rate - direct difference
        return round($backOfficeCharge - $contractCharge, 3);
    }

    /**
     * MAIN METHOD - Calculate charges for any gateway
     * 
     * Priority:
     * 1. payment_methods table (if methodId provided)
     * 2. charges table (fallback using gatewayName)
     * 
     * Returns separate calculations for:
     * - Client payment (with markup + rounding)
     * - Accounting/COA (actual cost, no markup)
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
        $contractCharge = 0;      // API charge (service_charge/amount)
        $backOfficeCharge = 0;    // API + Markup (self_charge)
        $chargeType = 'Flat Rate';
        $extraCharge = null;      // Always flat rate, nullable
        $paidBy = 'Company';      // Payment Execution: who physically pays
        $method = null;          

        // Priority 1: Get from payment_methods table
        if ($methodId) {
            $method = PaymentMethod::find($methodId);
            if ($method) {
                // Contract charge (API charge) from service_charge
                $contractCharge = $method->service_charge ?? 0;
                $chargeType = $method->charge_type ?? 'Flat Rate';

                // Back office charge (API + Markup) from self_charge
                // If self_charge not set, fallback to contract charge (no markup)
                $backOfficeCharge = $method->self_charge ?? $contractCharge;

                // Extra charge (always flat rate, nullable)
                $extraCharge = $method->extra_charge;

                // Payment execution (who physically pays the gateway)
                $paidBy = $method->paid_by ?? 'Company';
            }
        }

        // Priority 2: Fallback to charges table if no method or no charge found
        if ($contractCharge <= 0 && $gatewayName) {
            $charge = Charge::where('name', $gatewayName)
                ->where('company_id', $companyId)
                ->first();

            if (!$charge) {
                $charge = Charge::where('name', 'LIKE', '%' . $gatewayName . '%')
                    ->where('company_id', $companyId)
                    ->first();
            }

            if ($charge) {
                // Contract charge (API charge) from amount
                $contractCharge = $charge->amount ?? 0;
                $chargeType = $charge->charge_type ?? 'Percent';

                // Back office charge (API + Markup) from self_charge
                $backOfficeCharge = $charge->self_charge ?? $contractCharge;

                // Extra charge (always flat rate, nullable)
                $extraCharge = $charge->extra_charge;

                // Payment execution
                $paidBy = $charge->paid_by ?? 'Company';
            }
        }

        // No charge configuration found - return zero charges
        if ($contractCharge <= 0 && $backOfficeCharge <= 0) {
            return [
                'finalAmount' => $amount,
                'gatewayFee' => 0,
                'accountingFee' => 0,
                'markup_profit' => 0,
                'rounding_profit' => 0,
                'paid_by' => $paidBy,
                'charge_type' => $chargeType,
                'self_charge' => 0,
                'service_charge' => 0,
            ];
        }

        // Calculate for CLIENT PAYMENT (what client sees/pays)
        // Uses: back_office_charge (self_charge) + extra_charge
        $paymentCalc = self::calculateChargeForPayment(
            $amount,
            $backOfficeCharge,
            $chargeType,
            $extraCharge
        );

        // Calculate for ACCOUNTING (actual gateway cost)
        // Uses: contract_charge (service_charge/amount) + extra_charge
        $accountingCalc = self::calculateChargeForAccounting(
            $amount,
            $contractCharge,
            $chargeType,
            $extraCharge
        );

        // Calculate markup profit (company profit from markup)
        // This is always company profit regardless of who pays
        $markupProfit = self::calculateMarkupProfit(
            $amount,
            $contractCharge,
            $backOfficeCharge,
            $chargeType
        );

        // Determine what client pays (if client pays physically)
        $clientPays = ($paidBy === 'Client') ? $paymentCalc['total_charge'] : 0;
        $finalAmount = $amount + $clientPays;

        Log::info('ChargeService::calculate', [
            'payment_gateway' => $gatewayName,
            'method' => $method ? $method->english_name : 'N/A',
            'amount' => $amount,
            'contract_charge_rate' => $contractCharge,
            'back_office_charge_rate' => $backOfficeCharge,
            'extra_charge_flat' => $extraCharge,
            'charge_type' => $chargeType,
            'paid_by' => $paidBy,
            'payment_calc' => $paymentCalc,
            'accounting_calc' => $accountingCalc,
            'markup_profit' => $markupProfit,
            'client_pays' => $clientPays,
            'final_amount' => $finalAmount,
        ]);

        return [
            'finalAmount' => $finalAmount,           // Total client pays (amount + fee if client pays)
            'gatewayFee' => $clientPays,         // Fee added to invoice after round up (0 if company pays)
            'accountingFee' => $accountingCalc['accounting_fee'],   // For COA/profit: exact service charge
            'paid_by' => $paidBy,
            'charge_type' => $chargeType,
            'self_charge' => $backOfficeCharge, // API + Markup rate
            'service_charge' => $accountingCalc['contract_charge'],
            'markup_profit' => $markupProfit, // Company profit from markup difference
            'rounding_profit' => $paymentCalc['rounding_profit'], // Company profit from ceil rounding
        ];
    }
}
