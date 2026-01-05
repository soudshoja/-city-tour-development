<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Task;
use App\Models\Supplier;
use App\Models\InvoiceDetail;
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
        $this->warn("Skipped: {$this->skippedCount}");
        $this->error("Integrity issues: {$this->integrityIssuesCount}");

        if (!empty($this->successTasks)) {
            $this->newLine();
            $this->info('Newly Applied Tasks:');
            $this->table(
                ['Task ID', 'Price', 'Tax', 'Old Surcharge', 'New Surcharge', 'Old Total', 'New Total'],
                collect($this->successTasks)->map(function ($task) {
                    return [
                        'Task ID' => $task['task_id'],
                        'Price' => number_format($task['price'], 2),
                        'Tax' => number_format($task['tax'], 2),
                        'Old Surcharge' => number_format($task['old_surcharge'], 2),
                        'New Surcharge' => number_format($task['new_surcharge'], 2),
                        'Old Total' => number_format($task['old_total'], 2),
                        'New Total' => number_format($task['new_total'], 2)
                    ];
                })->toArray()
            );
        }

        if (!empty($this->alreadyAppliedTasks)) {
            $this->newLine();
            $this->info('Already Applied (Field Updated):');
            $this->table(
                ['Task ID', 'Price', 'Tax', 'Old Surcharge', 'New Surcharge', 'Total'],
                collect($this->alreadyAppliedTasks)->map(function ($task) {
                    return [
                        'Task ID' => $task['task_id'],
                        'Price' => number_format($task['price'], 2),
                        'Tax' => number_format($task['tax'], 2),
                        'Old Surcharge' => number_format($task['old_surcharge'], 2),
                        'New Surcharge' => number_format($task['new_surcharge'], 2),
                        'Total' => number_format($task['total'], 2)
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
                        'Price' => number_format($issue['price'], 2),
                        'Tax' => number_format($issue['tax'], 2),
                        'Surcharge' => number_format($issue['supplier_surcharge'], 2),
                        'Total' => number_format($issue['total'], 2),
                        'Expected' => number_format($issue['expected_with_surcharge'], 2),
                        'Difference' => number_format($issue['difference'], 2)
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
            'skipped' => $this->skippedCount,
            'integrity_issues' => $this->integrityIssuesCount
        ]);
    }
}
