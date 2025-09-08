<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\JournalEntry;
use App\Models\Account;
use App\Models\Supplier;
use App\Models\SupplierCompany;
use App\Models\Agent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class UpdateOldTaskToTransaction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-old-task-to-transaction {--dry-run : Show what would be processed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find old tasks without transaction/journal entries and create them. Only processes tasks with status != confirmed and supplier_pay_date as transaction_date.';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('Running in DRY RUN mode - no changes will be made');
        }
        
        $this->info('Starting to process old tasks without transaction/journal entries...');
        
        try {
            // Find tasks that need processing
            $tasksToProcess = $this->findTasksNeedingProcessing();
            
            if ($tasksToProcess->isEmpty()) {
                $this->info('No tasks found that need processing.');
                return 0;
            }
            
            $this->info("Found {$tasksToProcess->count()} tasks to process:");
            
            // Display summary
            $this->table(
                ['ID', 'Reference', 'Status', 'Total', 'Supplier Pay Date', 'Supplier'],
                $tasksToProcess->map(function ($task) {
                    return [
                        $task->id,
                        $task->reference,
                        $task->status,
                        $task->total ?? 'N/A',
                        $task->supplier_pay_date ? $task->supplier_pay_date->format('Y-m-d') : 'N/A',
                        $task->supplier->name ?? 'N/A'
                    ];
                })->toArray()
            );
            
            if ($dryRun) {
                $this->info('DRY RUN complete - no changes made');
                return 0;
            }
            
            if (!$this->confirm('Do you want to proceed with creating transactions and journal entries for these tasks?')) {
                $this->info('Operation cancelled by user.');
                return 0;
            }
            
            // Process each task
            $processed = 0;
            $errors = 0;
            
            foreach ($tasksToProcess as $task) {
                try {
                    $this->processTask($task);
                    $processed++;
                    $this->info("✓ Processed task: {$task->reference}");
                } catch (Exception $e) {
                    $errors++;
                    $this->error("✗ Failed to process task {$task->reference}: " . $e->getMessage());
                    Log::error("Task processing failed: {$task->reference}", ['error' => $e->getMessage()]);
                }
            }
            
            $this->info("\nProcessing complete:");
            $this->info("Successfully processed: {$processed} tasks");
            if ($errors > 0) {
                $this->warn("Errors encountered: {$errors} tasks");
            }
            
            return 0;
            
        } catch (Exception $e) {
            $this->error('Command failed: ' . $e->getMessage());
            Log::error('Update old task to transaction command failed', ['error' => $e->getMessage()]);
            return 1;
        }
    }
    
    /**
     * Find tasks that need transaction and journal entry processing
     */
    private function findTasksNeedingProcessing()
    {
        return Task::with(['supplier', 'agent.branch'])
            ->whereDoesntHave('journalEntries') // Tasks without journal entries
            ->where('status', '!=', 'confirmed') // Status is not confirmed
            ->whereIn('status', ['issued', 'reissued', 'emd', 'refund']) // Only valid statuses for financial processing
            ->whereNotNull('supplier_pay_date') // Must have supplier_pay_date
            ->whereNotNull('total') // Must have total amount
            ->whereNotNull('supplier_id') // Must have supplier
            ->whereNotNull('company_id') // Must have company
            ->where('total', '>', 0) // Total must be greater than 0
            ->orderBy('supplier_pay_date', 'asc')
            ->get();
    }
    
    /**
     * Process a single task to create transaction and journal entries
     */
    private function processTask(Task $task)
    {
        DB::beginTransaction();
        
        try {
            // Validate task completeness
            if (!$task->is_complete) {
                throw new Exception('Task is not complete. Missing required fields.');
            }
            
            // Get branch ID
            $branchId = $this->getTaskBranchId($task);
            
            // Get supplier company relationship
            $supplierCompany = SupplierCompany::where('supplier_id', $task->supplier_id)
                ->where('company_id', $task->company_id)
                ->first();
                
            if (!$supplierCompany) {
                throw new Exception('Supplier company relationship not found.');
            }
            
            // Get required accounts
            $accounts = $this->getRequiredAccounts($task, $supplierCompany);
            
            // Additional validation: For flight tasks, we must have an issuedByAccount to avoid using parent account
            if ($task->type == 'flight' && !$accounts['issuedByAccount'] && strtolower($task->status) != 'refund') {
                throw new Exception('Flight task must have a valid issued by account to avoid using parent account.');
            }
            
            // Use supplier_pay_date as transaction_date
            $transactionDate = $task->supplier_pay_date ? Carbon::parse($task->supplier_pay_date) : Carbon::now();
            
            // Create transaction
            $transaction = $this->createTransaction($task, $branchId, $transactionDate);
            
            // Create journal entries based on task status
            $this->createJournalEntries($task, $transaction, $accounts, $supplierCompany, $branchId, $transactionDate);
            
            DB::commit();
            
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Get task branch ID
     */
    private function getTaskBranchId(Task $task)
    {
        if ($task->agent && $task->agent->branch_id) {
            return $task->agent->branch_id;
        }
        
        // Fallback to company's main branch by querying directly
        $company = \App\Models\Company::find($task->company_id);
        if ($company) {
            $mainBranch = $company->branches()->first();
            if ($mainBranch) {
                return $mainBranch->id;
            }
        }
        
        throw new Exception('No branch found for task company.');
    }
    
    /**
     * Get required accounts for the task
     */
    private function getRequiredAccounts(Task $task, $supplierCompany)
    {
        // Get root accounts
        $liabilities = Account::where('name', 'like', '%Liabilities%')
            ->where('company_id', $task->company_id)
            ->first();
            
        $expenses = Account::where('name', 'like', '%Expenses%')
            ->where('company_id', $task->company_id)
            ->first();
            
        if (!$liabilities || !$expenses) {
            throw new Exception('Required root accounts (Liabilities/Expenses) not found.');
        }
        
        // Get supplier accounts
        $supplier = $supplierCompany->supplier;
        
        $supplierPayable = Account::where('name', $supplier->name)
            ->where('company_id', $task->company_id)
            ->where('root_id', $liabilities->id)
            ->first();
            
        $supplierCost = Account::where('name', $supplier->name)
            ->where('company_id', $task->company_id)
            ->where('root_id', $expenses->id)
            ->first();
            
        if (!$supplierPayable || !$supplierCost) {
            throw new Exception('Supplier accounts not found.');
        }
        
        // For flight tasks, try to get issued_by account
        $issuedByAccount = null;
        if ($task->type == 'flight' && $task->issued_by) {
            $issuedByAccount = Account::where('name', $task->issued_by)
                ->where('company_id', $task->company_id)
                ->where('root_id', $liabilities->id)
                ->where('parent_id', $supplierPayable->id)
                ->first();
        }
        
        return [
            'liabilities' => $liabilities,
            'expenses' => $expenses,
            'supplierPayable' => $supplierPayable,
            'supplierCost' => $supplierCost,
            'issuedByAccount' => $issuedByAccount
        ];
    }
    
    /**
     * Create transaction record
     */
    private function createTransaction(Task $task, $branchId, $transactionDate)
    {
        // Transaction type depends on status
        $transactionType = strtolower($task->status) == 'refund' ? 'debit' : 'credit';
        $referenceType = strtolower($task->status) == 'refund' ? 'Refund' : 'Payment';
        
        return Transaction::create([
            'branch_id' => $branchId,
            'company_id' => $task->company_id,
            'entity_id' => $task->company_id,
            'entity_type' => 'company',
            'transaction_type' => $transactionType,
            'amount' => $task->total,
            'description' => ucfirst($task->status) . ' Task: ' . $task->reference,
            'reference_type' => $referenceType,
            'transaction_date' => $transactionDate,
            'name' => $task->client_name,
        ]);
    }
    
    /**
     * Create journal entries based on task status
     */
    private function createJournalEntries(Task $task, $transaction, $accounts, $supplierCompany, $branchId, $transactionDate)
    {
        $supplier = $supplierCompany->supplier;
        
        // Determine which payable account to use
        $payableAccountToUse = $accounts['issuedByAccount'] ?? $accounts['supplierPayable'];
        
        switch (strtolower($task->status)) {
            case 'issued':
            case 'reissued':
            case 'emd':
                $this->createIssuedTaskJournalEntries($task, $transaction, $accounts, $supplier, $branchId, $payableAccountToUse);
                break;
                
            case 'refund':
                // For refund flight tasks, ensure we have/create the issued_by account
                if ($task->type == 'flight') {
                    $payableAccountToUse = $this->ensureFlightRefundAccount($task, $accounts, $branchId);
                }
                $this->createRefundTaskJournalEntries($task, $transaction, $accounts, $supplier, $branchId, $payableAccountToUse);
                break;
                
            default:
                throw new Exception('Task status not recognized for financial processing: ' . $task->status);
        }
    }
    
    /**
     * Ensure flight refund task has proper issued_by account
     */
    private function ensureFlightRefundAccount(Task $task, $accounts, $branchId)
    {
        $companyIssuedBy = $task->issued_by ?? 'Not Issued';
        $supplierPayable = $accounts['supplierPayable'];
        
        // Check if issued_by account exists
        $issuedByAccount = Account::where('name', $companyIssuedBy)
            ->where('company_id', $task->company_id)
            ->where('root_id', $accounts['liabilities']->id)
            ->where('parent_id', $supplierPayable->id)
            ->first();
            
        if (!$issuedByAccount) {
            // Create the issued_by account
            $code = 2151;
            $lastIssuedByAccount = Account::where('company_id', $task->company_id)
                ->where('root_id', $accounts['liabilities']->id)
                ->where('parent_id', $supplierPayable->id)
                ->orderBy('code', 'desc')
                ->first();

            if ($lastIssuedByAccount) {
                $code = $lastIssuedByAccount->code + 1;
            }

            $issuedByAccount = Account::create([
                'name' => $companyIssuedBy,
                'parent_id' => $supplierPayable->id,
                'company_id' => $task->company_id,
                'branch_id' => $branchId,
                'root_id' => $accounts['liabilities']->id,
                'code' => $code,
                'account_type' => 'liability',
                'report_type' => 'balance sheet',
                'level' => $supplierPayable->level + 1,
                'is_group' => 0,
                'disabled' => 0,
                'actual_balance' => 0.00,
                'budget_balance' => 0.00,
                'variance' => 0.00,
                'currency' => 'KWD',
            ]);
        }
        
        return $issuedByAccount;
    }
    
    /**
     * Create journal entries for issued/reissued/emd tasks
     */
    private function createIssuedTaskJournalEntries($task, $transaction, $accounts, $supplier, $branchId, $payableAccountToUse)
    {
        // Use the transaction's transaction_date (which is the task's supplier_pay_date)
        $transactionDate = $transaction->transaction_date;
        
        // Debit: Supplier Cost (Expense)
        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $task->company_id,
            'branch_id' => $branchId,
            'account_id' => $accounts['supplierCost']->id,
            'task_id' => $task->id,
            'transaction_date' => $transactionDate,
            'description' => 'Task from supplier (Expenses): ' . $supplier->name,
            'name' => $supplier->name,
            'debit' => $task->total,
            'credit' => 0,
            'balance' => $task->total,
            'type' => 'payable',
        ]);
        
        // Credit: Supplier Payable (Liability)
        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $task->company_id,
            'branch_id' => $branchId,
            'account_id' => $payableAccountToUse->id,
            'task_id' => $task->id,
            'transaction_date' => $transactionDate,
            'description' => 'Records Payable to (Liabilities): ' . $supplier->name,
            'name' => $supplier->name,
            'debit' => 0,
            'credit' => $task->total,
            'balance' => $task->total,
            'type' => 'payable',
        ]);
    }
    
    /**
     * Create journal entries for refund tasks
     */
    private function createRefundTaskJournalEntries($task, $transaction, $accounts, $supplier, $branchId, $payableAccountToUse)
    {
        // Use the transaction's transaction_date (which is the task's supplier_pay_date)
        $transactionDate = $transaction->transaction_date;
        
        // Debit: Supplier Payable (reduces liability)
        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $task->company_id,
            'branch_id' => $branchId,
            'account_id' => $payableAccountToUse->id,
            'task_id' => $task->id,
            'transaction_date' => $transactionDate,
            'description' => 'Refund Task - Supplier refunds us (Liabilities): ' . $payableAccountToUse->name,
            'debit' => $task->total,
            'credit' => 0,
            'name' => $supplier->name,
            'type' => 'refund',
        ]);
        
        // Credit: Supplier Cost (reduces expense)
        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $task->company_id,
            'branch_id' => $branchId,
            'account_id' => $accounts['supplierCost']->id,
            'task_id' => $task->id,
            'transaction_date' => $transactionDate,
            'description' => 'Refund Task - Supplier cost return (Expenses): ' . $accounts['supplierCost']->name,
            'debit' => 0,
            'credit' => $task->total,
            'name' => $supplier->name,
            'type' => 'refund',
        ]);
    }
}
