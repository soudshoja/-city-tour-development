<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentCharge;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoicePartial;
use App\Models\JournalEntry;
use App\Models\PaymentMethod;
use App\Models\SupplierCompany;
use App\Models\SupplierSurcharge;
use App\Services\ChargeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixProfitAndCommission extends Command
{
    protected $signature = 'fix:profit-commission 
                            {--company= : Specific company ID}
                            {--invoice= : Specific invoice ID}
                            {--agent= : Specific agent ID}
                            {--dry-run : Preview changes without saving}
                            {--from-date= : Start date (Y-m-d)}
                            {--to-date= : End date (Y-m-d)}
                            {--force : Skip confirmation}';

    protected $description = 'Calculate profit, commission and fix COA entries';

    private int $processedInvoices = 0;
    private int $updatedDetails = 0;
    private int $createdExpenseEntries = 0;
    private int $createdLiabilityEntries = 0;
    private int $fixedExpenseEntries = 0;
    private int $fixedLiabilityEntries = 0;
    private array $changes = [];
    private bool $verboseMode = false;

    // Cache for accounts
    private array $expenseAccounts = [];
    private array $liabilityAccounts = [];

    public function handle()
    {
        $this->verboseMode = $this->output->isVerbose();
        $dryRun = $this->option('dry-run');

        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('  Fix Profit & Commission');
        $this->info('═══════════════════════════════════════════════════════════');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be saved');
        }
        $this->newLine();

        // Build query
        $query = Invoice::with([
            'agent.branch',
            'invoiceDetails.task.supplier',
            'invoicePartials',
        ]);

        // Only agents with commission (types 2, 3)
        $query->whereHas('agent', fn($q) => $q->whereIn('type_id', [2, 3]));

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

        $settings = AgentCharge::getForAgent($agent->id, $companyId);
        $totalAccountingFee = $this->getTotalAccountingFee($invoice, $companyId);
        $detailCount = $invoice->invoiceDetails->count();
        $commissionRate = (float) ($agent->commission ?? 0.15);
        $transactionId = $this->getOrCreateTransactionId($invoice);

        foreach ($invoice->invoiceDetails as $detail) {
            $this->processDetail(
                $detail,
                $invoice,
                $agent,
                $settings,
                $totalAccountingFee,
                $detailCount,
                $companyId,
                $commissionRate,
                $transactionId,
                $dryRun
            );
        }
    }

    private function getTotalAccountingFee(Invoice $invoice, int $companyId): float
    {
        $total = 0;
        $partials = InvoicePartial::where('invoice_id', $invoice->id)->get();

        foreach ($partials as $partial) {
            if (!$partial->payment_gateway) continue;

            $gateway = strtolower($partial->payment_gateway);
            if ($gateway === 'credit' || $gateway === 'credit balance') continue;

            $amount = (float) $partial->amount;
            if ($amount <= 0) continue;

            $methodId = null;
            if ($partial->payment_method) {
                if (is_numeric($partial->payment_method)) {
                    $methodId = (int) $partial->payment_method;
                } else {
                    $method = PaymentMethod::where('name', $partial->payment_method)
                        ->where('company_id', $companyId)
                        ->first();
                    $methodId = $method?->id;
                }
            }

            $result = ChargeService::calculate($amount, $companyId, $methodId, $partial->payment_gateway);
            $total += $result['accountingFee'] ?? 0;
        }

        return $total;
    }

    private function processDetail(
        InvoiceDetail $detail,
        Invoice $invoice,
        Agent $agent,
        AgentCharge $settings,
        float $totalAccountingFee,
        int $detailCount,
        int $companyId,
        float $commissionRate,
        ?int $transactionId,
        bool $dryRun
    ): void {
        $taskPrice = (float) $detail->task_price;
        $supplierPrice = (float) $detail->supplier_price;
        $markup = (float) ($detail->markup_price ?? ($taskPrice - $supplierPrice));

        $serviceChargePortion = ($detailCount > 0) ? round($totalAccountingFee / $detailCount, 3) : 0;
        $supplierSurcharge = $this->getSupplierSurcharge($detail, $companyId);
        $totalExtraCharge = $serviceChargePortion + $supplierSurcharge;
        $agentDeduction = $settings->calculateAgentChargeDeduction($totalExtraCharge);

        $newProfit = round($markup - $agentDeduction, 3);
        $newCommission = round($newProfit * $commissionRate, 3);

        $oldProfit = (float) ($detail->profit ?? 0);
        $oldCommission = (float) ($detail->commission ?? 0);

        $profitChanged = abs($oldProfit - $newProfit) > 0.001;
        $commissionChanged = abs($oldCommission - $newCommission) > 0.001;
        $hasChanges = $profitChanged || $commissionChanged;

        if ($hasChanges) {
            $this->changes[] = [
                'invoice' => $invoice->invoice_number,
                'detail_id' => $detail->id,
                'task_id' => $detail->task_id,
                'markup' => $markup,
                'service_charge' => $serviceChargePortion,
                'supplier_surcharge' => $supplierSurcharge,
                'total_extra_charge' => $totalExtraCharge,
                'agent_deduction' => $agentDeduction,
                'old_profit' => $oldProfit,
                'new_profit' => $newProfit,
                'old_commission' => $oldCommission,
                'new_commission' => $newCommission,
                'bearer' => $settings->charge_bearer,
                'agent_percentage' => $settings->agent_percentage,
            ];
        }

        if (!$dryRun) {
            try {
                DB::beginTransaction();

                $detail->profit = $newProfit;
                $detail->commission = $newCommission;
                $detail->save();

                if ($hasChanges) {
                    $this->updatedDetails++;
                }

                if ($transactionId) {
                    $this->fixOrCreateCommissionEntries(
                        $detail,
                        $invoice,
                        $agent,
                        $companyId,
                        $transactionId,
                        $newCommission
                    );
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Error processing detail {$detail->id}: {$e->getMessage()}");
                Log::error('FixProfitAndCommission error', [
                    'detail_id' => $detail->id,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            if ($hasChanges) {
                $this->updatedDetails++;
            }
        }
    }

    private function fixOrCreateCommissionEntries(
        InvoiceDetail $detail,
        Invoice $invoice,
        Agent $agent,
        int $companyId,
        int $transactionId,
        float $commission
    ): void {
        $expenseAccount = $this->getExpenseAccount($companyId);
        $liabilityAccount = $this->getLiabilityAccount($companyId);

        if (!$expenseAccount || !$liabilityAccount) {
            Log::warning('Commission accounts not found', ['company_id' => $companyId]);
            return;
        }

        // Handle positive vs negative commission
        // Positive: DEBIT Expense, CREDIT Liability (company owes agent)
        // Negative: CREDIT Expense, DEBIT Liability (agent owes company)
        $isNegative = $commission < 0;
        $absCommission = abs($commission);

        // EXPENSE ENTRY (DEBIT)
        $expenseEntry = JournalEntry::where('invoice_detail_id', $detail->id)
            ->where('account_id', $expenseAccount->id)
            ->first();

        $expenseDebit = $isNegative ? 0 : $absCommission;
        $expenseCredit = $isNegative ? $absCommission : 0;

        if ($expenseEntry) {
            if (abs($expenseEntry->debit - $expenseDebit) > 0.001 || abs($expenseEntry->credit - $expenseCredit) > 0.001) {
                $expenseEntry->debit = $expenseDebit;
                $expenseEntry->credit = $expenseCredit;
                $expenseEntry->amount = $absCommission;
                $expenseEntry->save();
                $this->fixedExpenseEntries++;
            }
        } else {
            JournalEntry::create([
                'transaction_id' => $transactionId,
                'branch_id' => $agent->branch_id,
                'company_id' => $companyId,
                'account_id' => $expenseAccount->id,
                'task_id' => $detail->task_id,
                'agent_id' => $agent->id,
                'invoice_id' => $invoice->id,
                'invoice_detail_id' => $detail->id,
                'transaction_date' => $invoice->invoice_date,
                'description' => 'Agents Commissions for (Expenses): ' . $agent->name,
                'debit' => $expenseDebit,
                'credit' => $expenseCredit,
                'balance' => $expenseAccount->balance ?? 0,
                'name' => $expenseAccount->name,
                'type' => 'receivable',
                'currency' => 'KWD',
                'exchange_rate' => 1.00,
                'amount' => $absCommission,
            ]);
            $this->createdExpenseEntries++;
        }

        // LIABILITY ENTRY (CREDIT)
        $liabilityEntry = JournalEntry::where('invoice_detail_id', $detail->id)
            ->where('account_id', $liabilityAccount->id)
            ->first();

        $liabilityDebit = $isNegative ? $absCommission : 0;
        $liabilityCredit = $isNegative ? 0 : $absCommission;

        if ($liabilityEntry) {
            if (abs($liabilityEntry->debit - $liabilityDebit) > 0.001 || abs($liabilityEntry->credit - $liabilityCredit) > 0.001) {
                $liabilityEntry->debit = $liabilityDebit;
                $liabilityEntry->credit = $liabilityCredit;
                $liabilityEntry->amount = $absCommission;
                $liabilityEntry->save();
                $this->fixedLiabilityEntries++;
            }
        } else {
            JournalEntry::create([
                'transaction_id' => $transactionId,
                'branch_id' => $agent->branch_id,
                'company_id' => $companyId,
                'account_id' => $liabilityAccount->id,
                'task_id' => $detail->task_id,
                'agent_id' => $agent->id,
                'invoice_id' => $invoice->id,
                'invoice_detail_id' => $detail->id,
                'transaction_date' => $invoice->invoice_date,
                'description' => 'Agents Commissions for (Liabilities): ' . $agent->name,
                'debit' => $liabilityDebit,
                'credit' => $liabilityCredit,
                'balance' => $liabilityAccount->balance ?? 0,
                'name' => $liabilityAccount->name,
                'type' => 'payable',
                'currency' => 'KWD',
                'exchange_rate' => 1.00,
                'amount' => $absCommission,
            ]);
            $this->createdLiabilityEntries++;
        }
    }

    private function getSupplierSurcharge(InvoiceDetail $detail, int $companyId): float
    {
        $task = $detail->task;
        if (!$task || !$task->supplier_id) return 0;

        $supplierCompany = SupplierCompany::where('supplier_id', $task->supplier_id)
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->first();

        if (!$supplierCompany) return 0;

        $totalSurcharge = 0;
        $surcharges = SupplierSurcharge::with('references')
            ->where('supplier_company_id', $supplierCompany->id)
            ->get();

        foreach ($surcharges as $surcharge) {
            if ($surcharge->charge_mode === 'task') {
                if ($surcharge->canChargeForStatus($task->status)) {
                    $totalSurcharge += $surcharge->amount;
                }
            } elseif ($surcharge->charge_mode === 'reference') {
                foreach ($surcharge->references as $ref) {
                    if ($task->reference === $ref->reference) {
                        if ($surcharge->charge_behavior === 'single' && $ref->is_charged) continue;
                        $totalSurcharge += $surcharge->amount;
                        break;
                    }
                }
            }
        }

        return (float) $totalSurcharge;
    }

    private function getOrCreateTransactionId(Invoice $invoice): ?int
    {
        $existingEntry = JournalEntry::where('invoice_id', $invoice->id)->first();
        return $existingEntry?->transaction_id;
    }

    private function getExpenseAccount(int $companyId): ?Account
    {
        if (!isset($this->expenseAccounts[$companyId])) {
            $this->expenseAccounts[$companyId] = Account::where('name', 'LIKE', 'Commissions Expense (Agents)%')
                ->where('company_id', $companyId)
                ->first();
        }
        return $this->expenseAccounts[$companyId];
    }

    private function getLiabilityAccount(int $companyId): ?Account
    {
        if (!isset($this->liabilityAccounts[$companyId])) {
            $this->liabilityAccounts[$companyId] = Account::where('name', 'LIKE', 'Commissions (Agents)%')
                ->where('company_id', $companyId)
                ->first();
        }
        return $this->liabilityAccounts[$companyId];
    }

    private function printSummary(bool $dryRun): void
    {
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('  Summary');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info("Invoices processed:        {$this->processedInvoices}");
        $this->info("Invoice details updated:   {$this->updatedDetails}");
        $this->info("COA Expense created/fixed: {$this->createdExpenseEntries}/{$this->fixedExpenseEntries}");
        $this->info("COA Liability created/fixed: {$this->createdLiabilityEntries}/{$this->fixedLiabilityEntries}");

        if (!empty($this->changes)) {
            $totalOldProfit = array_sum(array_column($this->changes, 'old_profit'));
            $totalNewProfit = array_sum(array_column($this->changes, 'new_profit'));
            $totalOldComm = array_sum(array_column($this->changes, 'old_commission'));
            $totalNewComm = array_sum(array_column($this->changes, 'new_commission'));

            $this->newLine();
            $this->info("Profit:     " . number_format($totalOldProfit, 3) . " → " . number_format($totalNewProfit, 3) . " (" . number_format($totalNewProfit - $totalOldProfit, 3) . ")");
            $this->info("Commission: " . number_format($totalOldComm, 3) . " → " . number_format($totalNewComm, 3) . " (" . number_format($totalNewComm - $totalOldComm, 3) . ")");

            if ($dryRun) {
                $this->newLine();
                $this->table(
                    ['Invoice', 'Task', 'Markup', 'Deduct', 'Bearer', 'Old Comm', 'New Comm'],
                    collect(array_slice($this->changes, 0, 20))->map(fn($c) => [
                        $c['invoice'],
                        $c['task_id'],
                        number_format($c['markup'], 2),
                        number_format($c['agent_deduction'], 2),
                        $c['bearer'],
                        number_format($c['old_commission'], 2),
                        number_format($c['new_commission'], 2),
                    ])->toArray()
                );

                if (count($this->changes) > 20) {
                    $this->info('... and ' . (count($this->changes) - 20) . ' more');
                }
                $this->newLine();
                $this->warn('Run without --dry-run to apply changes.');
            }
        }

        Log::info('FixProfitAndCommission completed', [
            'dry_run' => $dryRun,
            'invoices' => $this->processedInvoices,
            'details_updated' => $this->updatedDetails,
        ]);
    }
}
