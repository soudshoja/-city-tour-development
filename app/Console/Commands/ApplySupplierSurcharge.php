<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Task;
use App\Models\Supplier;
use App\Models\InvoiceDetail;
use App\Models\JournalEntry;
use App\Models\Agent;
use Exception;

class ApplySupplierSurcharge extends Command
{
    protected $signature = 'supplier:apply-surcharge
                            {--dry-run : Preview changes without applying}
                            {--proceed : Apply the changes}';

    protected $description = 'Apply supplier surcharge to tasks with supplier_id=1 and issued_by=KWIKT2843';

    private $processedCount = 0;
    private $successCount = 0;
    private $skippedCount = 0;
    private $integrityIssuesCount = 0;
    private $alreadyAppliedCount = 0;
    
    private $successTasks = [];
    private $skippedTasks = [];
    private $integrityIssues = [];
    private $alreadyAppliedTasks = [];
    
    // Add journal tracking arrays
    private $journalUpdates = [];
    private $journalNotFound = [];

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $proceed = $this->option('proceed');

        if (!$dryRun && !$proceed) {
            $this->error('Please specify either --dry-run or --proceed');
            return 1;
        }

        if ($dryRun) {
            $this->info('Running in DRY RUN mode - no changes will be made');
        }

        $this->info('Starting supplier surcharge application for supplier_id=1, issued_by=KWIKT2843');
        $this->newLine();

