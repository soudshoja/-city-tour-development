<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentLoss;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\JournalEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixProfitLossEntries extends Command
{
    protected $signature = 'fix:profit-loss-entries 
                            {--company= : Specific company ID}
                            {--invoice= : Specific invoice ID}
                            {--agent= : Specific agent ID}
                            {--dry-run : Preview changes without saving}
                            {--from-date= : Start date (Y-m-d)}
                            {--to-date= : End date (Y-m-d)}
                            {--force : Skip confirmation}';

    protected $description = 'Create missing profit and loss journal entries from invoice details';

    private int $processedInvoices = 0;
    private int $processedDetails = 0;
    private int $createdProfitEntries = 0;
    private int $createdLossEntries = 0;
    private int $skippedDetails = 0;
    private array $summary = [
        'profit_debit' => 0,
        'profit_credit' => 0,
        'agent_loss_debit' => 0,
        'loss_recovery_credit' => 0,
        'company_loss_debit' => 0,
        'supplier_cost_credit' => 0,
        'fee_provision_credit' => 0,
        'commission_debit' => 0,
        'commission_credit' => 0,
    ];

    // Cache for accounts
    private array $accountCache = [];
    private array $changes = [];

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('  Fix Profit & Loss Journal Entries');
        $this->info('═══════════════════════════════════════════════════════════');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be saved');
        }
        $this->newLine();

        $query = Invoice::with([
            'agent.branch',
            'invoiceDetails.task.supplier',
        ]);

        // Apply filters
        if ($invoiceId = $this->option('invoice')) {
            $query->where('id', $invoiceId);
        }

        if ($companyId = $this->option('company')) {
            $query->whereHas('agent.branch', fn($q) => $q->where('company_id', $companyId));
        }

        if ($agentId = $this->option('agent')) {
            $query->where('agent_id', $agentId);
        }

        if ($fromDate = $this->option('from-date')) {
            $query->where('invoice_date', '>=', $fromDate);
        }

        if ($toDate = $this->option('to-date')) {
            $query->where('invoice_date', '<=', $toDate);
        }

        $invoices = $query->get();

        $this->info("Found {$invoices->count()} invoices to process");

        if ($invoices->isEmpty()) {
            $this->info('No invoices found matching criteria.');
            return 0;
        }

        if (!$dryRun && !$this->option('force')) {
            if (!$this->confirm('Do you want to proceed?')) {
                $this->info('Aborted.');
                return 0;
            }
        }

        $bar = $this->output->createProgressBar($invoices->count());
        $bar->start();

        foreach ($invoices as $invoice) {
            $this->processInvoice($invoice, $dryRun);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->printSummary($dryRun);

        return 0;
    }

    private function processInvoice(Invoice $invoice, bool $dryRun): void
    {
        $this->processedInvoices++;

        $agent = $invoice->agent;
        if (!$agent) return;

        $companyId = $agent->branch?->company_id;
        if (!$companyId) return;

        $transactionId = $this->getOrCreateTransactionId($invoice);
        if (!$transactionId) return;

        foreach ($invoice->invoiceDetails as $detail) {
            $this->processDetail($detail, $invoice, $agent, $companyId, $transactionId, $dryRun);
        }
    }

    private function processDetail(
        InvoiceDetail $detail,
        Invoice $invoice,
        Agent $agent,
        int $companyId,
        int $transactionId,
        bool $dryRun
    ): void {
        $task = $detail->task;
        if (!$task) {
            $this->skippedDetails++;
            return;
        }

        // Use stored values from invoice_details
        $profit = (float) ($detail->profit ?? 0);
        $commission = (float) ($detail->commission ?? 0);
        $taskPrice = (float) $detail->task_price;
        $supplierPrice = (float) $detail->supplier_price;
        $markup = $taskPrice - $supplierPrice;

        // Determine loss type
        $isSupplierLoss = $markup < 0;
        $isFeeLoss = ($profit < 0) && ($markup >= 0);

        try {
            if (!$dryRun) {
                DB::beginTransaction();
            }

            $hasChanges = false;

            // POSITIVE PROFIT: Create agent profit entries
            if ($profit > 0) {
                $created = $this->createProfitEntries(
                    $detail,
                    $invoice,
                    $agent,
                    $companyId,
                    $transactionId,
                    $profit,
                    $dryRun
                );
                if ($created) $hasChanges = true;
            }

            // COMMISSION: Create commission entries (if profit > 0 and commission > 0)
            if ($commission > 0) {
                $created = $this->createCommissionEntries(
                    $detail,
                    $invoice,
                    $agent,
                    $companyId,
                    $transactionId,
                    $commission,
                    $dryRun
                );
                if ($created) $hasChanges = true;
            }

            // LOSS: Create loss entries (supplier or fee loss)
            if ($isSupplierLoss || $isFeeLoss) {
                $created = $this->createLossEntries(
                    $detail,
                    $invoice,
                    $agent,
                    $task,
                    $companyId,
                    $transactionId,
                    $markup,
                    $profit,
                    $isSupplierLoss,
                    $dryRun
                );
                if ($created) $hasChanges = true;
            }

            if ($hasChanges) {
                $this->processedDetails++;

                $this->changes[] = [
                    'invoice' => $invoice->invoice_number,
                    'detail_id' => $detail->id,
                    'task_id' => $detail->task_id,
                    'profit' => $profit,
                    'commission' => $commission,
                    'markup' => $markup,
                    'loss_type' => $isSupplierLoss ? 'supplier' : ($isFeeLoss ? 'fee' : 'none'),
                ];
            }

            if (!$dryRun) {
                DB::commit();
            }
        } catch (\Exception $e) {
            if (!$dryRun) {
                DB::rollBack();
            }
            $this->error("Error processing detail {$detail->id}: {$e->getMessage()}");
            Log::error('FixProfitLossEntries error', [
                'detail_id' => $detail->id,
                'error' => $e->getMessage(),
            ]);
            $this->skippedDetails++;
        }
    }

    private function createProfitEntries(
        InvoiceDetail $detail,
        Invoice $invoice,
        Agent $agent,
        int $companyId,
        int $transactionId,
        float $profit,
        bool $dryRun
    ): bool {
        // Check if profit entries already exist
        $existingProfitEntry = JournalEntry::where('invoice_detail_id', $detail->id)
            ->where('description', 'LIKE', 'Agent profit share:%')
            ->exists();

        if ($existingProfitEntry) {
            return false; // Already have profit entries, skip
        }

        // Get accounts
        $agentSalariesAccount = $this->getCachedAccount('Agent Salaries', $companyId);
        $agentProfitPayableAccount = $agent->profit_account_id
            ? Account::find($agent->profit_account_id)
            : null;

        if (!$agentSalariesAccount || !$agentProfitPayableAccount) {
            Log::warning('Profit accounts not found', [
                'detail_id' => $detail->id,
                'company_id' => $companyId
            ]);
            return false;
        }

        if (!$dryRun) {
            // DEBIT: Agent Salaries (Expense)
            JournalEntry::create([
                'transaction_id' => $transactionId,
                'branch_id' => $agent->branch_id,
                'company_id' => $companyId,
                'account_id' => $agentSalariesAccount->id,
                'task_id' => $detail->task_id,
                'agent_id' => $agent->id,
                'invoice_id' => $invoice->id,
                'invoice_detail_id' => $detail->id,
                'transaction_date' => $invoice->invoice_date,
                'description' => 'Agent profit share: ' . $agent->name,
                'debit' => $profit,
                'credit' => 0,
                'balance' => 0,
                'name' => $agentSalariesAccount->name,
                'type' => 'expense',
                'currency' => $detail->task->currency ?? 'KWD',
                'exchange_rate' => $detail->task->exchange_rate ?? 1.00,
                'amount' => $profit,
            ]);

            // CREDIT: Agent Profit Payable (Liability)
            JournalEntry::create([
                'transaction_id' => $transactionId,
                'branch_id' => $agent->branch_id,
                'company_id' => $companyId,
                'account_id' => $agentProfitPayableAccount->id,
                'task_id' => $detail->task_id,
                'agent_id' => $agent->id,
                'invoice_id' => $invoice->id,
                'invoice_detail_id' => $detail->id,
                'transaction_date' => $invoice->invoice_date,
                'description' => 'Profit payable to agent: ' . $agent->name,
                'debit' => 0,
                'credit' => $profit,
                'balance' => 0,
                'name' => $agentProfitPayableAccount->name,
                'type' => 'payable',
                'currency' => $detail->task->currency ?? 'KWD',
                'exchange_rate' => $detail->task->exchange_rate ?? 1.00,
                'amount' => $profit,
            ]);
        }

        $this->createdProfitEntries += 2;
        $this->summary['profit_debit'] += $profit;
        $this->summary['profit_credit'] += $profit;

        return true;
    }

    private function createCommissionEntries(
        InvoiceDetail $detail,
        Invoice $invoice,
        Agent $agent,
        int $companyId,
        int $transactionId,
        float $commission,
        bool $dryRun
    ): bool {
        // Check if commission entries already exist
        $existingCommissionEntry = JournalEntry::where('invoice_detail_id', $detail->id)
            ->where('description', 'LIKE', 'Agents Commissions (Expense):%')
            ->exists();

        if ($existingCommissionEntry) {
            return false; // Already have commission entries, skip
        }

        // Get accounts
        $commissionExpenseAccount = $this->getCachedAccount('Commissions Expense (Agents)', $companyId);
        $commissionLiabilityAccount = $this->getCachedAccount('Commissions (Agents)', $companyId);

        if (!$commissionExpenseAccount || !$commissionLiabilityAccount) {
            Log::warning('Commission accounts not found', [
                'detail_id' => $detail->id,
                'company_id' => $companyId
            ]);
            return false;
        }

        if (!$dryRun) {
            // DEBIT: Commission Expense
            JournalEntry::create([
                'transaction_id' => $transactionId,
                'branch_id' => $agent->branch_id,
                'company_id' => $companyId,
                'account_id' => $commissionExpenseAccount->id,
                'task_id' => $detail->task_id,
                'agent_id' => $agent->id,
                'invoice_id' => $invoice->id,
                'invoice_detail_id' => $detail->id,
                'transaction_date' => $invoice->invoice_date,
                'description' => 'Agents Commissions (Expense): ' . $agent->name,
                'debit' => $commission,
                'credit' => 0,
                'balance' => 0,
                'name' => $commissionExpenseAccount->name,
                'type' => 'expense',
                'currency' => $detail->task->currency ?? 'KWD',
                'exchange_rate' => $detail->task->exchange_rate ?? 1.00,
                'amount' => $commission,
            ]);

            // CREDIT: Commission Liability
            JournalEntry::create([
                'transaction_id' => $transactionId,
                'branch_id' => $agent->branch_id,
                'company_id' => $companyId,
                'account_id' => $commissionLiabilityAccount->id,
                'task_id' => $detail->task_id,
                'agent_id' => $agent->id,
                'invoice_id' => $invoice->id,
                'invoice_detail_id' => $detail->id,
                'transaction_date' => $invoice->invoice_date,
                'description' => 'Agents Commissions (Liability): ' . $agent->name,
                'debit' => 0,
                'credit' => $commission,
                'balance' => 0,
                'name' => $commissionLiabilityAccount->name,
                'type' => 'payable',
                'currency' => $detail->task->currency ?? 'KWD',
                'exchange_rate' => $detail->task->exchange_rate ?? 1.00,
                'amount' => $commission,
            ]);
        }

        $this->createdProfitEntries += 2; // Commission entries counted as profit entries
        $this->summary['commission_debit'] += $commission;
        $this->summary['commission_credit'] += $commission;

        return true;
    }

    private function createLossEntries(
        InvoiceDetail $detail,
        Invoice $invoice,
        Agent $agent,
        $task,
        int $companyId,
        int $transactionId,
        float $markup,
        float $profit,
        bool $isSupplierLoss,
        bool $dryRun
    ): bool {
        // Check if loss entries already exist
        $existingLossEntry = JournalEntry::where('invoice_detail_id', $detail->id)
            ->where('description', 'LIKE', '%loss charged to agent:%')
            ->exists();

        if ($existingLossEntry) {
            return false; // Already have loss entries, skip
        }

        $hasEntries = false;
        $lossSettings = AgentLoss::getForAgent($agent->id, $companyId);

        // SUPPLIER LOSS (if markup < 0)
        if ($isSupplierLoss) {
            $supplierLossAmount = abs($markup);
            $distribution = $lossSettings->calculateLossDistribution($supplierLossAmount);

            // Agent's portion
            if ($distribution['agent_loss'] > 0 && $agent->loss_account_id) {
                $hasEntries = $this->createAgentLossEntries(
                    $detail,
                    $invoice,
                    $agent,
                    $task,
                    $companyId,
                    $transactionId,
                    $distribution['agent_loss'],
                    'supplier',
                    $dryRun
                ) || $hasEntries;
            }

            // Company's portion
            if ($distribution['company_loss'] > 0) {
                $hasEntries = $this->createCompanySupplierLossEntries(
                    $detail,
                    $invoice,
                    $agent,
                    $task,
                    $companyId,
                    $transactionId,
                    $distribution['company_loss'],
                    $dryRun
                ) || $hasEntries;
            }
        }

        // FEE LOSS (if profit < markup - means fees made it worse)
        // This can happen TOGETHER with supplier loss or standalone
        $isFeeLoss = ($profit < 0) && ($markup >= 0); // Standalone fee loss
        $isBothLosses = ($markup < 0) && ($profit < $markup); // Both losses

        if ($isFeeLoss || $isBothLosses) {
            // Calculate ONLY the fee portion
            $feeLossAmount = $isBothLosses ? abs($profit - $markup) : abs($profit);
            $distribution = $lossSettings->calculateLossDistribution($feeLossAmount);

            // Agent's portion
            if ($distribution['agent_loss'] > 0 && $agent->loss_account_id) {
                $hasEntries = $this->createAgentLossEntries(
                    $detail,
                    $invoice,
                    $agent,
                    $task,
                    $companyId,
                    $transactionId,
                    $distribution['agent_loss'],
                    'fee',
                    $dryRun
                ) || $hasEntries;
            }

            // Company's portion
            if ($distribution['company_loss'] > 0) {
                $hasEntries = $this->createCompanyFeeLossEntries(
                    $detail,
                    $invoice,
                    $agent,
                    $task,
                    $companyId,
                    $transactionId,
                    $distribution['company_loss'],
                    $dryRun
                ) || $hasEntries;
            }
        }

        return $hasEntries;
    }

    private function createAgentLossEntries(
        InvoiceDetail $detail,
        Invoice $invoice,
        Agent $agent,
        $task,
        int $companyId,
        int $transactionId,
        float $agentLoss,
        string $lossType,
        bool $dryRun
    ): bool {
        $lossRecoveryAccount = $this->getCachedAccount('Loss Recovery Income', $companyId);

        if (!$lossRecoveryAccount || $dryRun) {
            return false;
        }

        // DEBIT: Agent Loss Receivable
        JournalEntry::create([
            'transaction_id' => $transactionId,
            'branch_id' => $agent->branch_id,
            'company_id' => $companyId,
            'account_id' => $agent->loss_account_id,
            'task_id' => $detail->task_id,
            'agent_id' => $agent->id,
            'invoice_id' => $invoice->id,
            'invoice_detail_id' => $detail->id,
            'transaction_date' => $invoice->invoice_date,
            'description' => ucfirst($lossType) . ' loss charged to agent: ' . $agent->name,
            'debit' => $agentLoss,
            'credit' => 0,
            'balance' => 0,
            'name' => Account::find($agent->loss_account_id)->name ?? 'Agent Loss Receivable',
            'type' => 'receivable',
            'currency' => $task->currency ?? 'KWD',
            'exchange_rate' => $task->exchange_rate ?? 1.00,
            'amount' => $agentLoss,
        ]);

        // CREDIT: Loss Recovery Income
        JournalEntry::create([
            'transaction_id' => $transactionId,
            'branch_id' => $agent->branch_id,
            'company_id' => $companyId,
            'account_id' => $lossRecoveryAccount->id,
            'task_id' => $detail->task_id,
            'agent_id' => $agent->id,
            'invoice_id' => $invoice->id,
            'invoice_detail_id' => $detail->id,
            'transaction_date' => $invoice->invoice_date,
            'description' => ucfirst($lossType) . ' loss recovery from agent: ' . $agent->name,
            'debit' => 0,
            'credit' => $agentLoss,
            'balance' => 0,
            'name' => 'Loss Recovery Income',
            'type' => 'income',
            'currency' => $task->currency ?? 'KWD',
            'exchange_rate' => $task->exchange_rate ?? 1.00,
            'amount' => $agentLoss,
        ]);

        $this->createdLossEntries += 2;
        $this->summary['agent_loss_debit'] += $agentLoss;
        $this->summary['loss_recovery_credit'] += $agentLoss;

        return true;
    }

    private function createCompanySupplierLossEntries(
        InvoiceDetail $detail,
        Invoice $invoice,
        Agent $agent,
        $task,
        int $companyId,
        int $transactionId,
        float $companyLoss,
        bool $dryRun
    ): bool {
        $companyLossAccount = $this->getCachedAccount('Company Loss on Sales', $companyId);

        if (!$companyLossAccount || $dryRun) {
            return false;
        }

        // DEBIT: Company Loss on Sales
        JournalEntry::create([
            'transaction_id' => $transactionId,
            'branch_id' => $agent->branch_id,
            'company_id' => $companyId,
            'account_id' => $companyLossAccount->id,
            'task_id' => $detail->task_id,
            'agent_id' => $agent->id,
            'invoice_id' => $invoice->id,
            'invoice_detail_id' => $detail->id,
            'transaction_date' => $invoice->invoice_date,
            'description' => 'Company portion of supplier loss on ' . $task->reference,
            'debit' => $companyLoss,
            'credit' => 0,
            'balance' => 0,
            'name' => $companyLossAccount->name,
            'type' => 'expense',
            'currency' => $task->currency ?? 'KWD',
            'exchange_rate' => $task->exchange_rate ?? 1.00,
            'amount' => $companyLoss,
        ]);

        $this->createdLossEntries++;
        $this->summary['company_loss_debit'] += $companyLoss;

        // CREDIT: Supplier Cost
        $this->createSupplierLossCredit(
            $detail,
            $invoice,
            $agent,
            $task,
            $companyId,
            $transactionId,
            $companyLoss,
            $dryRun
        );

        return true;
    }

    private function createCompanyFeeLossEntries(
        InvoiceDetail $detail,
        Invoice $invoice,
        Agent $agent,
        $task,
        int $companyId,
        int $transactionId,
        float $companyLoss,
        bool $dryRun
    ): bool {
        $companyLossAccount = $this->getCachedAccount('Company Loss on Sales', $companyId);

        if (!$companyLossAccount || $dryRun) {
            return false;
        }

        // DEBIT: Company Loss on Sales
        JournalEntry::create([
            'transaction_id' => $transactionId,
            'branch_id' => $agent->branch_id,
            'company_id' => $companyId,
            'account_id' => $companyLossAccount->id,
            'task_id' => $detail->task_id,
            'agent_id' => $agent->id,
            'invoice_id' => $invoice->id,
            'invoice_detail_id' => $detail->id,
            'transaction_date' => $invoice->invoice_date,
            'description' => 'Company portion of fee loss on ' . $task->reference,
            'debit' => $companyLoss,
            'credit' => 0,
            'balance' => 0,
            'name' => $companyLossAccount->name,
            'type' => 'expense',
            'currency' => $task->currency ?? 'KWD',
            'exchange_rate' => $task->exchange_rate ?? 1.00,
            'amount' => $companyLoss,
        ]);

        $this->createdLossEntries++;
        $this->summary['company_loss_debit'] += $companyLoss;

        // CREDIT: Fee Loss Provision
        $this->createFeeLossCredit(
            $detail,
            $invoice,
            $agent,
            $task,
            $companyId,
            $transactionId,
            $companyLoss,
            $dryRun
        );

        return true;
    }

    private function createSupplierLossCredit(
        InvoiceDetail $detail,
        Invoice $invoice,
        Agent $agent,
        $task,
        int $companyId,
        int $transactionId,
        float $companyLoss,
        bool $dryRun
    ): void {
        // Get the supplier cost account
        $expenses = Account::where('name', 'like', '%Expenses%')
            ->where('company_id', $companyId)
            ->first();

        if (!$expenses || !$task->supplier) {
            return;
        }

        $costAccount = Account::where('name', $task->supplier->name)
            ->where('company_id', $companyId)
            ->where('root_id', $expenses->id)
            ->first();

        if ($costAccount && !$dryRun) {
            // CREDIT: Supplier Cost (reduce cost)
            JournalEntry::create([
                'transaction_id' => $transactionId,
                'branch_id' => $agent->branch_id,
                'company_id' => $companyId,
                'account_id' => $costAccount->id,
                'task_id' => $detail->task_id,
                'agent_id' => $agent->id,
                'invoice_id' => $invoice->id,
                'invoice_detail_id' => $detail->id,
                'transaction_date' => $invoice->invoice_date,
                'description' => 'Transfer supplier loss to loss account',
                'debit' => 0,
                'credit' => $companyLoss,
                'balance' => 0,
                'name' => $costAccount->name,
                'type' => 'expense',
                'currency' => $task->currency ?? 'KWD',
                'exchange_rate' => $task->exchange_rate ?? 1.00,
                'amount' => $companyLoss,
            ]);

            $this->createdLossEntries++;
            $this->summary['supplier_cost_credit'] += $companyLoss;
        }
    }

    private function createFeeLossCredit(
        InvoiceDetail $detail,
        Invoice $invoice,
        Agent $agent,
        $task,
        int $companyId,
        int $transactionId,
        float $companyLoss,
        bool $dryRun
    ): void {
        // Get or create Fee Loss Provision account
        $feeLossProvisionAccount = $this->getCachedAccount('Fee Loss Provision', $companyId);

        if (!$feeLossProvisionAccount) {
            // Create the account if it doesn't exist
            $directIncomeParent = Account::where('name', 'like', '%Direct Income%')
                ->where('company_id', $companyId)
                ->first();

            if ($directIncomeParent && !$dryRun) {
                $feeLossProvisionAccount = Account::create([
                    'code' => '4175',
                    'name' => 'Fee Loss Provision',
                    'company_id' => $companyId,
                    'root_id' => $directIncomeParent->root_id,
                    'parent_id' => $directIncomeParent->id,
                    'branch_id' => $agent->branch_id,
                    'account_type' => 'income',
                    'report_type' => Account::REPORT_TYPES['PROFIT_LOSS'],
                    'level' => $directIncomeParent->level + 1,
                    'is_group' => 0,
                    'disabled' => 0,
                    'actual_balance' => 0.00,
                    'budget_balance' => 0.00,
                    'variance' => 0.00,
                    'currency' => 'KWD',
                ]);

                // Update cache
                $this->accountCache['Fee Loss Provision'][$companyId] = $feeLossProvisionAccount;
            }
        }

        if ($feeLossProvisionAccount && !$dryRun) {
            // CREDIT: Fee Loss Provision
            JournalEntry::create([
                'transaction_id' => $transactionId,
                'branch_id' => $agent->branch_id,
                'company_id' => $companyId,
                'account_id' => $feeLossProvisionAccount->id,
                'task_id' => $detail->task_id,
                'agent_id' => $agent->id,
                'invoice_id' => $invoice->id,
                'invoice_detail_id' => $detail->id,
                'transaction_date' => $invoice->invoice_date,
                'description' => 'Fee loss provision for ' . $task->reference,
                'debit' => 0,
                'credit' => $companyLoss,
                'balance' => 0,
                'name' => 'Fee Loss Provision',
                'type' => 'income',
                'currency' => $task->currency ?? 'KWD',
                'exchange_rate' => $task->exchange_rate ?? 1.00,
                'amount' => $companyLoss,
            ]);

            $this->createdLossEntries++;
            $this->summary['fee_provision_credit'] += $companyLoss;
        }
    }

    private function getOrCreateTransactionId(Invoice $invoice): ?int
    {
        $existingEntry = JournalEntry::where('invoice_id', $invoice->id)->first();
        return $existingEntry?->transaction_id;
    }

    private function getCachedAccount(string $name, int $companyId): ?Account
    {
        if (!isset($this->accountCache[$name][$companyId])) {
            $this->accountCache[$name][$companyId] = Account::where('name', 'LIKE', $name . '%')
                ->where('company_id', $companyId)
                ->first();
        }
        return $this->accountCache[$name][$companyId];
    }

    private function printSummary(bool $dryRun): void
    {
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('  Summary');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info("Invoices processed:         {$this->processedInvoices}");
        $this->info("Invoice details processed:  {$this->processedDetails}");
        $this->info("Skipped details:            {$this->skippedDetails}");
        $this->info("Profit entries created:     {$this->createdProfitEntries}");
        $this->info("Loss entries created:       {$this->createdLossEntries}");

        $this->newLine();
        $this->info('Amounts by Entry Type:');
        $this->info('─────────────────────────────────────────────────────────');
        $this->info("Profit (Dr):            " . number_format($this->summary['profit_debit'], 3));
        $this->info("Profit Payable (Cr):    " . number_format($this->summary['profit_credit'], 3));
        $this->info("Commission (Dr):        " . number_format($this->summary['commission_debit'], 3));
        $this->info("Commission Payable (Cr):" . number_format($this->summary['commission_credit'], 3));
        $this->info("Agent Loss (Dr):        " . number_format($this->summary['agent_loss_debit'], 3));
        $this->info("Loss Recovery (Cr):     " . number_format($this->summary['loss_recovery_credit'], 3));
        $this->info("Company Loss (Dr):      " . number_format($this->summary['company_loss_debit'], 3));
        $this->info("Supplier Cost (Cr):     " . number_format($this->summary['supplier_cost_credit'], 3));
        $this->info("Fee Provision (Cr):     " . number_format($this->summary['fee_provision_credit'], 3));

        $totalDebits = $this->summary['profit_debit']
            + $this->summary['commission_debit']
            + $this->summary['agent_loss_debit']
            + $this->summary['company_loss_debit'];

        $totalCredits = $this->summary['profit_credit']
            + $this->summary['commission_credit']
            + $this->summary['loss_recovery_credit']
            + $this->summary['supplier_cost_credit']
            + $this->summary['fee_provision_credit'];

        $this->newLine();
        $this->info("Total Debits:           " . number_format($totalDebits, 3));
        $this->info("Total Credits:          " . number_format($totalCredits, 3));
        $this->info("Difference:             " . number_format(abs($totalDebits - $totalCredits), 3));

        if (abs($totalDebits - $totalCredits) < 0.01) {
            $this->info("✅ Entries are BALANCED");
        } else {
            $this->error("❌ Entries are NOT BALANCED - Please review!");
        }

        if (!empty($this->changes) && $dryRun) {
            $this->newLine();
            $this->table(
                ['Invoice', 'Task', 'Profit', 'Commission', 'Markup', 'Loss Type'],
                collect(array_slice($this->changes, 0, 20))->map(fn($c) => [
                    $c['invoice'],
                    $c['task_id'],
                    number_format($c['profit'], 2),
                    number_format($c['commission'], 2),
                    number_format($c['markup'], 2),
                    $c['loss_type'],
                ])->toArray()
            );

            if (count($this->changes) > 20) {
                $this->info('... and ' . (count($this->changes) - 20) . ' more');
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('Run without --dry-run to apply changes.');
        }

        Log::info('FixProfitLossEntries completed', [
            'dry_run' => $dryRun,
            'invoices_processed' => $this->processedInvoices,
            'details_processed' => $this->processedDetails,
            'profit_entries' => $this->createdProfitEntries,
            'loss_entries' => $this->createdLossEntries,
            'skipped' => $this->skippedDetails,
        ]);
    }
}
