<?php

namespace App\Services;

use App\Models\PaymentMethod;
use App\Models\Charge;
use App\Models\Agent;
use Illuminate\Support\Facades\Log;

class ChargeService
{
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
        return [
            'finalAmount' => $amount,
            'fee' => 0,
            'paid_by' => null,
            'netReceived' => $amount,
            'charge_type' => null,
            'amount' => 0,
        ];
    }

    $fee = $charge->charge_type === 'Percent'
        ? round($amount * $charge->amount / 100, 2)
        : (float) $charge->amount;

    $finalAmount = $amount + $fee;
    $netReceived = $charge->paid_by === 'Company'
        ? $finalAmount - $fee
        : $amount;

    Log::info('Tap Gateway charge calculated', [
        'charge_type' => $charge->charge_type,
        'paid_by' => $charge->paid_by,
        'fee' => $fee,
        'finalAmount' => $finalAmount,
        'netReceived' => $netReceived,
        'company_id' => $companyId,
        'gateway' => $gatewayName
    ]);

    return [
        'finalAmount'  => $finalAmount,
        'fee'          => $fee,
        'paid_by'      => $charge->paid_by,
        'netReceived'  => $netReceived,
        'charge_type'  => $charge->charge_type,
        'amount'       => $fee,
    ];
}


    public static function FatoorahCharge($amount, $methodCode, $companyId)
    {
        $method = PaymentMethod::where('id', $methodCode)
            ->where('type', 'myfatoorah')
            ->first();

        if (!$method) {
            throw new \Exception("Payment method [$methodCode] not found.");
        }

        $flatrate = (float) $method->service_charge ?? 0;

        $paidBy = Charge::where('name', 'myfatoorah')
            ->where('company_id', $companyId)
            ->value('paid_by') ?? 'Client';

        if ($paidBy === 'Client') {
            $finalAmount = $amount + $flatrate;
            $netReceived = $amount;
        } else {
            $finalAmount = $amount + $flatrate;
            $netReceived = $amount - $flatrate;
        }

        Log::info('MyFatoorah Gateway charge calculated from PaymentMethod table', [
            'fee' => $flatrate,
            'finalAmount' => $finalAmount,
            'netReceived' => $netReceived,
            'paid_by' => $paidBy,
        ]);

        return [
            'finalAmount'  => $finalAmount,
            'fee'          => $flatrate,
            'paid_by'      => $paidBy,
            'netReceived'  => $netReceived,
            'charge_type'  => 'Flat Rate',
            'amount'       => $flatrate,
        ];
    }
}
