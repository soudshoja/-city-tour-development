<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AutoBilling;
use App\Models\Task;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoicePartial;
use App\Models\InvoiceSequence;
use App\Models\Client;
use App\Models\Company;
use App\Models\Charge;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\JournalEntry;
use App\Services\ChargeService;
use App\Http\Controllers\ResayilController;
use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class RunAutoBilling extends Command
{
    protected $signature = 'autobill:generate-invoices {--dry-run : Show eligible/ineligible tasks without creating invoices}';
    protected $description = 'Automatically generate invoices for all active AutoBilling rules at their scheduled times.';

    public function handle()
    {
        $startTime = microtime(true);
        $now = Carbon::now('Asia/Kuala_Lumpur')->format('H:i');

        $this->info("🕒 Running AutoBilling at {$now}");
        Log::info("=== [AutoBilling] Command started at {$now} ===");

        $rules = AutoBilling::where('is_active', true)
            ->whereRaw("DATE_FORMAT(invoice_time_system, '%H:%i') = ?", [$now])
            ->get();

        if ($rules->isEmpty()) {
            $this->warn('⚠️  No AutoBilling rules scheduled for this time.');
            Log::info('No AutoBilling rules scheduled for this time.');
            return Command::SUCCESS;
        }

        $totalRules = $rules->count();
        $this->info("🔍 Found {$totalRules} active AutoBilling rule(s) to process.");
        $bar = $this->output->createProgressBar($totalRules);
        $bar->setFormat(' 🧾 [%bar%] %current%/%max% | %message%');

        $invoiceController = new InvoiceController();

        foreach ($rules as $rule) {
            DB::beginTransaction();

            try {
                $client = Client::find($rule->client_id);
                $clientName = $client->full_name ?? null;

                $matchType = $rule->created_by ? 'created_by' : ($rule->issued_by ? 'issued_by' : 'agent_id');
                $matchValue = $rule->{$matchType} ?? 'N/A';

                $msg = "Processing Rule #{$rule->id} for {$clientName} [{$matchType}: {$matchValue}]";
                $bar->setMessage($msg);
                $bar->advance();
                Log::info("[AutoBilling] {$msg}");

                $query = Task::query()
                    ->where('company_id', $rule->company_id)
                    ->where('client_id', $rule->client_id)
                    ->whereDoesntHave('invoiceDetail')
                    ->where(function ($q) use ($rule) {
                        if ($rule->created_by) {
                            $q->where('created_by', $rule->created_by);
                        }
                        if ($rule->issued_by) {
                            $q->where('issued_by', $rule->issued_by);
                        }
                        if ($rule->agent_id) {
                            $q->where('agent_id', $rule->agent_id);
                        }
                    });

                $tasks = $query->get();

                if ($tasks->isEmpty()) {
                    $this->line("\n   ➤ No eligible tasks found for {$clientName} (Rule ID {$rule->id})");
                    Log::info("[AutoBilling] No tasks found for Rule ID {$rule->id}");
                    DB::rollBack();
                    continue;
                }

                $eligibleTasks = [];
                $ineligibleTasks = [];

                foreach ($tasks as $task) {
                    $issues = [];

                    if (!$task->is_complete) {
                        $issues[] = 'Task not marked complete';
                    }

                    if (!$task->agent_id) {
                        $issues[] = 'Missing agent assignment';
                    }

                    if (!$task->client_id) {
                        $issues[] = 'Missing client assignment';
                    }

                    if (empty($task->supplier_pay_date)) {
                        $issues[] = 'Missing supplier issued date';
                    }

                    $journalExists = JournalEntry::where('task_id', $task->id)
                        ->whereHas('transaction', function ($q) use ($task) {
                            $q->where('description', 'like', '%' . $task->reference . '%');
                        })
                        ->exists();

                    if (!$journalExists) {
                        $issues[] = 'No matching journal entry found for task reference';
                    }

                    // Auto-enable task if it meets all completion requirements
                    if (
                        !$task->enabled &&
                        $task->agent_id &&
                        $task->client_id &&
                        !empty($task->supplier_pay_date) &&
                        JournalEntry::where('task_id', $task->id)
                            ->whereHas('transaction', function ($q) use ($task) {
                                $q->where('description', 'like', '%' . ($task->reference ?? '') . '%');
                            })
                            ->exists()
                    ) {
                        $task->enabled = true;
                        $task->save();

                        $this->line("🔓 Task {$task->id} auto-enabled (client: {$task->client_id}, agent: {$task->agent_id})");
                        Log::info("[AutoBilling] Task {$task->id} auto-enabled (met all completion conditions).");
                    }

                    if (empty($issues)) {
                        $eligibleTasks[] = $task;
                    } else {
                        $ineligibleTasks[] = [
                            'task_id' => $task->id,
                            'reference' => $task->reference,
                            'issues' => implode(', ', $issues)
                        ];
                    }
                }

                $totalTasks = $tasks->count();
                $validCount = count($eligibleTasks);
                $invalidCount = count($ineligibleTasks);

                $this->newLine();
                $this->line("🔹 Total tasks found: {$totalTasks}");
                $this->line("✅ Eligible for invoicing: {$validCount}");
                $this->line("❌ Ineligible: {$invalidCount}");

                if ($invalidCount > 0) {
                    $this->warn("⚠️  Ineligible tasks list:");
                    foreach ($ineligibleTasks as $bad) {
                        $this->line("   - Task ID {$bad['task_id']} ({$bad['reference']}): {$bad['issues']}");
                    }
                }

                Log::info("[AutoBilling] Rule #{$rule->id} task validation", [
                    'total' => $totalTasks,
                    'eligible' => $validCount,
                    'ineligible' => $invalidCount,
                    'ineligible_reasons' => $ineligibleTasks
                ]);

                if ($this->option('dry-run')) {
                    $this->info("\n🧾 DRY RUN MODE: Skipping invoice creation for Rule #{$rule->id}");
                    DB::rollBack();
                    continue;
                }

                if (empty($eligibleTasks)) {
                    $this->warn("⚠️ No eligible tasks for rule #{$rule->id}, skipping invoice creation.");
                    DB::rollBack();
                    continue;
                }

                $tasks = collect($eligibleTasks);

                $invoiceSequence = InvoiceSequence::firstOrCreate(['company_id' => $rule->company_id], ['current_sequence' => 1]);
                $currentSequence = $invoiceSequence->current_sequence;
                $invoiceNumber = $invoiceController->generateInvoiceNumber($currentSequence);
                $invoiceSequence->increment('current_sequence');

                $totalAmount = 0;
                foreach ($tasks as $task) {
                    $task->invoice_price = ceil($task->total + $rule->add_amount);
                    $totalAmount += $task->invoice_price;
                }

                $invoice = Invoice::create([
                    'invoice_number' => $invoiceNumber,
                    'agent_id' => $task->agent_id,
                    'client_id' => $task->client_id,
                    'company_id' => $task->company_id,
                    'sub_amount' => $totalAmount,
                    'amount' => $totalAmount,
                    'currency' => 'KWD',
                    'status' => 'unpaid',
                    'payment_type' => 'full',
                    'invoice_date' => now($rule->timezone),
                    'due_date' => now($rule->timezone)->addDays(3),
                ]);

                $transaction = Transaction::create([
                    'company_id' => $tasks[0]->company_id,
                    'branch_id' => $tasks[0]->agent->branch_id ?? null,
                    'entity_id' => $tasks[0]->company_id,
                    'entity_type' => 'company',
                    'transaction_type' => 'credit',
                    'amount' => $invoice->amount,
                    'description' => 'Invoice: ' . $invoice->invoice_number . ' Generated',
                    'invoice_id' => $invoice->id,
                    'reference_type' => 'Invoice',
                    'transaction_date' => $invoice->invoice_date,
                ]);

                foreach ($tasks as $task) {
                    $invoiceDetail = InvoiceDetail::create([
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoiceNumber,
                        'task_id' => $task->id,
                        'task_description' => $task->reference,
                        'task_remark' => $task->remark,
                        'task_price' => $task->invoice_price,
                        'supplier_price' => $task->total,
                        'markup_price' => $task->invoice_price - $task->total,
                        'paid' => false,
                    ]);

                    try {
                        $invoiceController->addJournalEntry(
                            $task,
                            $invoice->id,
                            $invoiceDetail->id,
                            $transaction->id,
                            $clientName
                        );
                    } catch (Exception $e) {
                        Log::error("[AutoBilling] Journal entry failed for Task {$task->id}: " . $e->getMessage());
                    }
                }

                $gateway = optional($rule->gateway)->name ?? null;
                $fee = 0;

                try {
                    switch (strtolower($gateway)) {
                        case 'myfatoorah':
                            $fee = ChargeService::FatoorahCharge($totalAmount, $rule->method_id, $rule->company_id)['fee'] ?? 0;
                            break;
                        case 'hesabe':
                            $fee = ChargeService::HesabeCharge($totalAmount, $rule->method_id, $rule->company_id)['fee'] ?? 0;
                            break;
                        case 'tap':
                            $fee = ChargeService::TapCharge([
                                'amount' => $totalAmount,
                                'client_id' => $invoice->client_id,
                                'agent_id' => $invoice->agent_id,
                                'currency' => $invoice->currency
                            ], $gateway)['fee'] ?? 0;
                            break;
                        case 'upayment':
                            $fee = ChargeService::UPaymentCharge($totalAmount, $rule->method_id, $rule->company_id)['fee'] ?? 0;
                            break;
                    }
                } catch (Exception $e) {
                    Log::error("[AutoBilling] Gateway fee calculation failed: {$e->getMessage()}");
                }

                InvoicePartial::create([
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoiceNumber,
                    'client_id' => $task->client_id,
                    'service_charge' => $fee,
                    'amount' => $totalAmount,
                    'status' => 'unpaid',
                    'expiry_date' => now($rule->timezone)->addDays(3),
                    'type' => 'full',
                    'payment_gateway' => $gateway,
                    'payment_method' => $rule->method_id ?? null,
                    'charge_id' => $rule->gateway_id,
                ]);

                DB::commit();

                $this->line("\n ✅ Invoice #{$invoiceNumber} created for {$clientName} ({$tasks->count()} eligible task[s])");

                if ($rule->auto_send_whatsapp) {
                    try {
                        $resayil = new ResayilController();
                        $request = new Request([
                            'client_id' => $rule->client_id,
                            'invoiceNumber' => $invoiceNumber,
                        ]);
                        $resayil->shareInvoiceLink($request);
                        $this->info("   📲 WhatsApp sent to {$clientName}");
                        Log::info("[AutoBilling] WhatsApp invoice #{$invoiceNumber} sent for {$clientName}");
                    } catch (Exception $e) {
                        $this->error("   ⚠️ Failed to send WhatsApp for {$clientName}");
                        Log::error("[AutoBilling] WhatsApp send failed: {$e->getMessage()}");
                    }
                }
            } catch (Exception $e) {
                DB::rollBack();
                $this->error("\n ❌ Error in Rule #{$rule->id}: {$e->getMessage()}");
                Log::error("[AutoBilling] Rule {$rule->id} error: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            }
        }

        $bar->finish();
        $duration = round(microtime(true) - $startTime, 2);

        $this->newLine();
        $this->info("✅ AutoBilling completed for {$rules->count()} rule(s) in {$duration}s.");
        Log::info("=== [AutoBilling] Command finished in {$duration}s ===");

        return Command::SUCCESS;
    }
}
