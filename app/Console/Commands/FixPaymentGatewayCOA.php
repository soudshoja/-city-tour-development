<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Models\JournalEntry;
use App\Models\Account;

class FixPaymentGatewayCOA extends Command
{
    protected $signature = 'fix:payment-gateway-coa 
                            {--dry-run : Preview changes without applying}
                            {--payment-id= : Fix specific payment ID}
                            {--invoice-id= : Fix specific invoice ID}
                            {--company-id= : Fix only for specific company}
                            {--force : Apply changes without confirmation}';

    protected $description = 'Fix unbalanced Payment Gateway COA entries where Net payment received was incorrectly debited with full amount instead of (full - fee)';

    private int $fixed = 0;
    private int $skipped = 0;
    private int $errors = 0;

    public function handle()
    {
        $this->info('===========================================');
        $this->info('  Payment Gateway COA Fix Command');
        $this->info('===========================================');
        $this->newLine();

        $isDryRun = $this->option('dry-run');
        $paymentId = $this->option('payment-id');
        $invoiceId = $this->option('invoice-id');
        $companyId = $this->option('company-id');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Find transactions that are payment gateway related
        $query = Transaction::with(['journalEntries.account'])
            ->where(function ($q) {
                // Match payment gateway success transactions
                $q->where('description', 'LIKE', '%payment success%')
                    ->orWhere('description', 'LIKE', '%Payment via%');
            })
            ->whereHas('journalEntries', function ($q) {
                // Must have "Net payment received" entry
                $q->where('description', 'LIKE', '%Net payment received%');
            });

        if ($paymentId) {
            $query->where('payment_id', $paymentId);
            $this->info("Filtering by payment_id: {$paymentId}");
        }

        if ($invoiceId) {
            $query->where('invoice_id', $invoiceId);
            $this->info("Filtering by invoice_id: {$invoiceId}");
        }

        if ($companyId) {
            $query->where('company_id', $companyId);
            $this->info("Filtering by company_id: {$companyId}");
        }

        $transactions = $query->get();

        $this->info("Found {$transactions->count()} payment gateway transactions to analyze");
        $this->newLine();

        if ($transactions->isEmpty()) {
            $this->info('No transactions found matching criteria.');
            return 0;
        }

        $unbalancedTransactions = [];

        foreach ($transactions as $transaction) {
            $analysis = $this->analyzeTransaction($transaction);

            if ($analysis['is_unbalanced']) {
                $unbalancedTransactions[] = [
                    'transaction' => $transaction,
                    'analysis' => $analysis,
                ];
            }
        }

        $this->info("Found " . count($unbalancedTransactions) . " unbalanced transactions");
        $this->newLine();

        if (empty($unbalancedTransactions)) {
            $this->info('✅ All transactions are balanced. Nothing to fix.');
            return 0;
        }

        $this->table(
            ['Transaction ID', 'Invoice #', 'Total Debit', 'Total Credit', 'Imbalance', 'Gateway Fee'],
            collect($unbalancedTransactions)->map(function ($item) {
                $t = $item['transaction'];
                $a = $item['analysis'];
                return [
                    $t->id,
                    $t->invoice?->invoice_number ?? 'N/A',
                    number_format($a['total_debit'], 3),
                    number_format($a['total_credit'], 3),
                    number_format($a['imbalance'], 3),
                    number_format($a['gateway_fee'], 3),
                ];
            })->toArray()
        );

        if (!$isDryRun && !$this->option('force')) {
            if (!$this->confirm('Do you want to fix these ' . count($unbalancedTransactions) . ' unbalanced transactions?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        // Process fixes
        foreach ($unbalancedTransactions as $item) {
            $this->processTransaction($item['transaction'], $item['analysis'], $isDryRun);
        }

        $this->newLine();
        $this->info('===========================================');
        $this->info('  Summary');
        $this->info('===========================================');
        $this->info("Fixed:   {$this->fixed}");
        $this->info("Skipped: {$this->skipped}");
        $this->info("Errors:  {$this->errors}");

        if ($isDryRun) {
            $this->newLine();
            $this->warn('This was a dry run. Run without --dry-run to apply changes.');
        }

        return 0;
    }

    private function analyzeTransaction(Transaction $transaction): array
    {
        $entries = $transaction->journalEntries;

        $totalDebit = $entries->sum('debit');
        $totalCredit = $entries->sum('credit');
        $imbalance = round($totalDebit - $totalCredit, 3);

        $netPaymentEntry = $entries->first(function ($e) {
            return str_contains(strtolower($e->description), 'net payment received');
        });

        $gatewayFeeEntry = $entries->first(function ($e) {
            return str_contains(strtolower($e->description), 'gateway fee') ||
                str_contains(strtolower($e->description), 'service fee');
        });

        $clientPaymentEntry = $entries->first(function ($e) {
            return str_contains(strtolower($e->description), 'client payment received');
        });

        $gatewayFee = $gatewayFeeEntry ? $gatewayFeeEntry->debit : 0;

        // Transaction is unbalanced if debit > credit
        // The imbalance should equal the gateway fee (because net was debited with full amount instead of full - fee)
        $isUnbalanced = $imbalance > 0.001 && abs($imbalance - $gatewayFee) < 0.01;

        return [
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'imbalance' => $imbalance,
            'is_unbalanced' => $isUnbalanced,
            'gateway_fee' => $gatewayFee,
            'net_payment_entry' => $netPaymentEntry,
            'gateway_fee_entry' => $gatewayFeeEntry,
            'client_payment_entry' => $clientPaymentEntry,
        ];
    }

    private function processTransaction(Transaction $transaction, array $analysis, bool $isDryRun): void
    {
        $netPaymentEntry = $analysis['net_payment_entry'];
        $gatewayFee = $analysis['gateway_fee'];

        if (!$netPaymentEntry) {
            $this->error("  Transaction {$transaction->id}: No 'Net payment received' entry found");
            $this->errors++;
            return;
        }

        $currentDebit = $netPaymentEntry->debit;
        $correctDebit = $currentDebit - $gatewayFee;

        $this->line("Transaction #{$transaction->id}:");
        $this->line("  Invoice: " . ($transaction->invoice?->invoice_number ?? 'N/A'));
        $this->line("  Current Net Debit: " . number_format($currentDebit, 3));
        $this->line("  Gateway Fee: " . number_format($gatewayFee, 3));
        $this->line("  Correct Net Debit: " . number_format($correctDebit, 3));

        if ($correctDebit < 0) {
            $this->warn("  ⚠️  Skipping: Corrected debit would be negative");
            $this->skipped++;
            return;
        }

        if ($isDryRun) {
            $this->info("  ✅ Would fix: Update debit from {$currentDebit} to {$correctDebit}");
            $this->fixed++;
            return;
        }

        try {
            DB::beginTransaction();

            // Update the journal entry
            $netPaymentEntry->debit = $correctDebit;
            $netPaymentEntry->save();

            // Also update the account balance if needed
            $account = $netPaymentEntry->account;
            if ($account) {
                // Reduce actual_balance by the gateway fee (since we over-debited before)
                $account->actual_balance -= $gatewayFee;
                $account->save();

                $this->line("  Updated account '{$account->name}' balance by -{$gatewayFee}");
            }

            DB::commit();

            Log::info('[FIX PAYMENT GATEWAY COA] Fixed transaction', [
                'transaction_id' => $transaction->id,
                'invoice_id' => $transaction->invoice_id,
                'journal_entry_id' => $netPaymentEntry->id,
                'old_debit' => $currentDebit,
                'new_debit' => $correctDebit,
                'gateway_fee' => $gatewayFee,
            ]);

            $this->info("  ✅ Fixed successfully");
            $this->fixed++;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('[FIX PAYMENT GATEWAY COA] Error fixing transaction', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            $this->error("  ❌ Error: " . $e->getMessage());
            $this->errors++;
        }

        $this->newLine();
    }
}
