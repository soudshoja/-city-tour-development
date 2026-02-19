<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentCharge;
use App\Models\AgentLoss;
use App\Models\Charge;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\JournalEntry;
use App\Services\ChargeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixInvoiceCoa extends Command
{
    protected $signature = 'fix:invoice-coa
                            {--company= : Specific company ID}
                            {--invoice= : Specific invoice ID}
                            {--agent= : Specific agent ID}
                            {--dry-run : Preview changes without saving}
                            {--from-date= : Start date (Y-m-d)}
                            {--to-date= : End date (Y-m-d)}
                            {--force : Skip confirmation}';

    protected $description = 'Fix profit, commission and all COA journal entries for invoices';

    private int $processedInvoices = 0;
    private int $updatedDetails = 0;
    private int $createdEntries = 0;
    private int $fixedEntries = 0;
    private array $changes = [];
    private array $accountCache = [];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('Fix Invoice COA (Profit, Commission & Journal Entries)');
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be saved');
        }
        $this->newLine();

        $query = Invoice::with([
            'agent.branch',
            'invoiceDetails.task.supplier',
            'invoicePartials.paymentMethod',
            'invoicePartials.charge',
        ]);

        if ($id = $this->option('invoice')) $query->where('id', $id);
        if ($cid = $this->option('company')) {
            $query->whereHas('agent.branch', fn($q) => $q->where('company_id', $cid));
        }
        if ($aid = $this->option('agent')) $query->where('agent_id', $aid);
        if ($from = $this->option('from-date')) $query->where('invoice_date', '>=', $from);
        if ($to = $this->option('to-date')) $query->where('invoice_date', '<=', $to);

        $invoices = $query->get();
        $this->info("Found {$invoices->count()} invoices to process");

        if ($invoices->isEmpty()) return 0;
        if (!$dryRun && !$this->option('force') && !$this->confirm('Proceed?')) return 0;

        $bar = $this->output->createProgressBar($invoices->count());
        $bar->start();

        foreach ($invoices as $invoice) {
            try {
                if (!$dryRun) DB::beginTransaction();
                $this->processInvoice($invoice, $dryRun);
                if (!$dryRun) DB::commit();
            } catch (\Exception $e) {
                if (!$dryRun) DB::rollBack();
                Log::error("FixInvoiceCoa error on invoice {$invoice->id}: {$e->getMessage()}");
                $this->error("Error on invoice {$invoice->id}: {$e->getMessage()}");
            }
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

        $transactionId = JournalEntry::where('invoice_id', $invoice->id)->value('transaction_id');
        if (!$transactionId) return;

        $chargeSettings = AgentCharge::getForAgent($agent->id, $companyId);
        $lossSettings = AgentLoss::getForAgent($agent->id, $companyId);

        // Invoice-level calculations
        $totalAccountingFee = $this->getTotalAccountingFee($invoice);
        $gatewayProfitData = $this->getGatewayProfitData($invoice, $companyId);

        $chargeRecord = $invoice->invoicePartials
            ->filter(fn($p) => $p->payment_gateway && $p->payment_gateway !== 'Credit')
            ->map(fn($p) => $p->charge ?: Charge::where('name', $p->payment_gateway)->where('company_id', $companyId)->first())
            ->filter()
            ->first();
        $clientPaid = $chargeRecord?->paid_by === 'Client';

        $taskCount = $invoice->invoiceDetails->count();
        $feePerTask = $taskCount > 0 ? round($totalAccountingFee / $taskCount, 3) : 0;
        $markupPerTask = $taskCount > 0 ? round($gatewayProfitData['markup_profit'] / $taskCount, 3) : 0;
        $roundingPerTask = $taskCount > 0 ? round($gatewayProfitData['rounding_profit'] / $taskCount, 3) : 0;
        $gwProfitPerTask = $markupPerTask + $roundingPerTask;
        $agentDeduction = $chargeSettings->calculateAgentChargeDeduction($feePerTask);

        foreach ($invoice->invoiceDetails as $detail) {
            $this->processDetail(
                $detail,
                $invoice,
                $agent,
                $companyId,
                $transactionId,
                $chargeSettings,
                $lossSettings,
                $clientPaid,
                $feePerTask,
                $agentDeduction,
                $gwProfitPerTask,
                $dryRun
            );
        }
    }

    private function processDetail(
        InvoiceDetail $detail,
        Invoice $invoice,
        Agent $agent,
        int $companyId,
        int $transactionId,
        AgentCharge $chargeSettings,
        AgentLoss $lossSettings,
        bool $clientPaid,
        float $feePerTask,
        float $agentDeduction,
        float $gwProfitPerTask,
        bool $dryRun
    ): void {
        $task = $detail->task;
        if (!$task) return;

        $selling = (float) $detail->task_price;
        $supplier = (float) $detail->supplier_price;
        $margin = $selling - $supplier;

        // Company pays + Company bears → Margin
        // Company pays + Agent bears   → Margin - (API + Extra)
        // Client pays  + Company bears → Margin + (API + Extra)
        // Client pays  + Agent bears   → Margin + (API + Extra) - (API + Extra) = Margin
        $profit = $clientPaid
            ? round(($margin + $feePerTask) - $agentDeduction, 3)
            : round($margin - $agentDeduction, 3);

        // Commission (only for agent types 2,3,4 with positive profit)
        $commission = 0;
        if (in_array($agent->type_id, [2, 3, 4]) && $profit > 0) {
            $commission = round($profit * (float) ($agent->commission ?? 0.15), 3);
        }

        // Update invoice detail if changed
        $oldProfit = (float) ($detail->profit ?? 0);
        $oldCommission = (float) ($detail->commission ?? 0);

        if (abs($oldProfit - $profit) > 0.001 || abs($oldCommission - $commission) > 0.001) {
            $this->changes[] = [
                'invoice' => $invoice->invoice_number,
                'task' => $task->reference ?? $detail->task_id,
                'margin' => $margin,
                'client_paid' => $clientPaid ? 'Y' : 'N',
                'old_profit' => $oldProfit,
                'new_profit' => $profit,
                'old_commission' => $oldCommission,
                'new_commission' => $commission,
            ];

            if (!$dryRun) {
                $detail->profit = $profit;
                $detail->commission = $commission;
                $detail->save();
            }
            $this->updatedDetails++;
        }

        // Fix/create journal entries
        $base = [
            'transaction_id' => $transactionId,
            'branch_id' => $agent->branch_id ?? null,
            'company_id' => $companyId,
            'task_id' => $detail->task_id,
            'agent_id' => $agent->id,
            'invoice_id' => $invoice->id,
            'invoice_detail_id' => $detail->id,
            'transaction_date' => $invoice->invoice_date,
            'currency' => $task->currency ?? 'KWD',
            'exchange_rate' => $task->exchange_rate ?? 1.00,
        ];

        // Always call fix methods so stale entries get zeroed out
        // e.g. old profit entries when profit is now negative, old commission when commission is now 0
        $this->fixGatewayProfitEntries($detail, $task, $invoice, $companyId, $gwProfitPerTask, $base, $dryRun);
        $this->fixProfitEntries($detail, $agent, $companyId, max($profit, 0), $base, $dryRun);
        $this->fixCommissionEntries($detail, $agent, $companyId, $commission, $base, $dryRun);

        // Loss handling
        $isSupplierLoss = $margin < 0;
        $isFeeLoss = ($profit < 0) && ($margin >= 0);
        $isBothLosses = ($margin < 0) && ($profit < $margin);

        if ($isSupplierLoss) {
            $this->fixSupplierLossEntries($detail, $task, $agent, $companyId, $lossSettings, abs($margin), $base, $dryRun);
        }

        if ($isFeeLoss || $isBothLosses) {
            $feeLoss = $isBothLosses ? abs($profit - $margin) : abs($profit);
            $this->fixFeeLossEntries($detail, $task, $agent, $companyId, $chargeSettings, $feeLoss, $base, $dryRun);
        }
    }

    private function fixGatewayProfitEntries($detail, $task, $invoice, $companyId, $amount, $base, $dryRun): void
    {
        // Get specific gateway asset account from charge record (e.g. Tap, MyFatoorah)
        $chargeRecord = null;
        foreach ($invoice->invoicePartials as $partial) {
            if (!$partial->payment_gateway || $partial->payment_gateway === 'Credit') continue;
            if ($partial->charge_id) {
                $charge = $partial->charge ?? Charge::find($partial->charge_id);
                if ($charge && $charge->acc_fee_bank_id) { $chargeRecord = $charge; break; }
            }
            $charge = Charge::where('name', $partial->payment_gateway)->where('company_id', $companyId)->first();
            if ($charge && $charge->acc_fee_bank_id) { $chargeRecord = $charge; break; }
        }

        $asset = $chargeRecord ? Account::find($chargeRecord->acc_fee_bank_id) : null;
        $income = $this->getAccount('Gateway Fee Recovery', $companyId);
        if (!$asset || !$income) return;

        $desc = 'Gateway profit on ' . $task->reference;

        $this->fixOrCreate($detail->id, $asset->id, $desc, array_merge($base, [
            'account_id' => $asset->id,
            'description' => $desc,
            'debit' => $amount,
            'credit' => 0,
            'name' => $asset->name,
            'type' => 'asset',
            'amount' => $amount,
        ]), $dryRun);

        $this->fixOrCreate($detail->id, $income->id, $desc, array_merge($base, [
            'account_id' => $income->id,
            'description' => $desc,
            'debit' => 0,
            'credit' => $amount,
            'name' => $income->name,
            'type' => 'income',
            'amount' => $amount,
        ]), $dryRun);
    }

    private function fixProfitEntries($detail, $agent, $companyId, $profit, $base, $dryRun): void
    {
        $salaries = $this->getAccount('Agent Salaries', $companyId);
        if ($salaries) {
            $desc = 'Agent profit share: ' . $agent->name;
            $this->fixOrCreate($detail->id, $salaries->id, $desc, array_merge($base, [
                'account_id' => $salaries->id,
                'description' => $desc,
                'debit' => $profit,
                'credit' => 0,
                'name' => $salaries->name,
                'type' => 'expense',
                'amount' => $profit,
            ]), $dryRun);
        }

        if ($agent->profit_account_id) {
            $profitAccount = Account::find($agent->profit_account_id);
            if ($profitAccount) {
                $desc = 'Profit payable to agent: ' . $agent->name;
                $this->fixOrCreate($detail->id, $agent->profit_account_id, $desc, array_merge($base, [
                    'account_id' => $agent->profit_account_id,
                    'description' => $desc,
                    'debit' => 0,
                    'credit' => $profit,
                    'name' => $profitAccount->name,
                    'type' => 'payable',
                    'amount' => $profit,
                ]), $dryRun);
            }
        }
    }

    private function fixCommissionEntries($detail, $agent, $companyId, $commission, $base, $dryRun): void
    {
        $expense = $this->getAccount('Commissions Expense (Agents)', $companyId);
        $liability = $this->getAccount('Commissions (Agents)', $companyId);

        if ($expense) {
            $desc = 'Agents Commissions for (Expenses): ' . $agent->name;
            $this->fixOrCreate($detail->id, $expense->id, $desc, array_merge($base, [
                'account_id' => $expense->id,
                'description' => $desc,
                'debit' => $commission,
                'credit' => 0,
                'name' => $expense->name,
                'type' => 'expense',
                'amount' => $commission,
            ]), $dryRun);
        }

        if ($liability) {
            $desc = 'Agents Commissions for (Liabilities): ' . $agent->name;
            $this->fixOrCreate($detail->id, $liability->id, $desc, array_merge($base, [
                'account_id' => $liability->id,
                'description' => $desc,
                'debit' => 0,
                'credit' => $commission,
                'name' => $liability->name,
                'type' => 'payable',
                'amount' => $commission,
            ]), $dryRun);
        }
    }

    private function fixSupplierLossEntries($detail, $task, $agent, $companyId, $lossSettings, $lossAmount, $base, $dryRun): void
    {
        $distribution = $lossSettings->calculateLossDistribution($lossAmount);

        // Agent portion
        if ($distribution['agent_loss'] > 0 && $agent->loss_account_id) {
            $agentLossName = optional(Account::find($agent->loss_account_id))->name ?? 'Agent Loss Receivable';

            $this->fixOrCreate(
                $detail->id,
                $agent->loss_account_id,
                'Supplier loss charged to agent: ' . $agent->name,
                array_merge($base, [
                    'account_id' => $agent->loss_account_id,
                    'description' => 'Supplier loss charged to agent: ' . $agent->name,
                    'debit' => $distribution['agent_loss'],
                    'credit' => 0,
                    'name' => $agentLossName,
                    'type' => 'receivable',
                    'amount' => $distribution['agent_loss'],
                ]),
                $dryRun
            );

            $lossRecovery = $this->getAccount('Loss Recovery Income', $companyId);
            if ($lossRecovery) {
                $this->fixOrCreate(
                    $detail->id,
                    $lossRecovery->id,
                    'Supplier loss recovery from agent: ' . $agent->name,
                    array_merge($base, [
                        'account_id' => $lossRecovery->id,
                        'description' => 'Supplier loss recovery from agent: ' . $agent->name,
                        'debit' => 0,
                        'credit' => $distribution['agent_loss'],
                        'name' => 'Loss Recovery Income',
                        'type' => 'income',
                        'amount' => $distribution['agent_loss'],
                    ]),
                    $dryRun
                );
            }
        }

        // Company portion
        if ($distribution['company_loss'] > 0) {
            $companyLossAcct = $this->getAccount('Company Loss on Sales', $companyId);
            $expenses = Account::where('name', 'like', '%Expenses%')->where('company_id', $companyId)->first();
            $costAccount = ($task->supplier && $expenses) ? Account::where('name', $task->supplier->name)
                ->where('company_id', $companyId)
                ->where('root_id', $expenses->id)
                ->first() : null;

            if ($companyLossAcct) {
                $this->fixOrCreate(
                    $detail->id,
                    $companyLossAcct->id,
                    'Company portion of supplier loss on ' . $task->reference,
                    array_merge($base, [
                        'account_id' => $companyLossAcct->id,
                        'description' => 'Company portion of supplier loss on ' . $task->reference,
                        'debit' => $distribution['company_loss'],
                        'credit' => 0,
                        'name' => $companyLossAcct->name,
                        'type' => 'expense',
                        'amount' => $distribution['company_loss'],
                    ]),
                    $dryRun
                );
            }

            if ($costAccount) {
                $this->fixOrCreate(
                    $detail->id,
                    $costAccount->id,
                    'Transfer supplier loss to loss account',
                    array_merge($base, [
                        'account_id' => $costAccount->id,
                        'description' => 'Transfer supplier loss to loss account',
                        'debit' => 0,
                        'credit' => $distribution['company_loss'],
                        'name' => $costAccount->name,
                        'type' => 'expense',
                        'amount' => $distribution['company_loss'],
                    ]),
                    $dryRun
                );
            }
        }
    }

    private function fixFeeLossEntries($detail, $task, $agent, $companyId, $chargeSettings, $feeLoss, $base, $dryRun): void
    {
        $agentPct = $chargeSettings->getAgentPercentageToApply();
        $agentFeeLoss = round($feeLoss * ($agentPct / 100), 3);
        $companyFeeLoss = round($feeLoss * ((100 - $agentPct) / 100), 3);

        // Agent portion
        if ($agentFeeLoss > 0 && $agent->loss_account_id) {
            $agentLossName = optional(Account::find($agent->loss_account_id))->name ?? 'Agent Loss Receivable';

            $this->fixOrCreate(
                $detail->id,
                $agent->loss_account_id,
                'Fee loss charged to agent: ' . $agent->name,
                array_merge($base, [
                    'account_id' => $agent->loss_account_id,
                    'description' => 'Fee loss charged to agent: ' . $agent->name,
                    'debit' => $agentFeeLoss,
                    'credit' => 0,
                    'name' => $agentLossName,
                    'type' => 'receivable',
                    'amount' => $agentFeeLoss,
                ]),
                $dryRun
            );

            $lossRecovery = $this->getAccount('Loss Recovery Income', $companyId);
            if ($lossRecovery) {
                $this->fixOrCreate(
                    $detail->id,
                    $lossRecovery->id,
                    'Fee loss recovery from agent: ' . $agent->name,
                    array_merge($base, [
                        'account_id' => $lossRecovery->id,
                        'description' => 'Fee loss recovery from agent: ' . $agent->name,
                        'debit' => 0,
                        'credit' => $agentFeeLoss,
                        'name' => 'Loss Recovery Income',
                        'type' => 'income',
                        'amount' => $agentFeeLoss,
                    ]),
                    $dryRun
                );
            }
        }

        // Company portion
        if ($companyFeeLoss > 0) {
            $companyLossAcct = $this->getAccount('Company Loss on Sales', $companyId);
            $gwFeeExpense = $this->getAccount('Fee Loss Provision', $companyId);

            if ($companyLossAcct) {
                $this->fixOrCreate(
                    $detail->id,
                    $companyLossAcct->id,
                    'Company portion of fee loss on ' . $task->reference,
                    array_merge($base, [
                        'account_id' => $companyLossAcct->id,
                        'description' => 'Company portion of fee loss on ' . $task->reference,
                        'debit' => $companyFeeLoss,
                        'credit' => 0,
                        'name' => $companyLossAcct->name,
                        'type' => 'expense',
                        'amount' => $companyFeeLoss,
                    ]),
                    $dryRun
                );
            }

            if ($gwFeeExpense) {
                $this->fixOrCreate(
                    $detail->id,
                    $gwFeeExpense->id,
                    'Fee loss provision for ' . $task->reference,
                    array_merge($base, [
                        'account_id' => $gwFeeExpense->id,
                        'description' => 'Fee loss provision for ' . $task->reference,
                        'debit' => 0,
                        'credit' => $companyFeeLoss,
                        'name' => $gwFeeExpense->name,
                        'type' => 'expense',
                        'amount' => $companyFeeLoss,
                    ]),
                    $dryRun
                );
            }
        }
    }

    private function fixOrCreate(int $detailId, int $accountId, string $description, array $data, bool $dryRun): void
    {
        // Search by detail + account. If only one entry exists, use it regardless of
        // If multiple exist (supplier loss + fee loss on same account), use description to disambiguate
        $entries = JournalEntry::where('invoice_detail_id', $detailId)
            ->where('account_id', $accountId)
            ->get();

        $existing = $entries->count() === 1 ? $entries->first() : $entries->firstWhere('description', $description);

        $amount = max($data['debit'], $data['credit']);

        if ($existing) {
            $amountChanged = abs(max($existing->debit, $existing->credit) - $amount) > 0.001;
            $descChanged = $existing->description !== $description;

            if ($amountChanged || $descChanged) {
                if (!$dryRun) {
                    $existing->update([
                        'debit' => $data['debit'],
                        'credit' => $data['credit'],
                        'amount' => $amount,
                        'description' => $description,
                    ]);
                }
                $this->fixedEntries++;
            }
        } elseif ($amount > 0) {
            if (!$dryRun) {
                JournalEntry::create(array_merge($data, ['balance' => 0]));
            }
            $this->createdEntries++;
        }
    }

    private function getTotalAccountingFee(Invoice $invoice): float
    {
        $partialFees = $invoice->invoicePartials
            ->filter(fn($p) => $p->payment_gateway && !in_array($p->payment_gateway, ['Credit', 'Cash']))
            ->sum('gateway_fee');

        $creditFees = (float) Credit::where('invoice_id', $invoice->id)
            ->where('amount', '<', 0)
            ->sum('gateway_fee');

        return round((float) $partialFees + abs($creditFees), 3);
    }

    private function getGatewayProfitData(Invoice $invoice, int $companyId): array
    {
        $markupProfit = 0;
        $roundingProfit = 0;

        foreach ($invoice->invoicePartials as $partial) {
            if (!$partial->payment_gateway || in_array($partial->payment_gateway, ['Credit', 'Cash'])) continue;

            if (isset($partial->markup_profit)) {
                $markupProfit += (float) $partial->markup_profit;
                $roundingProfit += (float) ($partial->rounding_profit ?? 0);
            } elseif ($partial->payment_method) {
                $calc = ChargeService::calculate(
                    $partial->amount,
                    $companyId,
                    $partial->payment_method,
                    $partial->payment_gateway
                );
                if ($calc['paid_by'] === 'Client') {
                    $markupProfit += $calc['markup_profit'] ?? 0;
                    $roundingProfit += $calc['rounding_profit'] ?? 0;
                }
            }
        }

        $credits = Credit::where('invoice_id', $invoice->id)->where('amount', '<', 0)->get();
        foreach ($credits as $credit) {
            $markupProfit += (float) ($credit->markup_profit ?? 0);
            $roundingProfit += (float) ($credit->rounding_profit ?? 0);
        }

        return [
            'markup_profit' => round($markupProfit, 3),
            'rounding_profit' => round($roundingProfit, 3),
        ];
    }

    private function getAccount(string $name, int $companyId, ?string $code = null): ?Account
    {
        $key = "{$name}:{$companyId}:{$code}";
        if (!isset($this->accountCache[$key])) {
            $query = Account::where('name', 'LIKE', $name . '%')->where('company_id', $companyId);
            if ($code) $query->where('code', $code);
            $this->accountCache[$key] = $query->first();
        }
        return $this->accountCache[$key];
    }

    private function printSummary(bool $dryRun): void
    {
        $this->info('═══════════════════════════════════════════════════');
        $this->info("Invoices processed:      {$this->processedInvoices}");
        $this->info("Details updated:         {$this->updatedDetails}");
        $this->info("Journal entries created: {$this->createdEntries}");
        $this->info("Journal entries fixed:   {$this->fixedEntries}");

        if (!empty($this->changes)) {
            $this->newLine();
            $this->table(
                ['Invoice', 'Task', 'Margin', 'Paid', 'Old Profit', 'New Profit', 'Old Comm', 'New Comm'],
                collect(array_slice($this->changes, 0, 30))->map(fn($c) => [
                    $c['invoice'],
                    $c['task'],
                    number_format($c['margin'], 3),
                    $c['client_paid'],
                    number_format($c['old_profit'], 3),
                    number_format($c['new_profit'], 3),
                    number_format($c['old_commission'], 3),
                    number_format($c['new_commission'], 3),
                ])->toArray()
            );

            if (count($this->changes) > 30) {
                $this->info('... and ' . (count($this->changes) - 30) . ' more');
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('Run without --dry-run to apply changes.');
        }

        Log::info('FixInvoiceCoa completed', [
            'dry_run' => $dryRun,
            'invoices' => $this->processedInvoices,
            'details_updated' => $this->updatedDetails,
            'entries_created' => $this->createdEntries,
            'entries_fixed' => $this->fixedEntries,
        ]);
    }
}
