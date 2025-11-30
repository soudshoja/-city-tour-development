<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Agent;
use App\Models\Company;
use App\Models\Task;
use App\Models\Supplier;
use App\Models\SupplierCompany;
use App\Models\SupplierSurcharge;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Account;
use Exception;

class FixOldProfit extends Command
{
    protected $signature = 'fix:old-profit-data
                            {--dry-run : Show the expected process without making changes to the database}
                            {--proceed : Skip dry run mode and make changes onto database}
                            {--companyId= : The ID of the company that affected by the changes}
                            {--supplierId= : The ID of the supplier that is applied with supplier charges}';

    protected $description = 'Fix the profit gained by an agent based on the supplier charges';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $proceed = $this->option('proceed');
        $companyId = $this->option('companyId');
        $supplierId = $this->option('supplierId');

        if (!$companyId) {
            $this->error('Company ID is required when using this command');
            return 0;
        }

        if (!$supplierId) {
            $this->error('Supplier ID is required when using this command');
            return 0;
        }

        if ($dryRun) {
            $this->info('Running in DRY RUN mode - no changes will be made');
        }

        $this->info('Starting to fix the profit of agents for old task based on supplier charges that implemented');
        $this->newLine();

        try {
            $company = Company::find($companyId);
            Log::info('Found the company with ID ' . $companyId . ', ' . $company->name);
            if (!$company) {
                $this->error('Company with ID ' . $companyId . 'is not found within the system database');
                return 0;
            }

            $supplier = Supplier::find($supplierId);
            Log::info('Found the supplier with ID ' . $supplierId . ', ' . $supplier->name);
            if (!$supplier) {
                $this->error('Supplier with ID ' . $supplierId . 'is not found within the system database');
                return 0;
            }

            $activeSurcharges = $this->getActiveSupplierSurcharge($company, $supplier);
            Log::info('Active surcharge ', [
                'data' => $activeSurcharges,
            ]);
            if (!$activeSurcharges) {
                Log::info('This supplier does not have active supplier surcharge. Aborting the process');
                $this->error('This supplier does not have active supplier surcharge. Aborting the process');
                return 0;
            }

            $paidTasks = $this->getOldPaidInvoices($company, $supplier);
            Log::info('query paid invoice', [
                'data' => $paidTasks,
            ]);
            if (!$paidTasks) {
                Log::info('No tasks within the supplier and company');
                $this->error('No tasks within the supplier and company');
                return 0;
            }

            if ($dryRun) {
                $this->table(
                    [
                        'ID',
                        'Supplier Company ID',
                        'Company Name',
                        'Supplier Name',
                        'Surcharge Name',
                        'Amount'
                    ],
                    $activeSurcharges->map(function ($surcharge) use ($company, $supplier) {
                        return [
                            'ID' => $surcharge->id,
                            'Supplier Company ID' => $surcharge->supplier_company_id,
                            'Company Name' => $company->name,
                            'Supplier Name' => $supplier->name,
                            'Surcharge Name' => $surcharge->label,
                            'Amount' => $surcharge->amount,
                        ];
                    })->toArray()
                );

                $this->info("\nFound {$paidTasks->count()} invoices within supplier {$supplier->name} and company {$company->name}: ");

                $this->table(
                    [
                        'Invoice Detail ID',
                        'Task ID',
                        'Invoice Number',
                        'Task Price',
                        'Supplier Price',
                        'Markup Price',
                        'Agent ID',
                        'Status',
                    ],
                    $paidTasks->map(function ($paidTask) {
                        return [
                            'Invoice Detail ID' => $paidTask->invoice_detail_id,
                            'Task ID'           => $paidTask->task_id,
                            'Invoice Number'    => $paidTask->invoice_number,
                            'Task Price'        => $paidTask->task_price,
                            'Supplier Price'    => $paidTask->supplier_price,
                            'Markup Price'      => $paidTask->markup_price,
                            'Agent ID'          => $paidTask->agent_id,
                            'Status'            => $paidTask->status,
                        ];
                    })->toArray()
                );

                if ($dryRun) {
                    $this->info('DRY RUN completed - no changes has been made to the database');
                    return 0;
                }
            }

            if ($proceed) {

                try {
                    $this->fixOldProfitProcess($paidTasks, $activeSurcharges, $supplier);
                } catch (Exception $e) {
                    $this->error('Failed to fix old data of paid invoices');
                    Log::error('Failed to fix old data of paid invoices', [
                        'error' => $e->getMessage()
                    ]);
                }

                return 0;
            }
        } catch (Exception $e) {
            $this->error('Error fixing the old profit for agents based on implemented supplier charges', $e->getMessage());
            Log::error('Failed to fix the old profit for agents based on implemented supplier charges');
            return 0;
        }
    }

    private function getActiveSupplierSurcharge($company, $supplier)
    {
        Log::info('Starting to get active supplier surcharge based on company ' . $company->name . ' and supplier ' . $supplier->name);

        $supplierCompany = SupplierCompany::where('supplier_id', $supplier->id)
            ->where('company_id', $company->id)
            ->first();
        if (!$supplierCompany) {
            Log::info('No active supplier for such company');;
            $this->info('No active supplier for such company');
            return 0;
        }

        $activeSurcharge = SupplierSurcharge::where('supplier_company_id', $supplierCompany->id)
            ->get();
        Log::info('Found active surcharge for company ' . $company->name . ' and supplier ' . $supplier->name);
        if (!$activeSurcharge) {
            Log::info('No active surcharge for the company and supplier');
            $this->info('No active surcharge for the company and supplier');
            return 0;
        }

        return $activeSurcharge;
    }

    private function getOldPaidInvoices($company, $supplier)
    {
        Log::info('Starting to get the old paid invoices for recalculating the profit of agents based on implemented supplier surcharge');

        $paidInvoices = InvoiceDetail::select(
            'invoice_details.id as invoice_detail_id',
            'tasks.id as task_id',
            'invoice_details.invoice_number',
            'invoice_details.task_price',
            'invoice_details.supplier_price',
            'invoice_details.markup_price',
            'tasks.agent_id',
            'invoices.status'
        )
            ->join('invoices', 'invoice_details.invoice_id', '=', 'invoices.id')
            ->join('tasks', 'invoice_details.task_id', '=', 'tasks.id')
            ->where('tasks.supplier_id', $supplier->id)
            ->where('tasks.company_id', $company->id)
            ->where('invoices.status', 'paid')
            ->distinct('invoice_details.id')
            ->get();


        Log::info("Found {$paidInvoices->count()} paid invoices within the supplier and company");

        return $paidInvoices;
    }

    private function fixOldProfitProcess($paidTasks, $activeSurcharges, $supplier)
    {
        $surchargeAmount = $activeSurcharges->first()->amount ?? 0;
        $processedCount = 0;
        $successCount = 0;
        $failedCount = 0;
        $skippedCount = 0;
        
        $successTasks = [];
        $skippedTasks = [];
        $failedTasks = [];

        foreach ($paidTasks as $task) {
            $newMarkup = $task->markup_price - $surchargeAmount;
            $processedCount++;

            Log::info("Adjusting markup for InvoiceDetail ID {$task->invoice_detail_id}", [
                'old_markup' => $task->markup_price,
                'surcharge_amount' => $surchargeAmount,
                'new_markup' => $newMarkup,
            ]);

            try {
                InvoiceDetail::where('id', $task->invoice_detail_id)
                    ->update(['markup_price' => $newMarkup]);

                $invoiceDetail = InvoiceDetail::with(['invoice', 'journalEntrys'])
                    ->find($task->invoice_detail_id);

                if (!$invoiceDetail) {
                    throw new \Exception("InvoiceDetail not found");
                }

                $agentId = $invoiceDetail->invoice->agent_id ?? null;
                if (!$agentId) {
                    throw new \Exception("Invoice {$invoiceDetail->invoice_id} has no agent_id");
                }

                $agent = Agent::find($agentId);
                if (!$agent) {
                    Log::error("Agent not found", ['agent_id' => $agentId]);
                    $skippedCount++;
                    $skippedTasks[] = ['task_id' => $task->task_id, 'reason' => 'Agent not found'];
                    continue;
                }

                $journalEntries = JournalEntry::where('invoice_detail_id', $task->invoice_detail_id)->get();

                $taskIds = $journalEntries->pluck('task_id')->unique();
                if ($taskIds->isEmpty()) {
                    $skippedCount++;
                    $skippedTasks[] = ['task_id' => $task->task_id, 'reason' => 'No task IDs in journal entries'];
                    continue;
                } else if ($taskIds->count() > 1) {
                    $skippedCount++;
                    $skippedTasks[] = ['task_id' => $task->task_id, 'reason' => 'Multiple task IDs in journal entries'];
                    continue;
                }

                $taskId = $taskIds->first();

                $allJournal = JournalEntry::where('task_id', $taskId)->get();

                if ($allJournal->isEmpty()) {
                    $skippedCount++;
                    $skippedTasks[] = ['task_id' => $taskId, 'reason' => 'No journal entries found'];
                    continue;
                }

                $has73 = $allJournal->where('account_id', 73)->isNotEmpty();
                $has43 = $allJournal->where('account_id', 43)->isNotEmpty();
                $supplierAccountIds = [280, 281, 282, 294, 1264, 1276, 1277, 1278, 1279, 1280, 1281];
                $hasSupplier = $allJournal->whereIn('account_id', $supplierAccountIds)->isNotEmpty();

                if (!$has73 || !$has43) {
                    $skippedCount++;
                    $skippedTasks[] = ['task_id' => $taskId, 'reason' => 'Missing commission accounts (73/43)'];
                    continue;
                }

                if (!$hasSupplier) {
                    $skippedCount++;
                    $skippedTasks[] = ['task_id' => $taskId, 'reason' => 'Missing supplier account'];
                    continue;
                }

                $commissionJournals = $allJournal->whereIn('account_id', [73, 43]);
                $supplierJournal = $allJournal->whereIn('account_id', $supplierAccountIds)->first();

                $supplierAccount = Account::where('id', $supplierJournal->account_id)
                    ->where('company_id', $agent->branch->company->id)
                    ->first();

                if (!$supplierAccount) {
                    $skippedCount++;
                    $skippedTasks[] = ['task_id' => $taskId, 'reason' => 'Supplier account not found'];
                    continue;
                }

                $transactionIds = $commissionJournals->pluck('transaction_id')->unique();
                if ($transactionIds->isEmpty()) {
                    $skippedCount++;
                    $skippedTasks[] = ['task_id' => $taskId, 'reason' => 'No transaction ID found'];
                    continue;
                } else if ($transactionIds->count() > 1) {
                    $skippedCount++;
                    $skippedTasks[] = ['task_id' => $taskId, 'reason' => 'Multiple transaction IDs found'];
                    continue;
                }

                $transactionId = $transactionIds->first();

                // Update commission journals
                foreach ($commissionJournals as $journal) {
                    $newCommission = $this->calculateCommission($newMarkup, $agent);

                    if ($journal->account_id == 73) {
                        $journal->debit = $newCommission;
                    } elseif ($journal->account_id == 43) {
                        $journal->credit = $newCommission;
                    }
                    $journal->save();
                }

                // Create supplier charge journal
                JournalEntry::create([
                    'transaction_id' => $transactionId,
                    'company_id' => $agent->branch->company->id,
                    'branch_id' => $agent->branch->id,
                    'account_id' => $supplierJournal->account_id,
                    'invoice_id' => $invoiceDetail->invoice_id,
                    'invoice_detail_id' => $invoiceDetail->id,
                    'transaction_date' => $commissionJournals->first()->transaction_date,
                    'description' => 'Supplier charges based on ' . $activeSurcharges->first()->label,
                    'debit' => 0,
                    'credit' => $surchargeAmount,
                    'balance' => 0,
                    'name' => $supplier->name,
                    'type' => 'payable',
                    'type_reference_id' => $supplierAccount->id,
                    'task_id' => $taskId,
                ]);

                $successCount++;
                $successTasks[] = [
                    'Invoice Detail ID' => $task->invoice_detail_id,
                    'Task ID' => $taskId,
                    'Old Markup' => number_format($task->markup_price, 2),
                    'Surcharge' => number_format($surchargeAmount, 3),
                    'New Markup' => number_format($newMarkup, 2),
                ];

            } catch (\Exception $e) {
                $failedCount++;
                $failedTasks[] = ['task_id' => $task->task_id, 'error' => $e->getMessage()];
            }
        }

        Log::info('===== Fix Old Profit Summary =====', [
            'total_processed' => $processedCount,
            'success' => $successCount,
            'skipped' => $skippedCount,
            'failed' => $failedCount,
        ]);

        if (!empty($skippedTasks)) {
            Log::warning('Skipped tasks', ['tasks' => $skippedTasks]);
        }

        if (!empty($failedTasks)) {
            Log::error('Failed tasks', ['tasks' => $failedTasks]);
        }

        // Console output
        $this->newLine();
        $this->info('===== Summary =====');
        $this->info("Total processed: {$processedCount}");
        $this->info("Success: {$successCount}");
        $this->warn("Skipped: {$skippedCount}");
        $this->error("Failed: {$failedCount}");

        if (!empty($successTasks)) {
            $this->newLine();
            $this->info('Succeeded Tasks:');
            $this->table(
                ['Invoice Detail ID', 'Task ID', 'Old Markup', 'Surcharge', 'New Markup'],
                $successTasks
            );
        }
    }

    private function calculateCommission($newMarkup, $agent)
    {
        if ($agent->type_id == 2) {
            return $newMarkup;
        } elseif ($agent->type_id == 3) {
            return $newMarkup * $agent->commission;
        }

        return 0;
    }
}
