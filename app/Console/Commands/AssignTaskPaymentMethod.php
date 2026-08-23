<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Task;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssignTaskPaymentMethod extends Command
{
    protected $signature = 'tasks:assign-payment-method
                            {--issued_by= : GDS office code, e.g. KWIKT2843}
                            {--account= : Account name (resolved by name + task.company_id), e.g. "Como Travel & Tourism"}
                            {--statuses=issued,reissued,refund : Comma-separated task statuses to include}
                            {--invoiced=1 : 1 to include only tasks with an invoice_details row, 0 to include all}
                            {--limit= : Optional safety cap on task count}
                            {--dry-run : Print what would happen, do not write}';

    protected $description = 'Bulk-assign payment_method_account_id on tasks by issued_by code, posting the matching journal entry on the chosen Account ledger (mirrors the UI updateJournalPaymentMethod behavior).';

    public function handle(): int
    {
        $issuedBy = (string) $this->option('issued_by');
        $accountName = (string) $this->option('account');
        $statuses = array_filter(array_map('trim', explode(',', (string) $this->option('statuses'))));
        $invoicedOnly = (int) $this->option('invoiced') === 1;
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $dryRun = (bool) $this->option('dry-run');

        if (!$issuedBy || !$accountName) {
            $this->error('Both --issued_by and --account are required.');
            return self::FAILURE;
        }

        $this->info("[" . ($dryRun ? 'DRY-RUN' : 'EXECUTE') . "] issued_by={$issuedBy} | account={$accountName} | statuses=" . implode(',', $statuses) . " | invoicedOnly=" . ($invoicedOnly ? 'yes' : 'no') . ($limit ? " | limit={$limit}" : ''));

        $query = Task::query()
            ->where('issued_by', $issuedBy)
            ->whereNull('payment_method_account_id')
            ->whereIn('status', $statuses);

        if ($invoicedOnly) {
            // Refund tasks don't have their own invoice_details and `refund_details.task_id`
            // points to the ORIGINAL (issued) task — not the refund task row. So for refund
            // tasks we look up the original by same reference and check if THAT has a
            // completed refunds record.
            $query->where(function ($q) {
                $q->where(function ($q2) {
                    // Refund: require an original task (same reference) with a completed refunds row
                    $q2->where('status', 'refund')
                       ->whereExists(function ($sub) {
                           $sub->select(DB::raw(1))
                               ->from('refund_details')
                               ->join('refunds', 'refunds.id', '=', 'refund_details.refund_id')
                               ->join('tasks as any_t', 'any_t.id', '=', 'refund_details.task_id')
                               ->whereColumn('any_t.reference', 'tasks.reference')
                               ->whereIn('refunds.status', ['processed', 'completed']);
                       });
                })->orWhere(function ($q2) {
                    // Non-refund: require an invoice_details row
                    $q2->where('status', '!=', 'refund')
                       ->whereExists(function ($sub) {
                           $sub->select(DB::raw(1))
                               ->from('invoice_details')
                               ->whereColumn('invoice_details.task_id', 'tasks.id');
                       });
                });
            });
        }

        if ($limit) {
            $query->limit($limit);
        }

        $tasks = $query->orderBy('id')->get();

        if ($tasks->isEmpty()) {
            $this->warn('No matching tasks found.');
            return self::SUCCESS;
        }

        $this->info("Found {$tasks->count()} task(s), total KWD " . number_format($tasks->sum('total'), 3));

        // Resolve account per company_id (caching to avoid repeat lookups)
        $accountCache = [];
        $resolveAccount = function (int $companyId) use (&$accountCache, $accountName) {
            if (!isset($accountCache[$companyId])) {
                $accountCache[$companyId] = Account::where('name', $accountName)
                    ->where('company_id', $companyId)
                    ->first();
            }
            return $accountCache[$companyId];
        };

        // Find Creditors parent per company (for the reversal logic)
        $creditorsCache = [];
        $resolveCreditors = function (int $companyId) use (&$creditorsCache) {
            if (!isset($creditorsCache[$companyId])) {
                $liabilities = Account::where('name', 'like', '%Liabilities%')
                    ->where('company_id', $companyId)
                    ->first();
                if (!$liabilities) {
                    return null;
                }
                $creditorsCache[$companyId] = Account::where('name', 'Creditors')
                    ->where('company_id', $companyId)
                    ->where('root_id', $liabilities->id)
                    ->first();
            }
            return $creditorsCache[$companyId];
        };

        $okCount = 0;
        $skipCount = 0;
        $reversedJeTotal = 0;
        $newJeTotal = 0;

        foreach ($tasks as $task) {
            $account = $resolveAccount((int) $task->company_id);
            if (!$account) {
                $this->warn("  task {$task->id} ({$task->reference}): account '{$accountName}' not found for company_id={$task->company_id} — skip");
                $skipCount++;
                continue;
            }

            $creditors = $resolveCreditors((int) $task->company_id);

            try {
                if (!$dryRun) {
                    DB::beginTransaction();
                }

                $reversed = 0;
                $postedAmount = 0;
                $txnDate = $task->supplier_pay_date ?? $task->issued_date ?? $task->created_at;

                if ($task->status === 'refund') {
                    // Refund task: redirect the "Supplier refunds us" DR leg from
                    // the auto-created GDS-office account onto the target account.
                    $supplierRefundJes = JournalEntry::where('task_id', $task->id)
                        ->where('description', 'like', 'Refund Task - Supplier refunds us%')
                        ->where('account_id', '!=', $account->id)
                        ->get();

                    foreach ($supplierRefundJes as $oldJe) {
                        // Skip if already reversed
                        $alreadyReversed = JournalEntry::where('task_id', $task->id)
                            ->where('account_id', $oldJe->account_id)
                            ->where('description', 'like', 'Reversed: %')
                            ->where('description', 'like', '%' . $oldJe->description . '%')
                            ->exists();
                        if ($alreadyReversed) {
                            continue;
                        }

                        $amount = (float) max($oldJe->debit, $oldJe->credit);

                        if (!$dryRun) {
                            // Reverse on old (wrong) account
                            $rev = $oldJe->replicate();
                            $rev->description = 'Reversed: ' . $oldJe->description;
                            $rev->debit = $oldJe->credit;
                            $rev->credit = $oldJe->debit;
                            $rev->balance = -$oldJe->balance;
                            $rev->save();

                            // Re-post on target account, same DR/CR shape
                            $newJe = $oldJe->replicate();
                            $newJe->account_id = $account->id;
                            $newJe->name = $account->name;
                            $newJe->description = 'Refund Task - Supplier refunds us (Liabilities): ' . $account->name;
                            $newJe->save();
                        }

                        $reversed++;
                        $reversedJeTotal += $amount;
                        $postedAmount += $amount;
                    }

                    if ($reversed > 0 && !$dryRun) {
                        $task->update(['payment_method_account_id' => $account->id]);
                        DB::commit();
                    } elseif ($reversed === 0 && !$dryRun) {
                        // No JE to flip — just mark the task so the UI is consistent
                        $task->update(['payment_method_account_id' => $account->id]);
                        DB::commit();
                    }

                    $newJeTotal += $postedAmount;
                    $okCount++;
                    if ($okCount % 50 === 0 || $okCount === 1) {
                        $this->line("  [{$okCount}/{$tasks->count()}] refund task {$task->id} ({$task->reference}) — redirected {$reversed} JE, " . ($dryRun ? 'would post' : 'posted') . ' DR KWD ' . number_format($postedAmount, 3) . ' on ' . $account->name);
                    }
                } else {
                    // issued / reissued / other: reverse any existing JE on this task that hit a child of Creditors,
                    // then post a new credit on the target account for task.total.
                    if ($creditors) {
                        $existingJes = JournalEntry::where('task_id', $task->id)
                            ->whereHas('account', function ($q) use ($creditors) {
                                $q->where('parent_id', $creditors->id);
                            })
                            ->get();

                        foreach ($existingJes as $je) {
                            $alreadyReversed = JournalEntry::where('task_id', $task->id)
                                ->where('account_id', $je->account_id)
                                ->where('description', 'like', 'Reversed: %')
                                ->where('description', 'like', '%' . $je->description . '%')
                                ->exists();
                            if ($alreadyReversed) {
                                continue;
                            }
                            $totalDebit = JournalEntry::where('task_id', $task->id)
                                ->where('account_id', $je->account_id)
                                ->sum('debit');
                            $totalCredit = JournalEntry::where('task_id', $task->id)
                                ->where('account_id', $je->account_id)
                                ->sum('credit');
                            if (abs($totalDebit - $totalCredit) < 0.0001) {
                                continue;
                            }

                            if (!$dryRun) {
                                $rev = $je->replicate();
                                $rev->description = 'Reversed: ' . $je->description;
                                $rev->debit = $je->credit;
                                $rev->credit = $je->debit;
                                $rev->balance = -$je->balance;
                                $rev->save();
                            }
                            $reversed++;
                            $reversedJeTotal += (float) max($je->debit, $je->credit);
                        }
                    }

                    if (!$dryRun) {
                        $transaction = Transaction::create([
                            'branch_id' => $task->agent->branch_id ?? null,
                            'company_id' => $task->company_id,
                            'entity_id' => $task->company_id,
                            'entity_type' => 'company',
                            'transaction_type' => 'credit',
                            'amount' => $task->total,
                            'name' => $account->name,
                            'description' => 'Update payment account for: ' . $task->reference,
                            'reference_type' => 'Payment',
                            'transaction_date' => $txnDate,
                        ]);

                        JournalEntry::create([
                            'transaction_id' => $transaction->id,
                            'company_id' => $task->company_id,
                            'branch_id' => $task->agent->branch_id ?? null,
                            'account_id' => $account->id,
                            'task_id' => $task->id,
                            'debit' => 0,
                            'credit' => $task->total,
                            'balance' => $task->total,
                            'transaction_date' => $txnDate,
                            'description' => 'Update For Whom to Pay: ' . $task->reference,
                            'name' => $account->name,
                            'type' => 'payable',
                        ]);

                        $task->update(['payment_method_account_id' => $account->id]);
                        DB::commit();
                    }

                    $newJeTotal += (float) $task->total;
                    $okCount++;
                    if ($okCount % 100 === 0 || $okCount === 1) {
                        $this->line("  [{$okCount}/{$tasks->count()}] task {$task->id} ({$task->reference}) — reversed {$reversed} prior JE, " . ($dryRun ? 'would post' : 'posted') . ' KWD ' . number_format($task->total, 3));
                    }
                }
            } catch (\Throwable $e) {
                if (!$dryRun) {
                    DB::rollBack();
                }
                $this->error("  task {$task->id} ({$task->reference}): {$e->getMessage()}");
                Log::error('AssignTaskPaymentMethod failed', ['task_id' => $task->id, 'err' => $e->getMessage()]);
                $skipCount++;
            }
        }

        $this->newLine();
        $this->info("--- Summary ---");
        $this->line("Mode:               " . ($dryRun ? 'DRY-RUN (no writes)' : 'EXECUTED'));
        $this->line("Tasks processed:    {$okCount} / {$tasks->count()}");
        $this->line("Tasks skipped:      {$skipCount}");
        $this->line("Reversed JE total:  KWD " . number_format($reversedJeTotal, 3));
        $this->line("New JE total:       KWD " . number_format($newJeTotal, 3) . " (credited to {$accountName})");

        return self::SUCCESS;
    }
}
