<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\{AutoBilling, Task, Invoice, Company};
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class RunAutoBilling extends Command
{
    protected $signature = 'autobill:run';
    protected $description = 'Generate automatic invoices for all companies based on Auto Billing Rules';

    public function handle()
    {
        $now = Carbon::now('Asia/Kuala_Lumpur')->format('H:i:s');
        $rules = AutoBilling::where('invoice_time_system', $now)->get();

        foreach ($rules as $rule) {
            Log::info("Running auto bill for company {$rule->company_id} / {$rule->link_type}:{$rule->link_value}");

            $tasks = Task::where($rule->link_type, $rule->link_value)
                ->where('company_id', $rule->company_id)
                ->where('status', 'issued')
                ->whereDoesntHave('invoiceDetail')
                ->get();

            if ($tasks->isEmpty()) continue;

            $clientId = $rule->client_id;
            $total = 0;

            foreach ($tasks as $task) {
                $net = $task->price ?? 0;
                $totalTask = ceil($net + $rule->add_amount + 0.9);
                $total += $totalTask;
            }

            // Create one invoice here (you can reuse your invoice logic)
            // Optionally call WhatsApp send if $rule->auto_send_whatsapp == true

            Log::info("Invoice total for {$tasks->count()} tasks: {$total}");
        }

        return Command::SUCCESS;
    }
}
