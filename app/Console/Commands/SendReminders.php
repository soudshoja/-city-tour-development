<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Agent;
use App\Models\Payment;
use App\Http\Controllers\ResayilController;
use App\Models\Reminder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendReminders extends Command
{
    protected $signature = 'process:reminder
                            {--dry-run : Run the command in dry-run mode}
                            {--proceed : Execute the command and make changes}';

    protected $description = 'Process and send due reminders to clients and agents';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $proceed = $this->option('proceed');

        if (!$dryRun && !$proceed) {
            $this->error('Please specify either --dry-run or --proceed');
            $this->info('  --dry-run  : Preview reminders without sending');
            $this->info('  --proceed  : Actually send the reminders');
            return 1;
        }

        if ($dryRun) {
            $this->info('Running in DRY RUN mode - no changes will be made');
            $this->newLine();
        }

        $this->info('Starting to process scheduled reminders');
        $this->newLine();
        Log::info('Starting to process scheduled reminders on mode: ', ['mode' => $dryRun ? 'dry-run' : 'proceed']);

        try {
            $dueReminders = Reminder::where('status', 'pending')
                ->where(function ($query) {
                    $query->whereHas('payment', function ($q) {
                        $q->where('status', '!=', 'completed');
                    })
                    ->orWhereHas('invoice', function ($q) {
                        $q->where('status', '!=', 'completed');
                    })
                    // W6.U "Reminders" (owner addition, 2026-08-28): a target_type='task' row
                    // carries neither invoice_id nor payment_id, so without this branch the
                    // whereHas()/orWhereHas() pair above always excludes it -- the exact gap
                    // w6-brief.md flags ("this is the exact spot ... or new task reminders will
                    // silently fail to send"). No open/closed guard is applied here for task
                    // reminders (unlike the invoice/payment branches above) because a ticketing
                    // deadline reminder has no "already settled" state to check against --
                    // reminder:generate-deadlines is itself the only writer of these rows and is
                    // idempotent per (task_id, offset).
                    ->orWhere('target_type', 'task');
                })
                ->where('is_active', true)
                ->where('scheduled_at', '<=', Carbon::now())
                ->with(['client', 'agent', 'invoice', 'payment', 'task'])
                ->get();

            if ($dueReminders->isEmpty()) {
                $this->info('No due reminders to process at this time. Aborting');
                // Log::info('No pending reminders to process at this time');
                return 0;
            } else {
                $this->info("Found {$dueReminders->count()} reminders due to process");
                $this->newLine();

                Log::info("Found {$dueReminders->count()} reminders due to process");
            }

            if ($dryRun) {
                $tableData = $dueReminders->map(function ($reminder) {
                    return [
                        'id' => $reminder->id,
                        'group_id' => $reminder->group_id ?? '-',
                        'target_type' => strtoupper($reminder->target_type),
                        'invoice_id' => $reminder->invoice_id ?? '-',
                        'payment_id' => $reminder->payment_id ?? '-',
                        'client' => strtoupper($reminder->client?->full_name ?? 'N/A'),
                        'agent' => strtoupper($reminder->agent?->name ?? 'N/A'),
                        'send_to' => implode(', ', array_filter([
                            $reminder->send_to_client ? 'Client' : null,
                            $reminder->send_to_agent ? 'Agent' : null,
                        ])) ?: '-',
                        'scheduled_at' => Carbon::parse($reminder->scheduled_at)->format('M d, Y h:i A'),
                        'message' => $reminder->message ? Str::limit($reminder->message, 30) : '-',
                    ];
                })->toArray();

                $this->table(
                    ['ID', 'Group ID', 'Target', 'Invoice ID', 'Payment ID', 'Client', 'Agent', 'Send To', 'Scheduled At', 'Message'],
                    $tableData
                );

                $this->newLine();
                $this->info('To actually send these reminders, run:');
                $this->info('  php artisan process:reminder --proceed');
                $this->newLine();

                return 0;
            }

            if ($proceed) {
                $this->processReminders($dueReminders);
            }

        } catch (\Exception $e) {
            Log::error('Error processing reminders: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error('Failed to process reminders. Check logs for details.');
            $this->error($e->getMessage());
            return 1;
        }

        return 0;
    }

    private function processReminders($dueReminders)
    {
        $resayil = new ResayilController();
        $successCount = 0;
        $failedCount = 0;
        
        foreach ($dueReminders as $reminder) {
            $this->info("Processing Reminder ID: {$reminder->id} (Group: {$reminder->group_id})");
            Log::info("Processing reminder ID: {$reminder->id}", [
                'group_id' => $reminder->group_id,
                'target_type' => $reminder->target_type,
                'invoice_id' => $reminder->invoice_id,
                'payment_id' => $reminder->payment_id,
                'scheduled_at' => $reminder->scheduled_at,
            ]);

            try {
                $client = Client::where('id', $reminder->client_id)->first();
                $agent = Agent::where('id', $reminder->agent_id)->first();

                if (!$client) {
                    $this->error("  ✗ Client not found for reminder ID: {$reminder->id}");
                    $this->markAsFailed($reminder, 'Client not found');
                    $failedCount++;
                    continue;
                }

                if (!$agent) {
                    $this->error("  ✗ Agent not found for reminder ID: {$reminder->id}");
                    $this->markAsFailed($reminder, 'Agent not found');
                    $failedCount++;
                    continue;
                }

                $clientPhone = preg_replace('/[^0-9]/', '', $client->phone ?? '');
                $clientCountryCode = $client->country_code;
                
                $agentPhone = preg_replace('/[^0-9]/', '', $agent->phone_number ?? '');

                if (empty($clientPhone)) {
                    $this->error("  ✗ Client phone number is missing");
                    $this->markAsFailed($reminder, 'Client phone number is missing');
                    $failedCount++;
                    continue;
                }

                if (empty($agentPhone)) {
                    $this->error("  ✗ Agent phone number is missing");
                    $this->markAsFailed($reminder, 'Agent phone number is missing');
                    $failedCount++;
                    continue;
                }

                $this->info("  Client: {$clientCountryCode}{$clientPhone}");
                $this->info("  Agent: {$agentPhone}");

                $messageData = $this->buildMessage($reminder);

                if (!$messageData) {
                    $this->error("  ✗ Failed to build message - missing invoice/payment data");
                    $this->markAsFailed($reminder, 'Failed to build message - missing invoice/payment data');
                    $failedCount++;
                    continue;
                }

                $clientResult = ['success' => true];
                $agentResult = ['success' => true];

                if ($reminder->send_to_client) {
                    $clientResult = $resayil->shareReminder(
                        $clientPhone,
                        $clientCountryCode,
                        $messageData['client_message'],
                        $reminder->client_id,
                        $reminder->agent_id,
                        $reminder->invoice_id ?? $reminder->payment_id,
                    );

                    Log::info("Reminder ID {$reminder->id} - Sent to CLIENT", $clientResult);
                    $this->info("  → Client: " . ($clientResult['success'] ? '✓ Sent' : '✗ Failed'));
                }

                if ($reminder->send_to_agent) {
                    $agentResult = $resayil->shareReminder(
                        $agentPhone,
                        '',
                        $messageData['agent_message'],
                        $reminder->client_id,
                        $reminder->agent_id,
                        $reminder->invoice_id ?? $reminder->payment_id,
                    );

                    Log::info("Reminder ID {$reminder->id} - Sent to AGENT", $agentResult);
                    $this->info("  → Agent: " . ($agentResult['success'] ? '✓ Sent' : '✗ Failed'));
                }

                $clientSuccess = !$reminder->send_to_client || $clientResult['success'];
                $agentSuccess = !$reminder->send_to_agent || $agentResult['success'];

                if ($clientSuccess && $agentSuccess) {
                    $reminder->update([
                        'status' => 'sent',
                        'sent_at' => Carbon::now(),
                    ]);
                    $successCount++;
                    $this->info("  ✓ Marked as sent");
                    Log::info("Reminder ID {$reminder->id} marked as sent.");
                } else {
                    $errorMessage = $this->buildErrorMessage($clientResult, $agentResult, $reminder);
                    $this->markAsFailed($reminder, $errorMessage);
                    $failedCount++;
                    $this->error("  ✗ Marked as failed");
                }

            } catch (\Exception $e) {
                $this->error("  ✗ Exception: {$e->getMessage()}");
                Log::error("Exception processing reminder ID {$reminder->id}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->markAsFailed($reminder, $e->getMessage());
                $failedCount++;
            }

            $this->newLine();
        }

        $this->newLine();
        $this->info('           PROCESSING COMPLETE          ');
        $this->info('════════════════════════════════════════');
        $this->info("  ✓ Success: {$successCount}");
        $this->info("  ✗ Failed:  {$failedCount}");
        $this->info("  Total:     {$dueReminders->count()}");

        Log::info('Processing scheduled reminders completed', [
            'success' => $successCount,
            'failed' => $failedCount,
            'total' => $dueReminders->count(),
        ]);
    }

    private function buildMessage(Reminder $reminder): ?array
    {
        Log::info("Building message for reminder ID {$reminder->id}", [
            'reminder' => $reminder->toArray(),
        ]);

        $additionalInfo = $reminder->message
            ? "\n\nAdditional information regarding this {$reminder->target_type} can be found below:\n{$reminder->message}"
            : '';

        if ($reminder->target_type === 'invoice' && $reminder->invoice) {
            $invoice = $reminder->invoice;
            $client = $reminder->client;

            $formattedDueDate = Carbon::parse($invoice->due_date)->format('jS F Y');
            $invoiceLink = route('invoice.show', [
                'companyId' => $client->agent->branch->company_id ?? 1,
                'invoiceNumber' => $invoice->invoice_number,
            ]);

            return [
                'client_message' => "Please be reminded that you have an outstanding payment to invoice {$invoice->invoice_number} of {$invoice->currency} {$invoice->amount} that was past due on {$formattedDueDate}.{$additionalInfo}\n\nPlease click the following link to make the payment to the invoice:\n{$invoiceLink}\n\nShould you require further assistance, feel free to reach out our support team.",

                'agent_message' => "This is a reminder that your client has an outstanding payment to invoice {$invoice->invoice_number} of {$invoice->currency} {$invoice->amount} that was past due on {$formattedDueDate}.{$additionalInfo}\n\nInvoice link:\n{$invoiceLink}\n\nPlease follow up with your client regarding this payment.",
            ];

        } elseif ($reminder->target_type === 'task' && $reminder->reminder_kind === 'ticketing_deadline' && $reminder->task) {
            // W6.U "Reminders" (owner addition, 2026-08-28) -- the exact branch w6-brief.md flags
            // as missing ("this is the exact spot a new task/ticketing_deadline branch must be
            // added, or new task reminders will silently fail to send"). Reuses this method's own
            // $additionalInfo convention above; builds agent + optional client text (deadline
            // time, task/PNR reference, deposit held).
            $task = $reminder->task;
            $deadline = $task->deadline_at ? Carbon::parse($task->deadline_at)->format('jS F Y, h:i A') : 'its ticketing deadline';
            $deposit = app(\App\Services\TaskStatusService::class)->depositHeld($task);
            $depositText = $deposit > 0 ? number_format($deposit, 3) . ' held as deposit' : 'no deposit on file';

            $agentMessage = "Reminder: booking reference {$task->reference} for passenger {$task->passenger_name} must be ticketed before {$deadline} ({$depositText}).{$additionalInfo}\n\nPlease action this booking before the deadline to avoid losing the reservation.";

            $clientMessage = null;
            if ($reminder->send_to_client) {
                // "an optional client nudge ... the message includes a payment link built the
                // same way the existing payment.link.show flow does" -- Task carries no direct
                // Payment relation, so the link is included only when one can actually be
                // resolved for this client; the reminder still sends without it otherwise rather
                // than failing the whole message.
                $clientMessage = "Please be reminded that your booking (ref. {$task->reference}) is due for ticketing before {$deadline}.{$additionalInfo}\n\nPlease contact us to complete your booking before the deadline.";

                $payment = Payment::where('client_id', $task->client_id)
                    ->where('status', '!=', 'completed')
                    ->latest()
                    ->first();

                if ($payment && $client = $reminder->client) {
                    $paymentLink = route('payment.link.show', [
                        'companyId' => $client->agent->branch->company->id ?? 1,
                        'voucherNumber' => $payment->voucher_number,
                    ]);
                    $clientMessage .= "\n\nPayment link:\n{$paymentLink}";
                }
            }

            return [
                'client_message' => $clientMessage ?? $agentMessage,
                'agent_message' => $agentMessage,
            ];
        } elseif ($reminder->target_type === 'payment' && $reminder->payment) {
            $payment = $reminder->payment;
            $client = $reminder->client;

            $paymentLink = route('payment.link.show', [
                'companyId' => $client->agent->branch->company->id ?? 1,
                'voucherNumber' => $payment->voucher_number,
            ]);

            return [
                'client_message' => "Please be reminded that you have an outstanding payment to voucher {$payment->voucher_number} of {$payment->currency} {$payment->amount}.{$additionalInfo}\n\nPlease click the following link to make the payment:\n{$paymentLink}\n\nShould you require further assistance, feel free to reach out our support team.",

                'agent_message' => "This is a reminder that your client has an outstanding payment to voucher {$payment->voucher_number} of {$payment->currency} {$payment->amount}.{$additionalInfo}\n\nPayment link:\n{$paymentLink}\n\nPlease follow up with your client regarding this payment.",
            ];
        }

        return null;
    }

    private function buildErrorMessage(array $clientResult, array $agentResult, Reminder $reminder): string
    {
        $errors = [];

        if ($reminder->send_to_client && !$clientResult['success']) {
            $errors[] = 'Client: ' . ($clientResult['error'] ?? 'Unknown error');
        }

        if ($reminder->send_to_agent && !$agentResult['success']) {
            $errors[] = 'Agent: ' . ($agentResult['error'] ?? 'Unknown error');
        }

        return implode('; ', $errors) ?: 'Unknown error';
    }

    private function markAsFailed(Reminder $reminder, string $errorMessage): void
    {
        $reminder->update([
            'status' => 'failed',
            'error_message' => Str::limit($errorMessage, 500),
        ]);

        Log::error("Reminder ID {$reminder->id} marked as failed", [
            'error_message' => $errorMessage,
        ]);
    }
}