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
                $this->table([
                        'ID', 'Supplier Company ID', 'Company Name', 'Supplier Name', 'Surcharge Name',  'Amount'
                    ], $activeSurcharges->map(function($surcharge) use ($company, $supplier) {
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
                    $this->fixOldProfitProcess($paidTasks, $activeSurcharges);
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

    private function fixOldProfitProcess($paidTasks, $activeSurcharges)
{
    $surchargeAmount = $activeSurcharges->first()->amount ?? 0;
    $updatedInvoices = [];
    $failedInvoices = [];

    foreach ($paidTasks as $task) {
        $newMarkup = $task->markup_price - $surchargeAmount;

        Log::info("Adjusting markup for InvoiceDetail ID {$task->invoice_detail_id}", [
            'old_markup' => $task->markup_price,
            'surcharge_amount' => $surchargeAmount,
            'new_markup' => $newMarkup,
        ]);

        try {
            // Update InvoiceDetail markup
            InvoiceDetail::where('id', $task->invoice_detail_id)
                ->update(['markup_price' => $newMarkup]);

            $updatedInvoices[] = [
                'Invoice Detail ID' => $task->invoice_detail_id,
                'Old Markup'        => number_format($task->markup_price, 2),
                'Surcharge'         => number_format($surchargeAmount, 3),
                'New Markup'        => number_format($newMarkup, 2),
                'Status'            => 'Success',
            ];

            // Fetch the invoice detail with relationships
            $invoiceDetail = InvoiceDetail::with(['invoice', 'journalEntrys'])
                ->find($task->invoice_detail_id);

            if (!$invoiceDetail) {
                throw new \Exception("InvoiceDetail not found");
            }

            $agentId = $invoiceDetail->invoice->agent_id ?? null;
            if (!$agentId) {
                throw new \Exception("Invoice {$invoiceDetail->invoice_id} has no agent_id");
            }

            // Step 1: get transaction_ids
            $transactionIds = $invoiceDetail->journalEntrys->pluck('transaction_id')->unique();

            // Step 2: fetch all journal entries with these transaction_ids
            $relatedJournals = JournalEntry::whereIn('transaction_id', $transactionIds)->get();

            // Step 3: filter for commission accounts (73 and 43)
            $commissionJournals = $relatedJournals->whereIn('account_id', [73, 43]);

            foreach ($commissionJournals as $journal) {
                try {
                    $newCommission = $this->calculateCommission($newMarkup, $agentId);

                    if ($journal->account_id == 73) {
                        $journal->debit = $newCommission;
                    } elseif ($journal->account_id == 43) {
                        $journal->credit = $newCommission;
                    }

                    $journal->save();

                    Log::info("Updated journal entry successfully", [
                        'journal_id' => $journal->id,
                        'invoice_detail_id' => $invoiceDetail->id,
                        'account_id' => $journal->account_id,
                        'new_commission' => $newCommission,
                    ]);

                } catch (\Exception $e) {
                    Log::error("Failed to update journal entry", [
                        'journal_id' => $journal->id,
                        'invoice_detail_id' => $invoiceDetail->id,
                        'account_id' => $journal->account_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error("Failed updating InvoiceDetail ID {$task->invoice_detail_id}", [
                'error' => $e->getMessage()
            ]);

            $failedRecord = [
                'Invoice Detail ID' => $task->invoice_detail_id,
                'Old Markup'        => number_format($task->markup_price, 2),
                'Surcharge'         => number_format($surchargeAmount, 3),
                'New Markup'        => number_format($newMarkup, 2),
                'Status'            => 'Failed',
                'Error Message'     => $e->getMessage(),
            ];

            $updatedInvoices[] = $failedRecord;
            $failedInvoices[] = $failedRecord;
        }
    }

    // Show summary
    $this->newLine();
    $this->info('Summary of processed invoices:');
    $this->table(['Invoice Detail ID', 'Old Markup', 'Surcharge', 'New Markup', 'Status'], $updatedInvoices);
    $this->info("Total processed: " . count($updatedInvoices));

    if (count($failedInvoices) > 0) {
        $this->newLine();
        $this->warn('⚠️ Failed Invoices:');
        $this->table(
            ['Invoice Detail ID', 'Old Markup', 'Surcharge', 'New Markup', 'Status', 'Error Message'],
            $failedInvoices
        );
        $this->error('Total failed: ' . count($failedInvoices));
    }
}

private function calculateCommission($newMarkup, $agentId)
{
    $agent = Agent::find($agentId);
    if (!$agent) {
        Log::error("Agent not found", ['agent_id' => $agentId]);
        return 0;
    }

    if ($agent->type_id == 2) {
        return $newMarkup;
    } elseif ($agent->type_id == 3) {
        return $newMarkup * $agent->commission;
    }

    return 0;
}

}