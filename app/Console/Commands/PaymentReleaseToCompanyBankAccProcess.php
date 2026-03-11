<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Charge;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class PaymentReleaseToCompanyBankAccProcess extends Command
{
    protected $signature = 'app:payment-release-to-company-bankacc-process';
    protected $description = 'Process daily payments and generate journal entries to complete pay invoice';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        Log::info('[Info] Starting daily payment release task...');

        $payments = Payment::where('completed', 0)
            ->where('status', 'completed')
            ->with(['client.agent.branch.company'])
            ->get();

        // Group by payment_date (formatted) and payment_gateway
        $grouped = $payments->groupBy(function ($payment) {
            $date = Carbon::parse($payment->payment_date)->format('Y-m-d');
            $gateway = $payment->payment_gateway ?? 'unknown';
            return $date . '|' . $gateway;
        });

        foreach ($grouped as $key => $groupPayments) {
            [$date, $gateway] = explode('|', $key);

            try {
                $firstPayment = $groupPayments->first();
                $client = $firstPayment->client;
                $company = $client->agent->branch->company ?? null;

                if (!$company) {
                    Log::warning("[Warning] Skipped group $key: Company not found.");
                    continue;
                }

                $chargeRecord = Charge::where('name', 'LIKE', "%$gateway%")
                    ->where('company_id', $company->id)
                    ->first();

                if (!$chargeRecord) {
                    Log::warning("[Warning] No charge record found for gateway: {$gateway} in group $key");
                    continue;
                }

                // Retrieve Accounts
                $bankAccountAccRecord = Account::where('id', $chargeRecord->acc_bank_id)
                    ->where('company_id', $company->id)->first();

                $tapAccount = Account::where('id', $chargeRecord->acc_fee_id)
                    ->where('company_id', $company->id)->first();

                $bankPaymentFee = Account::where('id', $chargeRecord->acc_fee_bank_id)
                    ->where('company_id', $company->id)->first();

                if (!$bankAccountAccRecord || !$tapAccount || !$bankPaymentFee) {
                    Log::warning("[Warning] One or more account records missing for group $key");
                    continue;
                }

                $totalAmount = 0;

                foreach ($groupPayments as $payment) {
                    $journalEntry = JournalEntry::where('company_id', $company->id)
                        ->where('voucher_number', $payment->voucher_number)
                        ->where('type', 'charges')
                        ->first();

                    if (!$journalEntry) {
                        Log::warning("[Warning] Journal entry not found for Payment ID {$payment->id}");
                        continue;
                    }

                    $netAmount = $payment->amount - $journalEntry->debit;
                    $totalAmount += $netAmount;
                }

                if ($totalAmount <= 0) {
                    Log::warning("[Warning] Skipped group $key: total amount is zero.");
                    continue;
                }

                // Transaction for the whole group
                $transaction = Transaction::create([
                    'branch_id' => $company->branches->first()->id ?? null,
                    'company_id' => $company->id,
                    'entity_id' => $company->id,
                    'entity_type' => 'company',
                    'transaction_type' => 'payment',
                    'amount' => $totalAmount,
                    'description' => "{$bankPaymentFee->name} Settles to Bank (After 24h) (Assets) for {$gateway} on {$date}",
                ]);

                Log::info("[Info] Group Transaction ID {$transaction->id} created for group $key");

                $entryDescription = "{$bankPaymentFee->name} Settles to Bank (After 24h) for {$gateway} on {$date} released to {$bankAccountAccRecord->name}";

                // Journal Entries for the group
                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'branch_id' => $company->branches->first()->id ?? null,
                    'company_id' => $company->id,
                    'account_id' => $bankAccountAccRecord->id,
                    'transaction_date' => $date,
                    'description' => $entryDescription,
                    'debit' => $totalAmount,
                    'credit' => 0,
                    'balance' => 0,
                    'name' => $bankAccountAccRecord->name,
                    'type' => 'receivable',
                ]);

                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'branch_id' => $company->branches->first()->id ?? null,
                    'company_id' => $company->id,
                    'account_id' => $bankPaymentFee->id,
                    'transaction_date' => $date,
                    'description' => $entryDescription,
                    'debit' => 0,
                    'credit' => $totalAmount,
                    'balance' => 0,
                    'name' => $bankPaymentFee->name,
                    'type' => 'receivable',
                ]);

                // Mark all payments as completed
                foreach ($groupPayments as $payment) {
                    $payment->completed = 1;
                    $payment->save();
                }

                Log::info("[Info] Group $key processed and all payments marked as completed.");
            } catch (Exception $e) {
                Log::error("[Error] Exception in group $key: " . $e->getMessage());
            }
        }


        Log::info('[Info] Daily payment release task completed.');
    }
}
