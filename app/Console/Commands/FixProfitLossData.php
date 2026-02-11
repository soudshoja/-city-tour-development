<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\AgentLoss;
use App\Models\InvoiceDetail;
use App\Models\JournalEntry;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixProfitLossData extends Command
{
    protected $signature = 'fix:invoice-profit-loss {--dry-run}';
    protected $description = 'Backfill profit/loss/provision using SAME structure as addJournalEntry';

    private int $profitEntries = 0;
    private int $lossEntries = 0;
    private int $provisionEntries = 0;
    private int $commissionZeroed = 0;

    private array $changes = [];

    public function handle()
    {
        $dry = $this->option('dry-run');

        $this->info('════════ Fix Profit & Loss JE ════════');

        if ($dry) {
            $this->warn('🔍 DRY RUN — no database writes');
        }

        $details = InvoiceDetail::with(['invoice', 'task.agent.branch'])->get();

        $bar = $this->output->createProgressBar($details->count());
        $bar->start();

        foreach ($details as $d) {

            $invoice = $d->invoice;
            $task    = $d->task;
            $agent   = $task?->agent;

            if (!$invoice || !$task || !$agent) {
                $bar->advance();
                continue;
            }

            $tx = Transaction::where('invoice_id', $invoice->id)->first();
            if (!$tx) {
                $bar->advance();
                continue;
            }

            $companyId = $invoice->company_id ?? $agent->branch?->company_id;
            $profit = (float)$d->profit;
            $markup = (float)$d->markup_price;

            if (!$dry) DB::beginTransaction();

            try {

                /* ========= PROFIT ========= */

                if ($agent->profit_account_id && $profit != 0) {

                    $acc = Account::find($agent->profit_account_id);

                    if ($acc && !JournalEntry::where('invoice_detail_id', $d->id)
                        ->where('account_id', $acc->id)->exists()) {

                        $this->profitEntries++;

                        $this->changes[] = [
                            'type' => 'PROFIT',
                            'detail' => $d->id,
                            'amount' => $profit,
                            'account' => $acc->name,
                        ];

                        if (!$dry) {
                            JournalEntry::create([
                                'transaction_id' => $tx->id,
                                'branch_id' => $agent->branch_id,
                                'company_id' => $companyId,
                                'account_id' => $acc->id,
                                'task_id' => $task->id,
                                'agent_id' => $agent->id,
                                'invoice_id' => $invoice->id,
                                'invoice_detail_id' => $d->id,
                                'transaction_date' => $invoice->invoice_date,
                                'description' => 'Profit for Agent: ' . $agent->name,
                                'debit'  => $profit < 0 ? abs($profit) : 0,
                                'credit' => $profit > 0 ? abs($profit) : 0,
                                'balance' => 0,
                                'name' => $acc->name,
                                'type' => 'receivable',
                                'currency' => $task->currency ?? 'KWD',
                                'exchange_rate' => $task->exchange_rate ?? 1.00,
                                'amount' => $profit,
                            ]);
                        }
                    }
                }

                /* ========= LOSS ========= */

                if ($markup < 0) {

                    $lossAmount = abs($markup);
                    $dist = AgentLoss::getForAgent($agent->id, $companyId)
                        ->calculateLossDistribution($lossAmount);

                    // agent loss
                    if ($dist['agent_loss'] > 0 && $agent->loss_account_id) {

                        $acc = Account::find($agent->loss_account_id);

                        if ($acc) {
                            $this->lossEntries++;

                            $this->changes[] = [
                                'type' => 'AGENT LOSS',
                                'detail' => $d->id,
                                'amount' => $dist['agent_loss'],
                                'account' => $acc->name,
                            ];

                            if (!$dry) {
                                JournalEntry::create([
                                    'transaction_id' => $tx->id,
                                    'branch_id' => $agent->branch_id,
                                    'company_id' => $companyId,
                                    'account_id' => $acc->id,
                                    'task_id' => $task->id,
                                    'agent_id' => $agent->id,
                                    'invoice_id' => $invoice->id,
                                    'invoice_detail_id' => $d->id,
                                    'transaction_date' => $invoice->invoice_date,
                                    'description' => 'Agent Loss: ' . $agent->name,
                                    'debit' => $dist['agent_loss'],
                                    'credit' => 0,
                                    'balance' => 0,
                                    'name' => $acc->name,
                                    'type' => 'receivable',
                                    'currency' => $task->currency ?? 'KWD',
                                    'exchange_rate' => $task->exchange_rate ?? 1.00,
                                    'amount' => $dist['agent_loss'],
                                ]);
                            }
                        }
                    }

                    // provision
                    $prov = Account::where('company_id', $companyId)
                        ->where('name', 'like', '%Loss Provision%')
                        ->where('is_group', 0)
                        ->first();

                    if ($prov) {

                        $this->provisionEntries++;

                        $this->changes[] = [
                            'type' => 'LOSS PROVISION',
                            'detail' => $d->id,
                            'amount' => $lossAmount,
                            'account' => $prov->name,
                        ];

                        if (!$dry) {
                            JournalEntry::create([
                                'transaction_id' => $tx->id,
                                'branch_id' => $agent->branch_id,
                                'company_id' => $companyId,
                                'account_id' => $prov->id,
                                'task_id' => $task->id,
                                'agent_id' => $agent->id,
                                'invoice_id' => $invoice->id,
                                'invoice_detail_id' => $d->id,
                                'transaction_date' => $invoice->invoice_date,
                                'description' => 'Loss Provision | Task: ' . ($task->reference ?? ''),
                                'debit' => 0,
                                'credit' => $lossAmount,
                                'balance' => 0,
                                'name' => $prov->name,
                                'type' => 'payable',
                                'currency' => $task->currency ?? 'KWD',
                                'exchange_rate' => $task->exchange_rate ?? 1.00,
                                'amount' => $lossAmount,
                            ]);
                        }
                    }
                }

                /* ========= COMMISSION ZERO ========= */

                if ($profit <= 0 && $d->commission != 0) {

                    $this->commissionZeroed++;

                    $this->changes[] = [
                        'type' => 'ZERO COMMISSION',
                        'detail' => $d->id,
                        'amount' => 0,
                        'account' => 'Commission',
                    ];

                    if (!$dry) {
                        $d->commission = 0;
                        $d->save();
                    }
                }

                if (!$dry) DB::commit();
            } catch (\Throwable $e) {
                if (!$dry) DB::rollBack();
                $this->error("Detail {$d->id} failed: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->printSummary($dry);
    }

    private function printSummary(bool $dry): void
    {
        $this->info("════════ Summary ════════");
        $this->info("Profit entries:   {$this->profitEntries}");
        $this->info("Loss entries:     {$this->lossEntries}");
        $this->info("Provision CR:     {$this->provisionEntries}");
        $this->info("Commission zero:  {$this->commissionZeroed}");

        if ($dry && $this->changes) {
            $this->newLine();
            $this->table(
                ['Type', 'Detail', 'Amount', 'Account'],
                array_slice($this->changes, 0, 25)
            );

            if (count($this->changes) > 25) {
                $this->warn('... more not shown');
            }

            $this->warn('Run without --dry-run to apply.');
        }
    }
}
