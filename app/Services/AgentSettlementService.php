<?php

namespace App\Services;

use App\Http\Controllers\PaymentController;
use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentSettlement;
use App\Models\AgentSettlementPayment;
use App\Models\Charge;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AgentSettlementService
{
    public function createSettlement(array $items, Agent $agent, ?string $notes = null): AgentSettlement
    {
        if (!auth()->check()) {
            throw new \Exception('User must be authenticated to create settlement');
        }

        if (empty($items)) {
            throw new \Exception('Settlement must have at least one item');
        }

        return DB::transaction(function () use ($items, $agent, $notes) {
            $companyId = $agent->branch->company_id;
            $totalAmount = collect($items)->sum('amount');

            $settlement = AgentSettlement::create([
                'settlement_number' => $this->generateSettlementNumber($companyId),
                'agent_id'          => $agent->id,
                'branch_id'         => $agent->branch_id,
                'company_id'        => $companyId,
                'total_amount'      => $totalAmount,
                'paid_amount'       => 0,
                'remaining_amount'  => $totalAmount,
                'status'            => 'unpaid',
                'settlement_date'   => now(),
                'notes'             => $notes,
                'created_by'        => auth()->id(),
            ]);

            foreach ($items as $item) {
                $settlement->details()->create([
                    'invoice_id'        => $item['invoice_id'],
                    'invoice_detail_id' => $item['invoice_detail_id'],
                    'amount'            => $item['amount'],
                    'description'       => $item['description'] ?? null,
                ]);
            }

            return $settlement;
        });
    }

    public function settleByProfit(AgentSettlement $settlement, float $amount): AgentSettlementPayment
    {
        return DB::transaction(function () use ($settlement, $amount) {
            $agent = $settlement->agent;
            $companyId = $settlement->company_id;
            $profitAccount = $agent->profitAccount;
            $lossAccount = $agent->lossAccount;

            if (!$profitAccount || !$lossAccount) {
                throw new \Exception('Agent must have both profit and loss accounts defined');
            }

            if ($amount > $settlement->remaining_amount) {
                throw new \Exception("Amount ({$amount}) exceeds remaining settlement ({$settlement->remaining_amount})");
            }

            // Profit account is LIABILITY: balance = credits - debits
            $profitBalance = JournalEntry::where('account_id', $profitAccount->id)->sum('credit')
                - JournalEntry::where('account_id', $profitAccount->id)->sum('debit');

            if ($amount > $profitBalance) {
                throw new \Exception("Insufficient profit balance. Available: {$profitBalance}");
            }

            $transaction = Transaction::create([
                'company_id'       => $companyId,
                'branch_id'        => $agent->branch_id,
                'entity_id'        => $agent->id,
                'entity_type'      => 'agent',
                'transaction_type' => 'debit',
                'amount'           => $amount,
                'description'      => "Loss settlement by profit offset - {$settlement->settlement_number}",
                'reference_type'   => 'Settlement',
                'reference_number' => $settlement->settlement_number,
                'transaction_date' => now(),
            ]);

            JournalEntry::create([
                'transaction_id'   => $transaction->id,
                'branch_id'        => $agent->branch_id ?? null,
                'company_id'       => $companyId,
                'account_id'       => $profitAccount->id,
                'agent_id'         => $agent->id,
                'transaction_date' => now(),
                'description'      => 'Loss settlement by profit offset: ' . $agent->name,
                'debit'            => $amount,
                'credit'           => 0,
                'balance'          => 0,
                'name'             => $profitAccount->name ?? 'Agent Profit Payable',
                'type'             => 'payable',
                'currency'         => 'KWD',
                'exchange_rate'    => 1.00,
                'amount'           => $amount,
            ]);

            JournalEntry::create([
                'transaction_id'   => $transaction->id,
                'branch_id'        => $agent->branch_id ?? null,
                'company_id'       => $companyId,
                'account_id'       => $lossAccount->id,
                'agent_id'         => $agent->id,
                'transaction_date' => now(),
                'description'      => 'Loss settlement by profit offset: ' . $agent->name,
                'debit'            => 0,
                'credit'           => $amount,
                'balance'          => 0,
                'name'             => $lossAccount->name ?? 'Agent Loss Receivable',
                'type'             => 'receivable',
                'currency'         => 'KWD',
                'exchange_rate'    => 1.00,
                'amount'           => $amount,
            ]);

            $settlementPayment = AgentSettlementPayment::create([
                'agent_settlement_id' => $settlement->id,
                'amount'              => $amount,
                'method'              => 'profit',
                'payment_id'          => null,
                'created_by'          => auth()->id(),
            ]);

            $this->updateSettlementTotals($settlement, $amount);

            return $settlementPayment;
        });
    }

    public function settleByPaymentLink(AgentSettlement $settlement, float $amount, string $currency = 'KWD')
    {
        if ($amount > $settlement->remaining_amount) {
            throw new \Exception("Amount ({$amount}) exceeds remaining settlement ({$settlement->remaining_amount})");
        }

        // TODO: reuse existing gateway logic from PaymentController
        $paymentMethodsId = $settlement->company->paymentMethodChoses()->get('payment_method_id')->pluck('payment_method_id')->toArray();

        $paymentController = new PaymentController();

        // 1. Create Payment record with settlement_id (new column on payments table)
        $requestData = new Request([
            'payment_methods' => $paymentMethodsId,
            'amount' => $amount,
            'currency' => $currency,
            'client_id' => null, // client_id must be null for settlement payments
            'agent_id' => $settlement->agent_id,
            'settlement_id' => $settlement->id,
            'send_payment_receipt' => false, // payment receipt use client info, so set to false for settlement payments because client_id is null
        ]);

        $response = $paymentController->multiPaymentMethodProcess($requestData);

        if(!$response['success']) {
            throw new \Exception("Failed to create payment link: " . $response['message']);
        }

        $payment = Payment::find($response['payment_id']);

        return route('payments.link.show', ['companyId' => $settlement->company_id, 'voucherNumber' => $payment->voucher_number]);
    }

    public function onPaymentCompleted(Payment $payment, AgentSettlement $settlement): void
    {
        DB::transaction(function () use ($payment, $settlement) {
            $agent = $settlement->agent;
            $companyId = $settlement->company_id;
            $lossAccount = $agent->lossAccount;
            $gatewayName = $payment->payment_gateway;

            if (!$lossAccount) {
                throw new \Exception('Agent must have a loss account defined');
            }

            $chargeRecord = Charge::where('name', 'LIKE', "%{$gatewayName}%")
                ->where('company_id', $companyId)
                ->first();

            if (!$chargeRecord) {
                throw new \Exception("Charge record not found for gateway: {$gatewayName}");
            }

            $gatewayAssetAccount = Account::find($chargeRecord->acc_fee_bank_id);
            $gatewayExpenseAccount = Account::find($chargeRecord->acc_fee_id);

            if (!$gatewayAssetAccount || !$gatewayExpenseAccount) {
                throw new \Exception('Gateway asset or expense account not found');
            }

            $chargeResult = ChargeService::calculate($payment->amount, $companyId, $payment->payment_method_id, $gatewayName);
            $accountingFee = $chargeResult['accountingFee'] ?? 0;
            $netAmount = $payment->amount - $accountingFee;

            $payment->gateway_fee = $accountingFee;
            $payment->save();

            $transaction = Transaction::create([
                'company_id'        => $companyId,
                'branch_id'         => $agent->branch_id,
                'entity_id'         => $agent->id,
                'entity_type'       => 'agent',
                'transaction_type'  => 'debit',
                'amount'            => $payment->amount,
                'description'       => "Loss settlement by payment - {$settlement->settlement_number}",
                'reference_type'    => 'Settlement',
                'reference_number'  => $settlement->settlement_number,
                'payment_id'        => $payment->id,
                'transaction_date'  => now(),
            ]);

            // CR Agent Loss Receivable (agent owes less)
            JournalEntry::create([
                'transaction_id'   => $transaction->id,
                'branch_id'        => $agent->branch_id ?? null,
                'company_id'       => $companyId,
                'account_id'       => $lossAccount->id,
                'agent_id'         => $agent->id,
                'transaction_date' => now(),
                'description'      => 'Loss settlement payment received from agent: ' . $agent->name,
                'debit'            => 0,
                'credit'           => $payment->amount,
                'balance'          => 0,
                'name'             => $lossAccount->name ?? 'Agent Loss Receivable',
                'type'             => 'receivable',
                'currency'         => 'KWD',
                'exchange_rate'    => 1.00,
                'amount'           => $payment->amount,
            ]);

            // DR Gateway Asset (net amount after fee)
            JournalEntry::create([
                'transaction_id'   => $transaction->id,
                'branch_id'        => $agent->branch_id ?? null,
                'company_id'       => $companyId,
                'account_id'       => $gatewayAssetAccount->id,
                'agent_id'         => $agent->id,
                'transaction_date' => now(),
                'description'      => 'Net settlement payment received via ' . $gatewayName,
                'debit'            => $netAmount,
                'credit'           => 0,
                'balance'          => $gatewayAssetAccount->actual_balance + $netAmount,
                'name'             => $gatewayAssetAccount->name,
                'type'             => 'bank',
                'voucher_number'   => $payment->voucher_number,
                'currency'         => 'KWD',
                'exchange_rate'    => 1.00,
                'amount'           => $netAmount,
            ]);

            $gatewayAssetAccount->actual_balance += $netAmount;
            $gatewayAssetAccount->save();

            // DR Gateway Fee Expense (cost of using gateway)
            JournalEntry::create([
                'transaction_id'   => $transaction->id,
                'branch_id'        => $agent->branch_id ?? null,
                'company_id'       => $companyId,
                'account_id'       => $gatewayExpenseAccount->id,
                'agent_id'         => $agent->id,
                'transaction_date' => now(),
                'description'      => 'Gateway fee on settlement payment: ' . $gatewayExpenseAccount->name,
                'debit'            => $accountingFee,
                'credit'           => 0,
                'balance'          => $gatewayExpenseAccount->actual_balance + $accountingFee,
                'name'             => $gatewayExpenseAccount->name,
                'type'             => 'charges',
                'voucher_number'   => $payment->voucher_number,
                'currency'         => 'KWD',
                'exchange_rate'    => 1.00,
                'amount'           => $accountingFee,
            ]);

            $gatewayExpenseAccount->actual_balance += $accountingFee;
            $gatewayExpenseAccount->save();

            Log::info('[SETTLEMENT COA] Journal entries created', [
                'transaction_id' => $transaction->id,
                'payment_id' => $payment->id,
                'settlement_number' => $settlement->settlement_number,
                'credit_loss_receivable' => $payment->amount,
                'debit_gateway_asset' => $netAmount,
                'debit_gateway_fee' => $accountingFee,
                'balanced' => ($payment->amount == ($netAmount + $accountingFee)) ? 'YES' : 'NO',
            ]);

            AgentSettlementPayment::create([
                'agent_settlement_id' => $settlement->id,
                'amount'              => $payment->amount,
                'method'              => 'payment_link',
                'payment_id'          => $payment->id,
                'created_by'          => auth()->id(),
            ]);

            $this->updateSettlementTotals($settlement, $payment->amount);
        });
    }

    private function updateSettlementTotals(AgentSettlement $settlement, float $amount): void
    {
        $settlement->paid_amount += $amount;
        $settlement->remaining_amount -= $amount;
        $settlement->status = $settlement->remaining_amount <= 0 ? 'paid' : 'partial';
        $settlement->updated_by = auth()->id();
        $settlement->save();
    }

    private function generateSettlementNumber(int $companyId): string
    {
        $thisYear = now()->year;

        $latestSettlementNumber = AgentSettlement::where('company_id', $companyId)
            ->where('settlement_number', 'like', "STL-{$thisYear}-%")
            ->latest('id')
            ->value('settlement_number');

        if (!$latestSettlementNumber) {
            return "STL-{$thisYear}-000001";
        }

        $number = (int) substr($latestSettlementNumber, -6);
        $number++;

        return "STL-{$thisYear}-" . str_pad($number, 6, '0', STR_PAD_LEFT);
    }
}
