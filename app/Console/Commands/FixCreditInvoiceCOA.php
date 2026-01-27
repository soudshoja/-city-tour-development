<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\InvoicePartial;
use App\Models\JournalEntry;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixCreditInvoiceCOA extends Command
{
    protected $signature = 'fix:credit-invoice-coa 
                            {--dry-run : Preview changes without modifying database}
                            {--invoice= : Fix specific invoice ID only}
                            {--type= : Filter by type: credit, split, partial, or all (default: all)}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Fix all invoice COA that used client credit - handles full credit, split, and partial payments';

    // Counters for credit invoices (full credit payment)
    private $creditScenarioA = 0; // No COA at all
    private $creditScenarioB = 0; // Old shortcut (DR 2632)
    private $creditScenarioC = 0; // Correct Step 1, missing Step 2
    private $creditScenarioD = 0; // Already correct

    // Counters for split/partial invoices
    private $splitPartialNeedsFix = 0;
    private $splitPartialCorrect = 0;

    private $fixed = 0;
    private $errors = 0;
    private $accounts = [];

    public function handle()
    {
        $this->displayHeader();

        $dryRun = $this->option('dry-run');
        $invoiceId = $this->option('invoice');
        $typeFilter = $this->option('type') ?? 'all';

        if ($dryRun) {
            $this->warn("🔍 DRY RUN MODE - No changes will be made\n");
        }

        $toFix = [];

        // PART 1: Credit Invoices (full credit payment)
        if (in_array($typeFilter, ['all', 'credit'])) {
            $this->info("━━━ ANALYZING CREDIT INVOICES (Full Credit Payment) ━━━\n");

            $creditInvoices = $this->getCreditInvoices($invoiceId);

            if ($creditInvoices->isNotEmpty()) {
                $progressBar = $this->output->createProgressBar($creditInvoices->count());
                $progressBar->start();

                foreach ($creditInvoices as $invoice) {
                    $analysis = $this->analyzeCreditInvoice($invoice);

                    if ($analysis['scenario'] !== 'D') {
                        $toFix[] = [
                            'invoice' => $invoice,
                            'category' => 'credit',
                            'scenario' => $analysis['scenario'],
                            'description' => $this->getCreditScenarioDescription($analysis['scenario']),
                        ];
                    }

                    $progressBar->advance();
                }

                $progressBar->finish();
                $this->newLine(2);
            } else {
                $this->info("No credit invoices found.\n");
            }
        }

        // PART 2: Split/Partial Invoices (partial credit payment)
        if (in_array($typeFilter, ['all', 'split', 'partial'])) {
            $this->info("━━━ ANALYZING SPLIT/PARTIAL INVOICES (Partial Credit Payment) ━━━\n");

            $splitPartialInvoices = $this->getSplitPartialInvoices($invoiceId, $typeFilter);

            if ($splitPartialInvoices->isNotEmpty()) {
                $progressBar = $this->output->createProgressBar($splitPartialInvoices->count());
                $progressBar->start();

                foreach ($splitPartialInvoices as $invoice) {
                    $analysis = $this->analyzeSplitPartialInvoice($invoice);

                    if ($analysis['needs_fix']) {
                        $toFix[] = [
                            'invoice' => $invoice,
                            'category' => 'split_partial',
                            'scenario' => 'SP', // Split/Partial
                            'description' => 'Missing Credit Payment COA (Step 2)',
                            'credit_partials' => $analysis['credit_partials'],
                            'total_credit' => $analysis['total_credit'],
                        ];
                        $this->splitPartialNeedsFix++;
                    } else {
                        $this->splitPartialCorrect++;
                    }

                    $progressBar->advance();
                }

                $progressBar->finish();
                $this->newLine(2);
            } else {
                $this->info("No split/partial invoices with credit found.\n");
            }
        }

        // Display analysis results
        $this->displayAnalysisResults();

        if (empty($toFix)) {
            $this->info("\n✅ All invoices already have correct COA structure!");
            return 0;
        }

        // Show invoices that need fixing
        $this->displayInvoicesToFix($toFix);

        // Confirm before fixing
        if (!$dryRun && !$this->option('force')) {
            if (!$this->confirm("Do you want to fix these " . count($toFix) . " invoice(s)?")) {
                $this->info("Operation cancelled.");
                return 0;
            }
        }

        if ($dryRun) {
            $this->warn("\n🔍 DRY RUN - Would have fixed " . count($toFix) . " invoice(s)");
            return 0;
        }

        // Process fixes
        $this->output->writeln("\n━━━ PROCESSING FIXES ━━━\n");

        $progressBar = $this->output->createProgressBar(count($toFix));
        $progressBar->start();

        $fixedByScenario = ['A' => 0, 'B' => 0, 'C' => 0, 'SP' => 0];
        $errorList = [];

        foreach ($toFix as $item) {
            try {
                $this->fixInvoice($item);
                $fixedByScenario[$item['scenario']]++;
                $this->fixed++;
            } catch (\Exception $e) {
                $this->errors++;
                $errorList[] = [
                    'invoice_id' => $item['invoice']->id,
                    'invoice_number' => $item['invoice']->invoice_number,
                    'error' => $e->getMessage(),
                ];
                Log::error('[FIX ALL CREDIT INVOICE COA] Error', [
                    'invoice_id' => $item['invoice']->id,
                    'category' => $item['category'],
                    'scenario' => $item['scenario'],
                    'error' => $e->getMessage(),
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display final summary
        $this->displayFinalSummary($fixedByScenario, $errorList);

        return $this->errors > 0 ? 1 : 0;
    }

    /**
     * Get credit invoices (full credit payment)
     */
    private function getCreditInvoices($invoiceId = null)
    {
        $query = Invoice::with([
            'invoiceDetails.task.agent.branch',
            'agent.branch.company',
            'client',
            'invoicePartials',
        ])
            ->where(function ($q) {
                $q->where('is_client_credit', 1)
                    ->orWhere('payment_type', 'credit');
            })
            ->whereNotNull('payment_type');

        if ($invoiceId) {
            $query->where('id', $invoiceId);
        }

        return $query->get();
    }

    /**
     * Get split/partial invoices that used credit
     */
    private function getSplitPartialInvoices($invoiceId = null, $typeFilter = 'all')
    {
        $query = Invoice::with([
            'invoicePartials',
            'agent.branch.company',
            'client',
        ])
            ->whereHas('invoicePartials', function ($q) {
                $q->where('payment_gateway', 'Credit')
                    ->where('status', 'paid');
            });

        // Apply type filter
        if ($typeFilter === 'split') {
            $query->where('payment_type', 'split');
        } elseif ($typeFilter === 'partial') {
            $query->where('payment_type', 'partial');
        } else {
            $query->whereIn('payment_type', ['split', 'partial']);
        }

        if ($invoiceId) {
            $query->where('id', $invoiceId);
        }

        return $query->get();
    }

    /**
     * Analyze credit invoice and determine scenario
     */
    private function analyzeCreditInvoice(Invoice $invoice): array
    {
        $companyId = $invoice->agent?->branch?->company_id;

        // Check for Invoice Generation transaction (Step 1)
        $invoiceTransaction = Transaction::where('invoice_id', $invoice->id)
            ->where('reference_type', 'Invoice')
            ->first();

        // Check for Credit Payment transaction (Step 2)
        $paymentTransaction = Transaction::where('invoice_id', $invoice->id)
            ->where('reference_type', 'Payment')
            ->first();

        if (!$invoiceTransaction) {
            // Scenario A: No COA at all
            $this->creditScenarioA++;
            return ['scenario' => 'A', 'invoice_transaction' => null];
        }

        // Check if Step 1 uses old shortcut (DR Liability instead of DR Receivable)
        $liabilityAccount = $this->getLiabilityAccount($companyId);
        $receivableAccount = $this->getReceivableAccount($companyId);

        if ($liabilityAccount && $receivableAccount) {
            $usesOldShortcut = JournalEntry::where('transaction_id', $invoiceTransaction->id)
                ->where('account_id', $liabilityAccount->id)
                ->where('debit', '>', 0)
                ->exists();

            if ($usesOldShortcut) {
                // Scenario B: Old shortcut COA
                $this->creditScenarioB++;
                return ['scenario' => 'B', 'invoice_transaction' => $invoiceTransaction];
            }
        }

        if (!$paymentTransaction) {
            // Scenario C: Correct Step 1, but missing Step 2
            $this->creditScenarioC++;
            return ['scenario' => 'C', 'invoice_transaction' => $invoiceTransaction];
        }

        // Scenario D: Both steps exist correctly
        $this->creditScenarioD++;
        return ['scenario' => 'D', 'invoice_transaction' => $invoiceTransaction];
    }

    /**
     * Analyze split/partial invoice
     */
    private function analyzeSplitPartialInvoice(Invoice $invoice): array
    {
        // Get credit partials for this invoice
        $creditPartials = $invoice->invoicePartials
            ->where('payment_gateway', 'Credit')
            ->where('status', 'paid');

        $totalCredit = $creditPartials->sum('amount');

        // Check if Credit Payment COA (Step 2) exists
        $hasCreditPaymentCOA = Transaction::where('invoice_id', $invoice->id)
            ->where('reference_type', 'Payment')
            ->where(function ($q) {
                $q->whereHas('journalEntries', function ($q2) {
                    $q2->where('description', 'like', '%Client Credit%');
                })
                    ->orWhere('description', 'like', 'Credit Payment for%');
            })
            ->exists();

        return [
            'needs_fix' => $creditPartials->isNotEmpty() && !$hasCreditPaymentCOA,
            'credit_partials' => $creditPartials,
            'total_credit' => $totalCredit,
            'has_credit_payment_coa' => $hasCreditPaymentCOA,
        ];
    }

    /**
     * Fix invoice based on category and scenario
     */
    private function fixInvoice(array $item): void
    {
        $invoice = $item['invoice'];
        $category = $item['category'];
        $scenario = $item['scenario'];

        DB::transaction(function () use ($invoice, $category, $scenario, $item) {
            if ($category === 'credit') {
                $this->fixCreditInvoice($invoice, $scenario);
            } else {
                $this->fixSplitPartialInvoice(
                    $invoice,
                    $item['credit_partials'],
                    $item['total_credit']
                );
            }
        });

        Log::info('[FIX ALL CREDIT INVOICE COA] Fixed invoice', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'category' => $category,
            'scenario' => $scenario,
        ]);
    }

    /**
     * Fix credit invoice based on scenario
     */
    private function fixCreditInvoice(Invoice $invoice, string $scenario): void
    {
        switch ($scenario) {
            case 'A':
                $this->fixCreditScenarioA($invoice);
                break;
            case 'B':
                $this->fixCreditScenarioB($invoice);
                break;
            case 'C':
                $this->fixCreditScenarioC($invoice);
                break;
        }
    }

    /**
     * Scenario A: No COA at all - Create both Step 1 and Step 2
     */
    private function fixCreditScenarioA(Invoice $invoice): void
    {
        $companyId = $invoice->agent?->branch?->company_id;
        $branchId = $invoice->agent?->branch_id;

        // Create Step 1: Invoice Generation COA
        $transaction1 = Transaction::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'entity_id' => $companyId,
            'entity_type' => 'company',
            'transaction_type' => 'credit',
            'amount' => $invoice->amount,
            'description' => 'Invoice: ' . $invoice->invoice_number . ' Generated',
            'invoice_id' => $invoice->id,
            'reference_type' => 'Invoice',
            'transaction_date' => $invoice->invoice_date,
            'created_at' => $invoice->created_at,
            'updated_at' => $invoice->created_at,
        ]);

        // Create journal entries for Step 1
        $this->createInvoiceGenerationJournalEntries($invoice, $transaction1);

        // Create Step 2: Credit Payment COA
        $this->createCreditPaymentCOA($invoice);
    }

    /**
     * Scenario B: Old shortcut - Fix Step 1 + Create Step 2
     */
    private function fixCreditScenarioB(Invoice $invoice): void
    {
        $companyId = $invoice->agent?->branch?->company_id;

        $liabilityAccount = $this->getLiabilityAccount($companyId);
        $receivableAccount = $this->getReceivableAccount($companyId);

        if (!$liabilityAccount || !$receivableAccount) {
            throw new \Exception("Required accounts not found for company {$companyId}");
        }

        // Find the existing Invoice transaction
        $invoiceTransaction = Transaction::where('invoice_id', $invoice->id)
            ->where('reference_type', 'Invoice')
            ->first();

        if (!$invoiceTransaction) {
            throw new \Exception("Invoice transaction not found");
        }

        // Update the journal entry: change from Liability to Receivable
        $journalEntry = JournalEntry::where('transaction_id', $invoiceTransaction->id)
            ->where('account_id', $liabilityAccount->id)
            ->where('debit', '>', 0)
            ->first();

        if ($journalEntry) {
            $journalEntry->update([
                'account_id' => $receivableAccount->id,
                'name' => $receivableAccount->name,
                'description' => 'Invoice created for (Assets): ' . $invoice->client?->full_name,
            ]);
        }

        // Create Step 2: Credit Payment COA
        $this->createCreditPaymentCOA($invoice);
    }

    /**
     * Scenario C: Correct Step 1, missing Step 2 - Create Step 2 only
     */
    private function fixCreditScenarioC(Invoice $invoice): void
    {
        $this->createCreditPaymentCOA($invoice);
    }

    /**
     * Fix split/partial invoice - Create missing Credit Payment COA
     */
    private function fixSplitPartialInvoice(Invoice $invoice, $creditPartials, float $totalCredit): void
    {
        $companyId = $invoice->agent?->branch?->company_id;
        $branchId = $invoice->agent?->branch_id;

        $liabilityAccount = $this->getLiabilityAccount($companyId);
        $receivableAccount = $this->getReceivableAccount($companyId);

        if (!$liabilityAccount || !$receivableAccount) {
            throw new \Exception("Required accounts not found for company {$companyId}");
        }

        // Build applied payments from credit records
        $appliedPayments = [];

        foreach ($creditPartials as $partial) {
            // Find credit records for this partial
            $credits = Credit::where('invoice_partial_id', $partial->id)
                ->where('type', Credit::INVOICE)
                ->where('amount', '<', 0)
                ->with(['payment', 'refund'])
                ->get();

            if ($credits->isNotEmpty()) {
                foreach ($credits as $credit) {
                    $voucherNumber = 'Client Credit';
                    if ($credit->payment) {
                        $voucherNumber = $credit->payment->voucher_number ?? 'TOPUP';
                    } elseif ($credit->refund) {
                        $voucherNumber = $credit->refund->refund_number ?? 'REFUND';
                    }

                    $appliedPayments[] = [
                        'voucher_number' => $voucherNumber,
                        'amount_applied' => abs($credit->amount),
                        'invoice_partial_id' => $partial->id,
                    ];
                }
            } else {
                // No specific credit records, use partial amount
                $appliedPayments[] = [
                    'voucher_number' => 'Client Credit',
                    'amount_applied' => $partial->amount,
                    'invoice_partial_id' => $partial->id,
                ];
            }
        }

        // Calculate total from applied payments
        $calculatedTotal = array_sum(array_column($appliedPayments, 'amount_applied'));

        // Determine transaction date
        $transactionDate = $creditPartials->min('created_at') ?? $invoice->invoice_date;

        // Create Transaction (reference_type = 'Payment')
        $transaction = Transaction::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'entity_id' => $invoice->client_id,
            'entity_type' => 'Client',
            'transaction_type' => 'debit',
            'amount' => $calculatedTotal,
            'description' => "Credit Payment for {$invoice->invoice_number}",
            'invoice_id' => $invoice->id,
            'reference_type' => 'Payment',
            'reference_number' => $invoice->invoice_number,
            'transaction_date' => $transactionDate,
            'created_at' => $transactionDate,
            'updated_at' => $transactionDate,
        ]);

        // Create DEBIT entries - One per voucher/partial
        foreach ($appliedPayments as $payment) {
            if ($payment['amount_applied'] <= 0) continue;

            JournalEntry::create([
                'transaction_id' => $transaction->id,
                'branch_id' => $branchId,
                'company_id' => $companyId,
                'account_id' => $liabilityAccount->id,
                'invoice_id' => $invoice->id,
                'invoice_partial_id' => $payment['invoice_partial_id'],
                'agent_id' => $invoice->agent_id,
                'transaction_date' => $transactionDate,
                'description' => "Apply Client Credit from {$payment['voucher_number']}",
                'debit' => $payment['amount_applied'],
                'credit' => 0,
                'balance' => $liabilityAccount->actual_balance ?? 0,
                'name' => $liabilityAccount->name,
                'type' => 'payable',
                'currency' => $invoice->currency ?? 'KWD',
                'created_at' => $transactionDate,
                'updated_at' => $transactionDate,
            ]);
        }

        // Create single CREDIT entry for total
        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'branch_id' => $branchId,
            'company_id' => $companyId,
            'account_id' => $receivableAccount->id,
            'invoice_id' => $invoice->id,
            'invoice_partial_id' => null,
            'agent_id' => $invoice->agent_id,
            'transaction_date' => $transactionDate,
            'description' => "Invoice {$invoice->invoice_number} paid via Client Credit",
            'debit' => 0,
            'credit' => $calculatedTotal,
            'balance' => $receivableAccount->actual_balance ?? 0,
            'name' => $receivableAccount->name,
            'type' => 'receivable',
            'currency' => $invoice->currency ?? 'KWD',
            'created_at' => $transactionDate,
            'updated_at' => $transactionDate,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // JOURNAL ENTRY CREATION METHODS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Create Invoice Generation Journal Entries (Step 1)
     */
    private function createInvoiceGenerationJournalEntries(Invoice $invoice, Transaction $transaction): void
    {
        $companyId = $invoice->agent?->branch?->company_id;
        $branchId = $invoice->agent?->branch_id;
        $clientName = $invoice->client?->full_name;

        $receivableAccount = $this->getReceivableAccount($companyId);
        $revenueAccount = $this->getRevenueAccount($companyId, $invoice);
        $commissionExpenseAccount = $this->getCommissionExpenseAccount($companyId);
        $commissionLiabilityAccount = $this->getCommissionLiabilityAccount($companyId);

        $invoiceDetails = $invoice->invoiceDetails;

        foreach ($invoiceDetails as $invoiceDetail) {
            $task = $invoiceDetail->task;
            if (!$task) continue;

            $taskPrice = $invoiceDetail->task_price ?? $task->total ?? 0;

            // Entry 1: DR Receivable
            if ($receivableAccount) {
                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'branch_id' => $branchId,
                    'company_id' => $companyId,
                    'account_id' => $receivableAccount->id,
                    'task_id' => $task->id,
                    'agent_id' => $invoice->agent_id,
                    'invoice_id' => $invoice->id,
                    'invoice_detail_id' => $invoiceDetail->id,
                    'transaction_date' => $invoice->invoice_date,
                    'description' => 'Invoice created for (Assets): ' . $clientName,
                    'debit' => $taskPrice,
                    'credit' => 0,
                    'balance' => $receivableAccount->actual_balance ?? 0,
                    'name' => $receivableAccount->name,
                    'type' => 'receivable',
                    'currency' => $invoice->currency ?? 'KWD',
                    'amount' => $taskPrice,
                    'created_at' => $invoice->created_at,
                    'updated_at' => $invoice->created_at,
                ]);
            }

            // Entry 2: CR Revenue
            if ($revenueAccount) {
                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'branch_id' => $branchId,
                    'company_id' => $companyId,
                    'account_id' => $revenueAccount->id,
                    'task_id' => $task->id,
                    'agent_id' => $invoice->agent_id,
                    'invoice_id' => $invoice->id,
                    'invoice_detail_id' => $invoiceDetail->id,
                    'transaction_date' => $invoice->invoice_date,
                    'description' => 'Invoice created for (Income): ' . $invoice->invoice_number,
                    'debit' => 0,
                    'credit' => $taskPrice,
                    'balance' => $revenueAccount->actual_balance ?? 0,
                    'name' => $revenueAccount->name,
                    'type' => 'payable',
                    'currency' => $invoice->currency ?? 'KWD',
                    'amount' => $taskPrice,
                    'created_at' => $invoice->created_at,
                    'updated_at' => $invoice->created_at,
                ]);
            }

            // Commission entries (if agent type 2 or 3)
            $agent = $task->agent ?? $invoice->agent;
            if ($agent && in_array($agent->type_id, [2, 3])) {
                $selling = (float) $taskPrice;
                $supplier = (float) ($task->total ?? 0);
                $rate = (float) ($agent->commission ?? 0.15);
                $commission = $rate * ($selling - $supplier);

                if ($commission > 0) {
                    // Entry 3: DR Commission Expense
                    if ($commissionExpenseAccount) {
                        JournalEntry::create([
                            'transaction_id' => $transaction->id,
                            'branch_id' => $branchId,
                            'company_id' => $companyId,
                            'account_id' => $commissionExpenseAccount->id,
                            'task_id' => $task->id,
                            'agent_id' => $invoice->agent_id,
                            'invoice_id' => $invoice->id,
                            'invoice_detail_id' => $invoiceDetail->id,
                            'transaction_date' => $invoice->invoice_date,
                            'description' => 'Agents Commissions for (Expenses): ' . $agent->name,
                            'debit' => $commission,
                            'credit' => 0,
                            'balance' => $commissionExpenseAccount->actual_balance ?? 0,
                            'name' => $commissionExpenseAccount->name,
                            'type' => 'receivable',
                            'currency' => $invoice->currency ?? 'KWD',
                            'amount' => $commission,
                            'created_at' => $invoice->created_at,
                            'updated_at' => $invoice->created_at,
                        ]);
                    }

                    // Entry 4: CR Commission Liability
                    if ($commissionLiabilityAccount) {
                        JournalEntry::create([
                            'transaction_id' => $transaction->id,
                            'branch_id' => $branchId,
                            'company_id' => $companyId,
                            'account_id' => $commissionLiabilityAccount->id,
                            'task_id' => $task->id,
                            'agent_id' => $invoice->agent_id,
                            'invoice_id' => $invoice->id,
                            'invoice_detail_id' => $invoiceDetail?->id,
                            'transaction_date' => $invoice->invoice_date,
                            'description' => 'Agents Commissions for (Liabilities): ' . $agent->name,
                            'debit' => 0,
                            'credit' => $commission,
                            'balance' => $commissionLiabilityAccount->actual_balance ?? 0,
                            'name' => $commissionLiabilityAccount->name,
                            'type' => 'payable',
                            'currency' => $invoice->currency ?? 'KWD',
                            'amount' => $commission,
                            'created_at' => $invoice->created_at,
                            'updated_at' => $invoice->created_at,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Create Credit Payment COA (Step 2) - For full credit invoices
     */
    private function createCreditPaymentCOA(Invoice $invoice): void
    {
        $companyId = $invoice->agent?->branch?->company_id;
        $branchId = $invoice->agent?->branch_id;

        $liabilityAccount = $this->getLiabilityAccount($companyId);
        $receivableAccount = $this->getReceivableAccount($companyId);

        if (!$liabilityAccount || !$receivableAccount) {
            throw new \Exception("Required accounts not found for company {$companyId}");
        }

        // Get credit records for this invoice to find voucher numbers
        $creditRecords = Credit::where('invoice_id', $invoice->id)
            ->where('type', Credit::INVOICE)
            ->where('amount', '<', 0)
            ->with(['payment', 'refund'])
            ->get();

        // Calculate total credit applied
        $totalCreditApplied = $creditRecords->sum(fn($c) => abs($c->amount));

        // If no credit records found, use invoice amount
        if ($totalCreditApplied <= 0) {
            $totalCreditApplied = $invoice->amount;
        }

        // Build voucher list for description
        $voucherList = $creditRecords->map(function ($credit) {
            if ($credit->payment) {
                return $credit->payment->voucher_number ?? 'TOPUP';
            } elseif ($credit->refund) {
                return $credit->refund->refund_number ?? 'REFUND';
            }
            return 'CREDIT';
        })->unique()->implode(', ') ?: 'Client Credit';

        // Create Step 2 Transaction
        $transaction2 = Transaction::create([
            'branch_id' => $branchId,
            'company_id' => $companyId,
            'name' => $invoice->client->full_name,
            'reference_number' => $invoice->invoice_number,
            'entity_id' => $invoice->client_id,
            'entity_type' => 'Client',
            'transaction_type' => 'debit',
            'amount' => $totalCreditApplied,
            'description' => 'Credit Payment for ' . $invoice->invoice_number,
            'invoice_id' => $invoice->id,
            'reference_type' => 'Payment',
            'transaction_date' => $invoice->invoice_date,
            'created_at' => $invoice->created_at,
            'updated_at' => $invoice->created_at,
        ]);

        // Create DEBIT entries per voucher (or single if no records)
        if ($creditRecords->isNotEmpty()) {
            foreach ($creditRecords as $credit) {
                $voucherNumber = 'Client Credit';
                if ($credit->payment) {
                    $voucherNumber = $credit->payment->voucher_number ?? 'TOPUP';
                } elseif ($credit->refund) {
                    $voucherNumber = $credit->refund->refund_number ?? 'REFUND';
                }

                $amount = abs($credit->amount);

                JournalEntry::create([
                    'transaction_id' => $transaction2->id,
                    'branch_id' => $branchId,
                    'company_id' => $companyId,
                    'account_id' => $liabilityAccount->id,
                    'invoice_id' => $invoice->id,
                    'invoice_partial_id' => $credit->invoice_partial_id,
                    'agent_id' => $invoice->agent_id,
                    'transaction_date' => $invoice->invoice_date,
                    'description' => 'Apply Client Credit from ' . $voucherNumber,
                    'debit' => $amount,
                    'credit' => 0,
                    'balance' => $liabilityAccount->actual_balance ?? 0,
                    'name' => $liabilityAccount->name,
                    'type' => 'payable',
                    'currency' => $invoice->currency ?? 'KWD',
                    'created_at' => $invoice->created_at,
                    'updated_at' => $invoice->created_at,
                ]);
            }
        } else {
            // No credit records - create single debit entry
            JournalEntry::create([
                'transaction_id' => $transaction2->id,
                'branch_id' => $branchId,
                'company_id' => $companyId,
                'account_id' => $liabilityAccount->id,
                'invoice_id' => $invoice->id,
                'agent_id' => $invoice->agent_id,
                'transaction_date' => $invoice->invoice_date,
                'description' => 'Apply Client Credit from ' . $voucherList,
                'debit' => $totalCreditApplied,
                'credit' => 0,
                'balance' => $liabilityAccount->actual_balance ?? 0,
                'name' => $liabilityAccount->name,
                'type' => 'payable',
                'currency' => $invoice->currency ?? 'KWD',
                'created_at' => $invoice->created_at,
                'updated_at' => $invoice->created_at,
            ]);
        }

        // Create single CREDIT entry for receivable
        JournalEntry::create([
            'transaction_id' => $transaction2->id,
            'branch_id' => $branchId,
            'company_id' => $companyId,
            'account_id' => $receivableAccount->id,
            'invoice_id' => $invoice->id,
            'agent_id' => $invoice->agent_id,
            'transaction_date' => $invoice->invoice_date,
            'description' => 'Invoice ' . $invoice->invoice_number . ' paid via Client Credit',
            'debit' => 0,
            'credit' => $totalCreditApplied,
            'balance' => $receivableAccount->actual_balance ?? 0,
            'name' => $receivableAccount->name,
            'type' => 'receivable',
            'currency' => $invoice->currency ?? 'KWD',
            'created_at' => $invoice->created_at,
            'updated_at' => $invoice->created_at,
        ]);
    }

    private function getLiabilityAccount($companyId): ?Account
    {
        $key = "liability_{$companyId}";
        if (!isset($this->accounts[$key])) {
            // Liabilities > Advances > Client > Payment Gateway
            $liabilities = Account::where('company_id', $companyId)
                ->where('name', 'like', 'Liabilities%')
                ->whereNull('parent_id')
                ->first();

            if ($liabilities) {
                $advances = Account::where('company_id', $companyId)
                    ->where('name', 'Advances')
                    ->where('parent_id', $liabilities->id)
                    ->first();

                if ($advances) {
                    $clientAdvance = Account::where('company_id', $companyId)
                        ->where('name', 'Client')
                        ->where('parent_id', $advances->id)
                        ->first();

                    if ($clientAdvance) {
                        $this->accounts[$key] = Account::where('company_id', $companyId)
                            ->where('name', 'Payment Gateway')
                            ->where('parent_id', $clientAdvance->id)
                            ->first();
                    }
                }
            }

            // Fallback
            if (!isset($this->accounts[$key]) || !$this->accounts[$key]) {
                $this->accounts[$key] = Account::where('company_id', $companyId)
                    ->where('name', 'Payment Gateway')
                    ->whereHas('parent', fn($q) => $q->where('name', 'Client'))
                    ->first();
            }
        }
        return $this->accounts[$key] ?? null;
    }

    private function getReceivableAccount($companyId): ?Account
    {
        $key = "receivable_{$companyId}";
        if (!isset($this->accounts[$key])) {
            // Assets > Accounts Receivable > Clients
            $accountsReceivable = Account::where('company_id', $companyId)
                ->where('name', 'Accounts Receivable')
                ->first();

            if ($accountsReceivable) {
                $this->accounts[$key] = Account::where('company_id', $companyId)
                    ->where('name', 'Clients')
                    ->where('parent_id', $accountsReceivable->id)
                    ->first();
            }

            // Fallback
            if (!isset($this->accounts[$key]) || !$this->accounts[$key]) {
                $this->accounts[$key] = Account::where('company_id', $companyId)
                    ->where('name', 'Clients')
                    ->whereHas('parent', fn($q) => $q->where('name', 'Accounts Receivable'))
                    ->first();
            }
        }
        return $this->accounts[$key] ?? null;
    }

    private function getRevenueAccount($companyId, $invoice): ?Account
    {
        $taskType = $invoice->invoiceDetails->first()?->task?->type ?? 'flight';
        $accountName = ucfirst($taskType) . ' Booking Revenue';

        $account = Account::where('company_id', $companyId)
            ->where('name', 'like', "%{$accountName}%")
            ->first();

        if (!$account) {
            $directIncome = Account::where('company_id', $companyId)
                ->where('name', 'Direct Income')
                ->first();

            if ($directIncome) {
                $account = Account::where('company_id', $companyId)
                    ->where('name', 'like', '%Booking Revenue%')
                    ->where('parent_id', $directIncome->id)
                    ->first();
            }
        }

        return $account;
    }

    private function getCommissionExpenseAccount($companyId): ?Account
    {
        $key = "commission_expense_{$companyId}";
        if (!isset($this->accounts[$key])) {
            $this->accounts[$key] = Account::where('company_id', $companyId)
                ->where('name', 'like', 'Commissions Expense (Agents)%')
                ->first();
        }
        return $this->accounts[$key];
    }

    private function getCommissionLiabilityAccount($companyId): ?Account
    {
        $key = "commission_liability_{$companyId}";
        if (!isset($this->accounts[$key])) {
            $this->accounts[$key] = Account::where('company_id', $companyId)
                ->where('name', 'like', 'Commissions (Agents)%')
                ->first();
        }
        return $this->accounts[$key];
    }

    private function displayHeader(): void
    {
        $this->output->writeln("");
        $this->output->writeln("╔═══════════════════════════════════════════════════════════════════╗");
        $this->output->writeln("║           FIX ALL CREDIT INVOICE COA - UNIFIED COMMAND           ║");
        $this->output->writeln("╠═══════════════════════════════════════════════════════════════════╣");
        $this->output->writeln("║ Fixes COA for invoices that used client credit:                  ║");
        $this->output->writeln("║                                                                   ║");
        $this->output->writeln("║ 📋 CREDIT INVOICES (payment_type='credit')                       ║");
        $this->output->writeln("║    • Scenario A: No COA → Create Step 1 + Step 2                 ║");
        $this->output->writeln("║    • Scenario B: Old shortcut → Fix Step 1 + Create Step 2       ║");
        $this->output->writeln("║    • Scenario C: Missing Step 2 → Create Step 2 only             ║");
        $this->output->writeln("║                                                                   ║");
        $this->output->writeln("║ 📋 SPLIT/PARTIAL INVOICES (with credit partials)                 ║");
        $this->output->writeln("║    • Missing Credit Payment COA → Create Step 2                  ║");
        $this->output->writeln("║                                                                   ║");
        $this->output->writeln("║ ┌─────────────────────────────────────────────────────────────┐  ║");
        $this->output->writeln("║ │ STEP 1: Invoice Generation (reference_type='Invoice')      │  ║");
        $this->output->writeln("║ │   DR: Accounts Receivable > Clients                        │  ║");
        $this->output->writeln("║ │   CR: Income > Direct Income > Booking Revenue             │  ║");
        $this->output->writeln("║ │                                                             │  ║");
        $this->output->writeln("║ │ STEP 2: Credit Payment (reference_type='Payment')          │  ║");
        $this->output->writeln("║ │   DR: Liabilities > Advances > Client > Payment Gateway    │  ║");
        $this->output->writeln("║ │   CR: Accounts Receivable > Clients                        │  ║");
        $this->output->writeln("║ └─────────────────────────────────────────────────────────────┘  ║");
        $this->output->writeln("╚═══════════════════════════════════════════════════════════════════╝");
        $this->output->writeln("");
    }

    private function displayAnalysisResults(): void
    {
        $this->output->writeln("\n━━━ ANALYSIS RESULTS ━━━\n");

        // Credit invoices table
        $this->info("Credit Invoices (Full Credit Payment):");
        $creditData = [];
        if ($this->creditScenarioA > 0) {
            $creditData[] = ['A', 'No COA at all', $this->creditScenarioA, 'Create Step 1 + Step 2'];
        }
        if ($this->creditScenarioB > 0) {
            $creditData[] = ['B', 'Old shortcut (DR Liability)', $this->creditScenarioB, 'Fix Step 1 + Create Step 2'];
        }
        if ($this->creditScenarioC > 0) {
            $creditData[] = ['C', 'Correct Step 1, missing Step 2', $this->creditScenarioC, 'Create Step 2 only'];
        }
        if ($this->creditScenarioD > 0) {
            $creditData[] = ['D', 'Already correct ✅', $this->creditScenarioD, 'Skip'];
        }

        if (!empty($creditData)) {
            $this->table(['Scenario', 'Description', 'Count', 'Action'], $creditData);
        } else {
            $this->line("  No credit invoices analyzed.");
        }

        // Split/Partial invoices table
        $this->newLine();
        $this->info("Split/Partial Invoices (Partial Credit Payment):");
        $splitData = [];
        if ($this->splitPartialNeedsFix > 0) {
            $splitData[] = ['SP', 'Missing Credit Payment COA', $this->splitPartialNeedsFix, 'Create Step 2'];
        }
        if ($this->splitPartialCorrect > 0) {
            $splitData[] = ['✅', 'Already correct', $this->splitPartialCorrect, 'Skip'];
        }

        if (!empty($splitData)) {
            $this->table(['Scenario', 'Description', 'Count', 'Action'], $splitData);
        } else {
            $this->line("  No split/partial invoices analyzed.");
        }

        // Totals
        $totalAnalyzed = $this->creditScenarioA + $this->creditScenarioB + $this->creditScenarioC + $this->creditScenarioD
            + $this->splitPartialNeedsFix + $this->splitPartialCorrect;
        $totalToFix = $this->creditScenarioA + $this->creditScenarioB + $this->creditScenarioC + $this->splitPartialNeedsFix;
        $totalCorrect = $this->creditScenarioD + $this->splitPartialCorrect;

        $this->newLine();
        $this->info("Total analyzed: {$totalAnalyzed}");
        $this->info("Need fixing: {$totalToFix}");
        $this->info("Already correct: {$totalCorrect}");
    }

    private function displayInvoicesToFix(array $toFix): void
    {
        $this->output->writeln("\n━━━ INVOICES TO FIX ━━━\n");

        $rows = [];
        $count = 0;
        foreach ($toFix as $item) {
            if ($count >= 25) break;
            $invoice = $item['invoice'];

            $amount = $item['category'] === 'split_partial'
                ? number_format($item['total_credit'], 3)
                : number_format($invoice->amount, 3);

            $rows[] = [
                $invoice->id,
                $invoice->invoice_number,
                $invoice->payment_type,
                $item['scenario'],
                $invoice->client?->full_name ?? 'N/A',
                $amount . ' ' . ($invoice->currency ?? 'KWD'),
                $item['description'],
            ];
            $count++;
        }

        $this->table(
            ['ID', 'Invoice #', 'Type', 'Scenario', 'Client', 'Amount', 'Action'],
            $rows
        );

        if (count($toFix) > 25) {
            $this->info("... and " . (count($toFix) - 25) . " more");
        }
    }

    private function displayFinalSummary(array $fixedByScenario, array $errors): void
    {
        $this->output->writeln("━━━ FINAL SUMMARY ━━━\n");

        $this->table(
            ['Scenario', 'Description', 'Fixed'],
            [
                ['A', 'Credit: No COA → Created both steps', $fixedByScenario['A']],
                ['B', 'Credit: Old shortcut → Fixed Step 1 + Created Step 2', $fixedByScenario['B']],
                ['C', 'Credit: Missing Step 2 → Created Step 2', $fixedByScenario['C']],
                ['SP', 'Split/Partial: Missing Step 2 → Created Step 2', $fixedByScenario['SP']],
            ]
        );

        $totalFixed = array_sum($fixedByScenario);
        $this->info("\nTotal fixed: {$totalFixed}");

        if (!empty($errors)) {
            $this->error("\nErrors encountered: " . count($errors));
            foreach (array_slice($errors, 0, 5) as $error) {
                $this->error("  - Invoice #{$error['invoice_number']}: {$error['error']}");
            }
            if (count($errors) > 5) {
                $this->error("  ... and " . (count($errors) - 5) . " more errors (check logs)");
            }
        } else {
            $this->info("\n✅ All done! No errors.");
        }
    }

    private function getCreditScenarioDescription(string $scenario): string
    {
        return match ($scenario) {
            'A' => 'No COA → Create Step 1 + Step 2',
            'B' => 'Old shortcut → Fix Step 1 + Create Step 2',
            'C' => 'Missing Step 2 → Create Step 2 only',
            default => 'Unknown',
        };
    }
}