        try {
            $supplier = Supplier::find(1);
            if (!$supplier) {
                $this->error('Supplier with ID 1 not found');
                Log::error('Supplier with ID 1 not found');
                return 1;
            }

            $this->info("Found supplier: {$supplier->name}");
            Log::info("Processing supplier surcharge for {$supplier->name}");

            $tasks = Task::where('supplier_id', 1)
                ->where('issued_by', 'KWIKT2843')
                ->whereIn('status', ['issued', 'reissued'])
                ->get();

            if ($tasks->isEmpty()) {
                $this->error('No tasks found matching criteria');
                Log::info('No tasks found for supplier_id=1 and issued_by=KWIKT2843');
                return 0;
            }

            $this->info("Found {$tasks->count()} tasks to process");
            Log::info("Found {$tasks->count()} tasks for processing");

            $surchargeAmount = 0.350;
            $this->info("Applying surcharge amount: {$surchargeAmount}");
            Log::info("Hardcoded surcharge amount: {$surchargeAmount}");

            if ($dryRun) {
                $this->displayDryRunInfo($tasks, $surchargeAmount);
                return 0;
            }

            if ($proceed) {
                DB::transaction(function () use ($tasks, $surchargeAmount) {
                    $this->processTasks($tasks, $surchargeAmount);
                });

                $this->displaySummary();
            }

            return 0;

        } catch (Exception $e) {
            $this->error('Error applying supplier surcharge: ' . $e->getMessage());
            Log::error('Failed to apply supplier surcharge', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    private function processTasks($tasks, $surchargeAmount)
    {
        foreach ($tasks as $task) {
            $this->processedCount++;

            $oldPrice = $task->price;
            $oldTax = $task->tax;
            $oldSurcharge = $task->supplier_surcharge;
            $oldTotal = $task->total;

            $expectedTotalWithSurcharge = $oldPrice + $oldTax + $surchargeAmount;
            $expectedTotalWithoutSurcharge = $oldPrice + $oldTax;

            $surchargeAlreadyInTotal = abs($oldTotal - $expectedTotalWithSurcharge) < 0.01;
            $surchargeNotInTotal = abs($oldTotal - $expectedTotalWithoutSurcharge) < 0.01;

            if (!$surchargeAlreadyInTotal && !$surchargeNotInTotal) {
                $this->integrityIssuesCount++;
                $this->integrityIssues[] = [
                    'task_id' => $task->id,
                    'price' => $oldPrice,
                    'tax' => $oldTax,
                    'supplier_surcharge' => $oldSurcharge,
                    'total' => $oldTotal,
                    'expected_with_surcharge' => $expectedTotalWithSurcharge,
                    'expected_without_surcharge' => $expectedTotalWithoutSurcharge,
                    'difference' => $oldTotal - $expectedTotalWithSurcharge
                ];
                Log::error("Task {$task->id}: Integrity issue detected", [
                    'task_id' => $task->id,
                    'price' => $oldPrice,
                    'tax' => $oldTax,
                    'old_supplier_surcharge' => $oldSurcharge,
                    'old_total' => $oldTotal,
                    'expected_with_surcharge' => $expectedTotalWithSurcharge,
                    'expected_without_surcharge' => $expectedTotalWithoutSurcharge,
                    'difference' => $oldTotal - $expectedTotalWithSurcharge
                ]);
                continue;
            }

            if ($surchargeAlreadyInTotal) {
                $task->supplier_surcharge = $surchargeAmount;
                $task->save();

                $this->alreadyAppliedCount++;
                $this->alreadyAppliedTasks[] = [
                    'task_id' => $task->id,
                    'price' => $oldPrice,
                    'tax' => $oldTax,
                    'old_surcharge' => $oldSurcharge,
                    'new_surcharge' => $surchargeAmount,
                    'total' => $oldTotal
                ];

                Log::info("Task {$task->id}: Surcharge already in total, updated field only", [
                    'task_id' => $task->id,
                    'price' => $oldPrice,
                    'tax' => $oldTax,
                    'old_supplier_surcharge' => $oldSurcharge,
                    'new_supplier_surcharge' => $surchargeAmount,
                    'total' => $oldTotal
                ]);
            } else {
                $task->supplier_surcharge = $surchargeAmount;
                $task->total = $oldPrice + $oldTax + $surchargeAmount;
                $task->save();

                $newTotal = $task->total;

                $this->successCount++;
                $this->successTasks[] = [
                    'task_id' => $task->id,
                    'price' => $oldPrice,
                    'tax' => $oldTax,
                    'old_surcharge' => $oldSurcharge,
                    'new_surcharge' => $surchargeAmount,
                    'old_total' => $oldTotal,
                    'new_total' => $newTotal
                ];

                Log::info("Task {$task->id}: Applied supplier surcharge", [
                    'task_id' => $task->id,
                    'price' => $oldPrice,
                    'tax' => $oldTax,
                    'old_supplier_surcharge' => $oldSurcharge,
                    'new_supplier_surcharge' => $surchargeAmount,
                    'old_total' => $oldTotal,
                    'new_total' => $newTotal
                ]);
            }

            $invoiceDetails = InvoiceDetail::where('task_id', $task->id)->get();
            foreach ($invoiceDetails as $detail) {
                $oldSupplierPrice = $detail->supplier_price;
                $oldMarkupPrice = $detail->markup_price;

                $detail->supplier_price = $task->total;
                $detail->markup_price = $detail->task_price - $detail->supplier_price;
                $detail->save();

                Log::info("Task {$task->id}: Updated InvoiceDetail {$detail->id}", [
                    'invoice_detail_id' => $detail->id,
                    'task_id' => $task->id,
                    'old_supplier_price' => $oldSupplierPrice,
                    'new_supplier_price' => $detail->supplier_price,
                    'old_markup_price' => $oldMarkupPrice,
                    'new_markup_price' => $detail->markup_price,
                    'task_price' => $detail->task_price
                ]);
            }
            
            $agentCommission = $this->calculateCommission($task);
            
            $commisionAccount = [
                73 => 'debit',
                43 => 'credit',
            ];

            foreach ($commisionAccount as $accountId => $field) {
                $entries = JournalEntry::where('task_id', $task->id)
                    ->where('account_id', $accountId)
                    ->get();

                if ($entries->isEmpty()) {
                    Log::info("No journal entry found with account {$accountId} task ID: {$task->id}");
                    $this->journalNotFound[] = [
                        'task_id' => $task->id,
                        'account_id' => $accountId,
                    ];
                    continue;
                }

                foreach ($entries as $entry) {
                    $oldAmount = $entry->amount;
                    $oldValue = $entry->$field;

                    $entry->amount = $agentCommission;
                    $entry->$field = $agentCommission;
                    $entry->save();

                    // Track journal updates
                    $this->journalUpdates[] = [
                        'task_id' => $task->id,
                        'journal_entry_id' => $entry->id,
                        'account_id' => $accountId,
                        'field' => $field,
                        'old_amount' => $oldAmount,
                        'new_amount' => $agentCommission,
                        'old_value' => $oldValue,
                        'new_value' => $agentCommission,
                    ];

                    Log::info("Task {$task->id}: Updated Journal Entry {$entry->id}", [
                        'journal_entry_id' => $entry->id,
                        'account_id' => $accountId,
                        'field_updated' => $field,
                        'old_amount' => $oldAmount,
                        'new_amount' => $entry->amount,
                        "old_{$field}" => $oldValue,
                        "new_{$field}" => $entry->$field,
                    ]);
                }
            
            }
            
            $taskAccount = [
                107 => 'credit',
                280 => 'debit',
            ];

            foreach ($taskAccount as $accountId => $field) {
                $entries = JournalEntry::where('task_id', $task->id)
                ->where('account_id', $accountId)
                ->get();

                if ($entries->isEmpty()) {
                    Log::info("No journal entry found with account {$accountId} task ID: {$task->id}");
                    $this->journalNotFound[] = [
                        'task_id' => $task->id,
                        'account_id' => $accountId,
                    ];
                    continue;
                }

                foreach ($entries as $entry) {
                    $oldAmount = $entry->amount;
                    $oldValue = $entry->$field;

                    $entry->amount = $task->total;
                    $entry->$field = $task->total;
                    $entry->save();

                    $this->journalUpdates[] = [
                        'task_id' => $task->id,
                        'journal_entry_id' => $entry->id,
                        'account_id' => $accountId,
                        'field' => $field,
                        'old_amount' => $oldAmount,
                        'new_amount' => $task->total,
                        'old_value' => $oldValue,
                        'new_value' => $task->total,
                    ];

                    Log::info("Task {$task->id}: Updated Journal Entry {$entry->id}", [
                        'journal_entry_id' => $entry->id,
                        'account_id' => $accountId,
                        'field_updated' => $field,
                        'old_amount' => $oldAmount,
                        'new_amount' => $entry->amount,
                        "old_{$field}" => $oldValue,
                        "new_{$field}" => $entry->$field,
                    ]);
                }
            }
        }
    }

    private function displayDryRunInfo($tasks, $surchargeAmount)
    {
        $this->info("Surcharge amount to apply: {$surchargeAmount}");
        $this->newLine();
        $this->info('Tasks Preview (first 20):');
        $this->table(
            ['Task ID', 'Status', 'Price', 'Tax', 'Current Surcharge', 'Current Total', 'Company ID'],
            $tasks->take(20)->map(function ($task) {
                return [
                    'Task ID' => $task->id,
                    'Status' => $task->status,
                    'Price' => number_format($task->price, 2),
                    'Tax' => number_format($task->tax, 2),
                    'Current Surcharge' => number_format($task->supplier_surcharge, 2),
                    'Current Total' => number_format($task->total, 2),
                    'Company ID' => $task->company_id
                ];
            })->toArray()
        );

        $this->info("\nDRY RUN completed - no changes made to database");
    }

    private function displaySummary()
    {
        $this->newLine();
        $this->info('===== Summary =====');
        $this->info("Total processed: {$this->processedCount}");
        $this->info("Newly applied: {$this->successCount}");
        $this->info("Already applied (updated field): {$this->alreadyAppliedCount}");
        $this->info("Journal entries updated: " . count($this->journalUpdates));
        $this->warn("Skipped: {$this->skippedCount}");
        $this->warn("Journal entries not found: " . count($this->journalNotFound));
        $this->error("Integrity issues: {$this->integrityIssuesCount}");

        $journalByTask = collect($this->journalUpdates)->groupBy('task_id');

        if (!empty($this->successTasks)) {
            $this->newLine();
            $this->info('Newly Applied Tasks:');
            $this->table(
                ['Task ID', 'Old Total', 'New Total', 'Old Debit (73)', 'New Debit (73)', 'Old Credit (43)', 'New Credit (43)'],
                collect($this->successTasks)->map(function ($task) use ($journalByTask) {
                    $taskJournals = $journalByTask->get($task['task_id'], collect());
                    $journal73 = $taskJournals->firstWhere('account_id', 73);
                    $journal43 = $taskJournals->firstWhere('account_id', 43);

                    return [
                        'Task ID' => $task['task_id'],
                        'Old Total' => number_format($task['old_total'], 3),
                        'New Total' => number_format($task['new_total'], 3),
                        'Old Journal Commission-Expenses' => $journal73 ? number_format($journal73['old_value'] ?? 0, 3) : '-',
                        'New Journal Commission-Expenses' => $journal73 ? number_format($journal73['new_value'], 3) : '-',
                        'Old Journal Commission-Liabilities' => $journal43 ? number_format($journal43['old_value'] ?? 0, 3) : '-',
                        'New Journal Commission-Liabilities' => $journal43 ? number_format($journal43['new_value'], 3) : '-',
                    ];
                })->toArray()
            );
        }

        if (!empty($this->alreadyAppliedTasks)) {
            $this->newLine();
            $this->info('Already Applied (Field Updated):');
            $this->table(
                ['Task ID', 'Total', 'Old Debit (73)', 'New Debit (73)', 'Old Credit (43)', 'New Credit (43)'],
                collect($this->alreadyAppliedTasks)->map(function ($task) use ($journalByTask) {
                    $taskJournals = $journalByTask->get($task['task_id'], collect());
                    $journal73 = $taskJournals->firstWhere('account_id', 73);
                    $journal43 = $taskJournals->firstWhere('account_id', 43);

                    return [
                        'Task ID' => $task['task_id'],
                        'Total' => number_format($task['total'], 3),
                        'Old Journal Commission-Expenses' => $journal73 ? number_format($journal73['old_value'] ?? 0, 3) : '-',
                        'New Journal Commission-Expenses' => $journal73 ? number_format($journal73['new_value'], 3) : '-',
                        'Old Journal Commission-Liabilities' => $journal43 ? number_format($journal43['old_value'] ?? 0, 3) : '-',
                        'New Journal Commission-Liabilities' => $journal43 ? number_format($journal43['new_value'], 3) : '-',
                    ];
                })->toArray()
            );
        }

        if (!empty($this->integrityIssues)) {
            $this->newLine();
            $this->error('Tasks with Integrity Issues (Manual Review Required):');
            $this->table(
                ['Task ID', 'Price', 'Tax', 'Surcharge', 'Total', 'Expected', 'Difference'],
                collect($this->integrityIssues)->map(function ($issue) {
                    return [
                        'Task ID' => $issue['task_id'],
                        'Price' => number_format($issue['price'], 3),
                        'Tax' => number_format($issue['tax'], 3),
                        'Surcharge' => number_format($issue['supplier_surcharge'] ?? 0, 3),
                        'Total' => number_format($issue['total'], 3),
                        'Expected' => number_format($issue['expected_with_surcharge'], 3),
                        'Difference' => number_format($issue['difference'], 3)
                    ];
                })->toArray()
            );
        }

        if (!empty($this->skippedTasks)) {
            $this->newLine();
            $this->warn('Skipped Tasks:');
            $this->table(
                ['Task ID', 'Reason'],
                $this->skippedTasks
            );
        }

        Log::info('===== Supplier Surcharge Application Summary =====', [
            'total_processed' => $this->processedCount,
            'newly_applied' => $this->successCount,
            'already_applied' => $this->alreadyAppliedCount,
            'journal_updated' => count($this->journalUpdates),
            'journal_not_found' => count($this->journalNotFound),
            'skipped' => $this->skippedCount,
            'integrity_issues' => $this->integrityIssuesCount
        ]);
    }

    private function calculateCommission($task)
    {   
        $agent = Agent::where('id', $task->agent_id)->first();
        if (!$agent) {
            Log::info("Task {$task->id}: No agent found. Skipping commission calculation.");
            return 0;
        }

        if (in_array($agent->type_id, [2, 3])) {
            $sellingPrice = $task->invoiceDetail->task_price ?? 0;
            $supplierPrice = $task->total ?? 0;
            $rate = $agent->commission ?? 0.15;
            $commission = $rate * ($sellingPrice - $supplierPrice);

            Log::info("Task {$task->id}: Commission calculated", [
                'agent_id' => $agent->id,
                'agent_name' => $agent->name,
                'agent_type_id' => $agent->type_id,
                'selling_price' => $sellingPrice,
                'supplier_price' => $supplierPrice,
                'markup' => $sellingPrice - $supplierPrice,
                'rate' => $rate,
                'commission' => $commission,
                'formula' => "{$rate} * ({$sellingPrice} - {$supplierPrice}) = {$commission}",
            ]);
        } else {
            $commission = 0;

            Log::info("Task {$task->id}: Commission is 0. Agent type not eligible", [
                'agent_id' => $agent->id,
                'agent_name' => $agent->name,
                'agent_type_id' => $agent->type_id,
                'eligible_types' => [2, 3],
            ]);
        }

        return $commission;
    }
}