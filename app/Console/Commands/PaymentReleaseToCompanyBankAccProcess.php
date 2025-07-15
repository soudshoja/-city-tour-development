<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Charge;
use App\Models\Client;
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

        Payment::where('completed', 0)
            ->where('status', 'completed')
            ->chunk(100, function ($payments) {
                foreach ($payments as $payment) {
                    try {
                        Log::info("[Info] Processing Payment ID: {$payment->id}");

                        $client = Client::findOrFail($payment->client_id);
                        $company = $client->agent->branch->company ?? null;

                        if (!$company) {
                            Log::warning("[Warning] Skipped payment ID {$payment->id}: Company not found.");
                            continue;
                        }

                        $paymentGateway = $payment->payment_gateway;
                        $chargeRecord = Charge::where('name', 'LIKE', "%$paymentGateway%")
                            ->where('company_id', $company->id)
                            ->first();

                        if (!$chargeRecord) {
                            Log::warning("[Warning] No charge record found for gateway: {$paymentGateway}, payment ID: {$payment->id}");
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
                            Log::warning("[Warning] One or more account records missing for Payment ID {$payment->id}");
                            continue;
                        }

                        $journalEntry = JournalEntry::where('company_id', $company->id)
                            ->where('voucher_number', $payment->voucher_number)
                            ->where('type', 'charges')
                            ->first();

                        if (!$journalEntry) {
                            Log::warning("[Warning] Journal entry for charges not found for Payment ID {$payment->id}");
                            continue;
                        }

                        $totalPaidAmount = $payment->amount - $journalEntry->debit;

                        Log::info("[Info] Calculated paid amount: {$totalPaidAmount} for Payment ID {$payment->id}");

                        // Transaction Record
                        $transaction = Transaction::create([
                            'branch_id' => $company->branches->first()->id ?? null,
                            'company_id' => $company->id,
                            'entity_id' => $company->id,
                            'entity_type' => 'company',
                            'transaction_type' => 'payment',
                            'amount' => $totalPaidAmount,
                            'description' => "{$bankPaymentFee->name} Settles to Bank (After 24h) (Assets) for Payment: {$payment->voucher_number}",
                            'payment_id' => $payment->id,
                            'payment_reference' => $payment->payment_reference,
                            'reference_type' => 'Payment',
                        ]);

                        Log::info("[Info] Transaction ID {$transaction->id} created for Payment ID {$payment->id}");

                        // Journal Entries (DR & CR)
                        $entryDescription = "{$bankPaymentFee->name} Settles to Bank (After 24h) for Payment: {$payment->voucher_number} released to {$bankAccountAccRecord->name}";

                        // Debit Entry
                        JournalEntry::create([
                            'transaction_id' => $transaction->id,
                            'branch_id' => $company->branches->first()->id ?? null,
                            'company_id' => $company->id,
                            'account_id' => $bankAccountAccRecord->id,
                            'transaction_date' => $payment->payment_date,
                            'description' => $entryDescription,
                            'debit' => $totalPaidAmount,
                            'credit' => 0,
                            'balance' => 0,
                            'name' => $bankAccountAccRecord->name,
                            'type' => 'receivable',
                            'voucher_number' => $payment->voucher_number,
                            'type_reference_id' => $payment->account_id,
                        ]);

                        // Credit Entry
                        JournalEntry::create([
                            'transaction_id' => $transaction->id,
                            'branch_id' => $company->branches->first()->id ?? null,
                            'company_id' => $company->id,
                            'invoice_id' => $payment->invoice_id,
                            'account_id' => $bankPaymentFee->id,
                            'transaction_date' => $payment->payment_date,
                            'description' => $entryDescription,
                            'debit' => 0,
                            'credit' => $totalPaidAmount,
                            'balance' => 0,
                            'name' => $bankPaymentFee->name,
                            'type' => 'receivable',
                            'voucher_number' => $payment->voucher_number,
                            'type_reference_id' => $payment->account_id,
                        ]);

                        // Mark payment as completed
                        $payment->completed = 1;
                        $payment->save();

                        Log::info("[Info] Payment ID {$payment->id} marked as completed.");
                    } catch (Exception $e) {
                        Log::error("[Error] Exception for Payment ID {$payment->id}: " . $e->getMessage());
                    }
                }
            });

        Log::info('[Info] Daily payment release task completed.');
    }
}
