<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Payment;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\Account;
use App\Models\Credit;
use App\Models\Charge;
use App\Services\ChargeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixPaymentLinkCOA extends Command
{
    protected $signature = 'fix:payment-link-coa 
                            {--dry-run : Show what would be fixed without making changes}
                            {--payment-id= : Fix specific payment ID only}
                            {--company-id= : Fix payments for specific company only}
                            {--recalculate-balances : Recalculate running balances for affected accounts}';

    protected $description = 'Fix incorrect COA entries for payment link topups';

    private $accountsToRecalculate = [];

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $specificPaymentId = $this->option('payment-id');
        $specificCompanyId = $this->option('company-id');
        $recalculateBalances = $this->option('recalculate-balances');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        $this->info('╔════════════════════════════════════════════════════════════╗');
        $this->info('║          PAYMENT LINK COA FIX COMMAND                      ║');
        $this->info('╚════════════════════════════════════════════════════════════╝');
        $this->newLine();

        $query = Payment::whereNull('invoice_id')
            ->where('status', 'completed')
            ->whereNotNull('voucher_number')
            ->with(['agent.branch.company', 'client', 'paymentMethod']);

        if ($specificPaymentId) {
            $query->where('id', $specificPaymentId);
            $this->info("Filtering: Payment ID = {$specificPaymentId}");
        }

        if ($specificCompanyId) {
            $query->whereHas('agent.branch', function ($q) use ($specificCompanyId) {
                $q->where('company_id', $specificCompanyId);
            });
            $this->info("Filtering: Company ID = {$specificCompanyId}");
        }

        $payments = $query->orderBy('id')->get();
        $this->info("Found {$payments->count()} topup payments to analyze");
        $this->newLine();

        $stats = ['total' => $payments->count(), 'fixed' => 0, 'skipped' => 0, 'errors' => 0, 'details' => []];

        $this->output->progressStart($payments->count());

        foreach ($payments as $payment) {
            try {
                $result = $this->fixPayment($payment, $isDryRun);
                if ($result['action'] === 'fixed') {
                    $stats['fixed']++;
                    $stats['details'][] = $result;
                } else {
                    $stats['skipped']++;
                }
            } catch (\Exception $e) {
                $stats['errors']++;
                Log::error('FixPaymentLinkCOA error', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        $this->newLine();
        $this->info('╔════════════════════════════════════════════════════════════╗');
        $this->info('║                      SUMMARY                               ║');
        $this->info('╚════════════════════════════════════════════════════════════╝');
        $this->table(['Metric', 'Count'], [
            ['Total Payments', $stats['total']],
            ['✅ Fixed', $stats['fixed']],
            ['⏭️  Skipped', $stats['skipped']],
            ['❌ Errors', $stats['errors']],
        ]);

        if (!empty($stats['details'])) {
            $this->newLine();
            $this->info('FIXES APPLIED (first 100):');
            $this->table(
                ['Payment ID', 'Voucher', 'Issue', 'Old', 'New'],
                collect($stats['details'])->take(100)->map(fn($d) => [
                    $d['payment_id'],
                    $d['voucher'],
                    $d['issue'],
                    $d['old_value'],
                    $d['new_value']
                ])->toArray()
            );
        }

        // Recalculate running balances if flag is set
        if ($recalculateBalances && !empty($this->accountsToRecalculate)) {
            $this->newLine();
            $this->info('RECALCULATING RUNNING BALANCES FOR AFFECTED ACCOUNTS...');

            foreach (array_unique($this->accountsToRecalculate) as $accountId) {
                $this->recalculateAccountBalance($accountId, $isDryRun);
            }
        }

        $this->newLine();
        if ($isDryRun) {
            $this->warn('This was a DRY RUN. Run without --dry-run to apply fixes.');
        } else {
            $this->info('✅ Fix completed!');
        }

        return Command::SUCCESS;
    }

    /**
     * Recalculate running balance for all journal entries of an account
     */
    private function recalculateAccountBalance(int $accountId, bool $isDryRun): void
    {
        $account = Account::find($accountId);
        if (!$account) return;

        $isDebitNormal = $this->isDebitNormalAccount($account);

        $entries = JournalEntry::where('account_id', $accountId)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $runningBalance = 0;
        $updatedCount = 0;

        foreach ($entries as $entry) {
            if ($isDebitNormal) {
                $runningBalance += $entry->debit - $entry->credit;
            } else {
                $runningBalance += $entry->credit - $entry->debit;
            }

            if (abs($entry->balance - $runningBalance) > 0.001) {
                if (!$isDryRun) {
                    $entry->update(['balance' => $runningBalance]);
                }
                $updatedCount++;
            }
        }

        $oldBalance = $account->actual_balance;
        if (abs($oldBalance - $runningBalance) > 0.001) {
            if (!$isDryRun) {
                $account->actual_balance = $runningBalance;
                $account->save();
            }
        }

        $this->line("   {$account->name} (ID: {$accountId}): {$oldBalance} → {$runningBalance} ({$updatedCount} entries updated)");
    }

    /**
     * Determine if account uses debit-normal balance (Asset, Expense)
     */
    private function isDebitNormalAccount(Account $account): bool
    {
        $name = strtolower($account->name);

        if (
            str_contains($name, 'myfatoorah') ||
            str_contains($name, 'tap') ||
            str_contains($name, 'hesabe') ||
            str_contains($name, 'upayment') ||
            str_contains($name, 'bank') ||
            str_contains($name, 'cash') ||
            str_contains($name, 'receivable') ||
            $account->type === 'bank' ||
            $account->type === 'asset'
        ) {
            return true;
        }

        if (
            str_contains($name, 'charge') ||
            str_contains($name, 'expense') ||
            str_contains($name, 'cost') ||
            $account->type === 'expense'
        ) {
            return true;
        }

        return false;
    }

    private function fixPayment(Payment $payment, bool $isDryRun): array
    {
        $companyId = $payment->agent->branch->company->id;
        $client = $payment->client;

        // Get accounts
        $liabilitiesAccount = Account::where('name', 'like', 'Liabilities%')
            ->where('company_id', $companyId)
            ->first();
        if (!$liabilitiesAccount) return ['action' => 'skipped'];

        $clientAdvance = Account::where('name', 'Client')
            ->where('company_id', $companyId)
            ->where('root_id', $liabilitiesAccount->id)
            ->first();
        if (!$clientAdvance) return ['action' => 'skipped'];

        $paymentGatewayAccount = Account::where('name', 'Payment Gateway')
            ->where('company_id', $companyId)
            ->where('parent_id', $clientAdvance->id)
            ->first();
        if (!$paymentGatewayAccount) return ['action' => 'skipped'];

        // Get charge record
        $chargeRecord = Charge::where('name', 'LIKE', '%' . $payment->payment_gateway . '%')
            ->where('company_id', $companyId)
            ->first();
        if (!$chargeRecord) return ['action' => 'skipped'];

        $bankPaymentFee = Account::find($chargeRecord->acc_fee_bank_id);
        $bankCOAFee = Account::find($chargeRecord->acc_fee_id);
        if (!$bankPaymentFee) return ['action' => 'skipped'];

        $chargeResult = ChargeService::calculate(
            $payment->amount,
            $companyId,
            $payment->payment_method_id,
            $payment->payment_gateway
        );
        $accountingFee = $chargeResult['accountingFee'];
        $paidBy = $payment->paymentMethod?->paid_by ?? $chargeResult['paid_by'] ?? 'Company';

        if ($paidBy === 'Company') {
            $correctAssetAmount = $payment->amount - $accountingFee;
            $correctClientCredit = $payment->amount;
            $shouldHaveIncome = false;
        } else {
            $correctAssetAmount = $payment->amount;
            $correctClientCredit = $payment->amount;
            $shouldHaveIncome = true;
        }

        $fixes = [];


        // FIX 1: Credit record amount
        // $creditRecord = Credit::where('payment_id', $payment->id)->first();
        // if ($creditRecord && abs($creditRecord->amount - $correctClientCredit) > 0.001) {
        //     $fixes[] = [
        //         'payment_id' => $payment->id,
        //         'voucher' => $payment->voucher_number,
        //         'issue' => 'Credit amount',
        //         'old_value' => number_format($creditRecord->amount, 3),
        //         'new_value' => number_format($correctClientCredit, 3),
        //     ];
        //     if (!$isDryRun) {
        //         $creditRecord->update(['amount' => $correctClientCredit]);
        //     }
        // }

        // FIX 2: Asset journal entry debit
        $assetEntry = JournalEntry::where('account_id', $chargeRecord->acc_fee_bank_id)
            ->where('description', 'LIKE', 'Client Pays by ' . $client->full_name . ' via (Assets): ' . $bankPaymentFee->name . '%')
            ->where('voucher_number', $payment->voucher_number)
            ->where('debit', '>', 0)
            ->first();

        if ($assetEntry && abs($assetEntry->debit - $correctAssetAmount) > 0.001) {
            $fixes[] = [
                'payment_id' => $payment->id,
                'voucher' => $payment->voucher_number,
                'issue' => 'Asset debit',
                'old_value' => number_format($assetEntry->debit, 3),
                'new_value' => number_format($correctAssetAmount, 3),
            ];
            if (!$isDryRun) {
                $assetEntry->update(['debit' => $correctAssetAmount]);
            }
            $this->accountsToRecalculate[] = $chargeRecord->acc_fee_bank_id;
        }

        // FIX 3: Liability journal entry credit
        $liabilityEntry = JournalEntry::where('account_id', $paymentGatewayAccount->id)
            ->where('description', 'LIKE', 'Advance Payment in voucher number: ' . $payment->voucher_number . '%')
            ->where('credit', '>', 0)
            ->first();

        if ($liabilityEntry && abs($liabilityEntry->credit - $correctClientCredit) > 0.001) {
            $fixes[] = [
                'payment_id' => $payment->id,
                'voucher' => $payment->voucher_number,
                'issue' => 'Liability credit',
                'old_value' => number_format($liabilityEntry->credit, 3),
                'new_value' => number_format($correctClientCredit, 3),
            ];
            if (!$isDryRun) {
                $liabilityEntry->update(['credit' => $correctClientCredit]);
            }
            $this->accountsToRecalculate[] = $paymentGatewayAccount->id;
        }

        // FIX 4: Combine/cleanup transactions
        // Rule: 1 "Client Credit of" + 1 "Topup success by" = 1 "Client Advance via"
        // Find the FIRST "Client Credit of" transaction (the one to KEEP)
        $clientCreditTransaction = Transaction::where('reference_number', $payment->voucher_number)
            ->where('description', 'LIKE', 'Client Credit of%')
            ->orderBy('id')
            ->first();

        // Find "Topup success by" transaction (to combine with Client Credit)
        $topupSuccessTransaction = Transaction::where('payment_id', $payment->id)
            ->where('description', 'LIKE', 'Topup success by%')
            ->first();

        // Find already renamed "Client Advance via" transaction (if exists)
        $clientAdvanceTransaction = Transaction::where('reference_number', $payment->voucher_number)
            ->where('description', 'LIKE', 'Client Advance via%')
            ->first();

        // Find ALL duplicate "Client Credit of" transactions (to delete)
        $duplicateClientCreditTransactions = Transaction::where('reference_number', $payment->voucher_number)
            ->where('description', 'LIKE', 'Client Credit of%')
            ->orderBy('id')
            ->get()
            ->skip(1); // Skip the first one (we keep it)

        // CASE 1: Both "Client Credit of" and "Topup success by" exist - COMBINE
        if ($clientCreditTransaction && $topupSuccessTransaction) {
            $fixes[] = [
                'payment_id' => $payment->id,
                'voucher' => $payment->voucher_number,
                'issue' => 'Combine transactions',
                'old_value' => '2 transactions',
                'new_value' => '1 transaction',
            ];

            if (!$isDryRun) {
                // Move journal entries from "Topup success by" to "Client Credit of"
                JournalEntry::where('transaction_id', $topupSuccessTransaction->id)
                    ->update(['transaction_id' => $clientCreditTransaction->id]);

                // Rename "Client Credit of" to "Client Advance via"
                $clientCreditTransaction->update([
                    'description' => 'Client Advance via ' . $payment->voucher_number,
                    'amount' => $correctClientCredit,
                    'payment_id' => $payment->id,
                ]);

                // Delete "Topup success by" transaction
                $topupSuccessTransaction->delete();
            }
        }
        // CASE 2: "Client Advance via" already exists but "Topup success by" still exists
        elseif ($clientAdvanceTransaction && $topupSuccessTransaction) {
            $fixes[] = [
                'payment_id' => $payment->id,
                'voucher' => $payment->voucher_number,
                'issue' => 'Delete orphan Topup success',
                'old_value' => 'Topup success by...',
                'new_value' => 'DELETED',
            ];

            if (!$isDryRun) {
                // Move journal entries to Client Advance
                JournalEntry::where('transaction_id', $topupSuccessTransaction->id)
                    ->update(['transaction_id' => $clientAdvanceTransaction->id]);

                // Update Client Advance with payment_id if missing
                $clientAdvanceTransaction->update([
                    'payment_id' => $payment->id,
                    'amount' => $correctClientCredit,
                ]);

                // Delete Topup success
                $topupSuccessTransaction->delete();
            }
        }
        // CASE 3: Only "Client Credit of" exists (no Topup success) - just rename
        elseif ($clientCreditTransaction && !$topupSuccessTransaction && !$clientAdvanceTransaction) {
            $fixes[] = [
                'payment_id' => $payment->id,
                'voucher' => $payment->voucher_number,
                'issue' => 'Rename transaction',
                'old_value' => 'Client Credit of...',
                'new_value' => 'Client Advance via...',
            ];

            if (!$isDryRun) {
                $clientCreditTransaction->update([
                    'description' => 'Client Advance via ' . $payment->voucher_number,
                    'amount' => $correctClientCredit,
                    'payment_id' => $payment->id,
                ]);
            }
        }
        // CASE 4: Only "Topup success by" exists (no Client Credit) - just rename
        elseif (!$clientCreditTransaction && $topupSuccessTransaction && !$clientAdvanceTransaction) {
            $fixes[] = [
                'payment_id' => $payment->id,
                'voucher' => $payment->voucher_number,
                'issue' => 'Rename transaction',
                'old_value' => 'Topup success by...',
                'new_value' => 'Client Advance via...',
            ];

            if (!$isDryRun) {
                $topupSuccessTransaction->update([
                    'description' => 'Client Advance via ' . $payment->voucher_number,
                    'amount' => $correctClientCredit,
                    'reference_number' => $payment->voucher_number,
                ]);
            }
        }
        // CASE 5: "Client Advance via" exists but needs payment_id
        elseif ($clientAdvanceTransaction && $clientAdvanceTransaction->payment_id === null) {
            $fixes[] = [
                'payment_id' => $payment->id,
                'voucher' => $payment->voucher_number,
                'issue' => 'Add payment_id',
                'old_value' => 'NULL',
                'new_value' => (string) $payment->id,
            ];

            if (!$isDryRun) {
                $clientAdvanceTransaction->update([
                    'payment_id' => $payment->id,
                    'amount' => $correctClientCredit,
                ]);
            }
        }

        // CASE 6: Delete duplicate "Client Credit of" transactions
        if ($duplicateClientCreditTransactions->count() > 0) {
            $fixes[] = [
                'payment_id' => $payment->id,
                'voucher' => $payment->voucher_number,
                'issue' => 'Delete duplicate Client Credit',
                'old_value' => $duplicateClientCreditTransactions->count() . ' duplicates',
                'new_value' => 'DELETED',
            ];

            if (!$isDryRun) {
                // Get the main transaction to move entries to
                $mainTransaction = $clientAdvanceTransaction ?? $clientCreditTransaction;

                if ($mainTransaction) {
                    foreach ($duplicateClientCreditTransactions as $duplicate) {
                        // Move journal entries to main transaction (avoid orphan entries)
                        JournalEntry::where('transaction_id', $duplicate->id)
                            ->update(['transaction_id' => $mainTransaction->id]);

                        // Delete duplicate
                        $duplicate->delete();
                    }
                }
            }
        }

        // CASE 7: "Client Advance via" exists but "Client Credit of" ALSO still exists - DELETE Client Credit
        if ($clientAdvanceTransaction && $clientCreditTransaction) {
            $fixes[] = [
                'payment_id' => $payment->id,
                'voucher' => $payment->voucher_number,
                'issue' => 'Delete orphan Client Credit',
                'old_value' => 'Client Credit of...',
                'new_value' => 'DELETED',
            ];

            if (!$isDryRun) {
                // Move journal entries to Client Advance
                JournalEntry::where('transaction_id', $clientCreditTransaction->id)
                    ->update(['transaction_id' => $clientAdvanceTransaction->id]);

                // Update Client Advance
                $clientAdvanceTransaction->update([
                    'payment_id' => $payment->id,
                    'amount' => $correctClientCredit,
                ]);

                // Delete Client Credit
                $clientCreditTransaction->delete();
            }
        }

        // FIX 5: Income entry - CREATE if client pays and missing
        $gatewayFeeRecoveryAccount = Account::where('name', 'Gateway Fee Recovery')
            ->where('company_id', $companyId)
            ->first();

        if ($shouldHaveIncome && $gatewayFeeRecoveryAccount && $accountingFee > 0) {
            $incomeEntry = JournalEntry::where('account_id', $gatewayFeeRecoveryAccount->id)
                ->where('voucher_number', $payment->voucher_number)
                ->where('credit', '>', 0)
                ->first();

            if (!$incomeEntry) {
                $fixes[] = [
                    'payment_id' => $payment->id,
                    'voucher' => $payment->voucher_number,
                    'issue' => 'Missing income entry',
                    'old_value' => 'NONE',
                    'new_value' => number_format($accountingFee, 3),
                ];
                if (!$isDryRun) {
                    $transaction = Transaction::where('payment_id', $payment->id)
                        ->where('description', 'LIKE', 'Client Advance via%')
                        ->first();
                    if ($transaction) {
                        JournalEntry::create([
                            'transaction_id' => $transaction->id,
                            'company_id' => $companyId,
                            'branch_id' => $payment->agent->branch->id,
                            'account_id' => $gatewayFeeRecoveryAccount->id,
                            'voucher_number' => $payment->voucher_number,
                            'transaction_date' => $payment->created_at,
                            'description' => 'Gateway Fee Recovery from Client: ' . $client->full_name,
                            'debit' => 0,
                            'credit' => $accountingFee,
                            'balance' => 0,
                            'name' => $gatewayFeeRecoveryAccount->name,
                            'type' => 'income',
                            'type_reference_id' => $gatewayFeeRecoveryAccount->id
                        ]);
                    }
                }
                $this->accountsToRecalculate[] = $gatewayFeeRecoveryAccount->id;
            }
        }

        // FIX 6: DELETE wrong income entry if company pays
        if (!$shouldHaveIncome && $gatewayFeeRecoveryAccount) {
            $wrongIncomeEntry = JournalEntry::where('account_id', $gatewayFeeRecoveryAccount->id)
                ->where('voucher_number', $payment->voucher_number)
                ->where('credit', '>', 0)
                ->first();

            if ($wrongIncomeEntry) {
                $fixes[] = [
                    'payment_id' => $payment->id,
                    'voucher' => $payment->voucher_number,
                    'issue' => 'Wrong income (company pays)',
                    'old_value' => number_format($wrongIncomeEntry->credit, 3),
                    'new_value' => 'DELETED',
                ];
                if (!$isDryRun) {
                    $wrongIncomeEntry->delete();
                }
                $this->accountsToRecalculate[] = $gatewayFeeRecoveryAccount->id;
            }
        }

        // FIX 7: CREATE missing Liability entry if not exists
        // This happens when old bug only created Asset + Expense but no Liability
        $existingLiabilityEntry = JournalEntry::where('account_id', $paymentGatewayAccount->id)
            ->where('voucher_number', $payment->voucher_number)
            ->where('credit', '>', 0)
            ->first();

        if (!$existingLiabilityEntry) {
            // Get the main transaction (should exist after FIX 4)
            $mainTransaction = Transaction::where('payment_id', $payment->id)
                ->where('description', 'LIKE', 'Client Advance via%')
                ->first();

            if ($mainTransaction) {
                $fixes[] = [
                    'payment_id' => $payment->id,
                    'voucher' => $payment->voucher_number,
                    'issue' => 'Missing liability entry',
                    'old_value' => 'NONE',
                    'new_value' => number_format($correctClientCredit, 3),
                ];

                if (!$isDryRun) {
                    JournalEntry::create([
                        'transaction_id' => $mainTransaction->id,
                        'company_id' => $companyId,
                        'branch_id' => $payment->agent->branch->id,
                        'account_id' => $paymentGatewayAccount->id,
                        'voucher_number' => $payment->voucher_number,
                        'transaction_date' => $payment->created_at,
                        'description' => 'Advance Payment in voucher number: ' . $payment->voucher_number,
                        'debit' => 0,
                        'credit' => $correctClientCredit,
                        'balance' => 0,
                        'name' => $client->full_name,
                        'type' => 'advance',
                        'type_reference_id' => $client->id
                    ]);
                }
                $this->accountsToRecalculate[] = $paymentGatewayAccount->id;
            }
        }

        if (empty($fixes)) {
            return ['action' => 'skipped'];
        }

        return array_merge(['action' => 'fixed'], $fixes[0]);
    }
}
