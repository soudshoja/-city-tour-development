<?php

namespace App\Console\Commands;

use App\Http\Controllers\ResayilController;
use App\Mail\UninvoicedTaskReminderMail;
use App\Models\Agent;
use App\Models\AgentNotificationSetting;
use App\Models\Company;
use App\Models\Task;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SendAgentUninvoicedTaskReminders extends Command
{
    protected $signature = 'reminder:uninvoiced-tasks {--dry-run : Preview without sending} {--from= : Start date (d/m/Y)} {--to= : End date (d/m/Y)}';

    protected $description = 'Send reminders to agents about tasks not yet invoiced';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        $this->info($isDryRun ? '[DRY RUN] Previewing uninvoiced task reminders...' : 'Sending uninvoiced task reminders...');

        $settings = AgentNotificationSetting::where('notification_type', AgentNotificationSetting::TYPE_TASK_CLOSE)
            ->where('is_active', true)
            ->with(['agent.branch.company'])
            ->get();

        if ($settings->isEmpty()) {
            $this->info('No active agent notification settings found.');
            return;
        }

        $totalSent = 0;

        foreach ($settings as $setting) {
            $agent = $setting->agent;

            if (!$agent) {
                continue;
            }

            $company = $agent->branch->company ?? Company::find($setting->company_id);

            if (!$company) {
                continue;
            }

            $voidReferences = Task::where('company_id', $company->id)->where('status', 'void')->pluck('reference');

            // Get the first non-empty 7-day batch (newest first)
            // e.g. today → 7 days ago, then 7-14 days ago, etc.
            $tasks = null;
            $windowLabel = '';

            $optFrom = $this->option('from');
            $optTo = $this->option('to');

            if ($optFrom && $optTo) {
                $from = \Carbon\Carbon::createFromFormat('d/m/Y', $optFrom)->startOfDay();
                $to = \Carbon\Carbon::createFromFormat('d/m/Y', $optTo)->endOfDay();

                $tasks = Task::with(['client', 'supplier'])
                    ->where('agent_id', $agent->id)
                    ->whereDoesntHave('invoiceDetail')
                    ->where('status', '!=', 'void')
                    ->when($voidReferences->isNotEmpty(), fn ($q) => $q->whereNotIn('reference', $voidReferences))
                    ->whereNotNull('created_at')
                    ->whereBetween('created_at', [$from, $to])
                    ->orderBy('created_at', 'desc')
                    ->get();

                $windowLabel = $from->format('d/m/Y') . ' - ' . $to->format('d/m/Y');
            } else {
                $latestTask = Task::where('agent_id', $agent->id)
                    ->whereDoesntHave('invoiceDetail')
                    ->where('status', '!=', 'void')
                    ->when($voidReferences->isNotEmpty(), fn ($q) => $q->whereNotIn('reference', $voidReferences))
                    ->whereNotNull('created_at')
                    ->latest('created_at')
                    ->first();

                if ($latestTask) {
                    $to = $latestTask->created_at;
                    $from = $to->copy()->subDays(7);

                    $tasks = Task::with(['client', 'supplier'])
                        ->where('agent_id', $agent->id)
                        ->whereDoesntHave('invoiceDetail')
                        ->where('status', '!=', 'void')
                        ->when($voidReferences->isNotEmpty(), fn ($q) => $q->whereNotIn('reference', $voidReferences))
                        ->whereNotNull('created_at')
                        ->whereBetween('created_at', [$from, $to])
                        ->orderBy('created_at', 'desc')
                        ->get();

                    $windowLabel = $from->format('d/m/Y') . ' - ' . $to->format('d/m/Y');
                }
            }

            if (!$tasks || $tasks->isEmpty()) {
                $this->line("  {$agent->name}: No uninvoiced tasks");
                continue;
            }

            $this->info("  {$agent->name}: {$tasks->count()} uninvoiced task(s) [{$windowLabel}]");

            if ($isDryRun) {
                $this->table(
                    ['#', 'Reference', 'Type', 'Supplier', 'Client', 'Status', 'Total', 'Created'],
                    $tasks->map(function ($task, $index) {
                        return [
                            $index + 1,
                            $task->reference ?? 'N/A',
                            ucfirst($task->type ?? 'N/A'),
                            $task->supplier->name ?? 'Not Set',
                            $task->client->full_name ?? $task->client_name ?? $task->passenger_name?? 'Not Set',
                            ucfirst($task->status ?? 'N/A'),
                            number_format($task->total ?? 0, 3),
                            $task->created_at ? $task->created_at->format('d/m/Y') : 'N/A',
                        ];
                    })
                );
                $this->line("  Window: {$windowLabel} | Via: {$setting->channel} (email: {$agent->email}, phone: {$agent->phone_number})");
                continue;
            }

            try {
                $sent = $this->sendReminder($agent, $company, $tasks, $setting->channel, $windowLabel);

                if ($sent) {
                    $totalSent++;
                }
            } catch (\Exception $e) {
                Log::error("[UninvoicedTaskReminder] Error for agent {$agent->id}: {$e->getMessage()}");
                $this->error("  Error: {$e->getMessage()}");
            }
        }

        $this->info($isDryRun ? '[DRY RUN] Complete.' : "Done. Sent {$totalSent} reminder(s).");
    }

    private function sendReminder(Agent $agent, Company $company, $tasks, string $channel, string $windowLabel): bool
    {
        $sent = false;

        if (in_array($channel, ['email', 'both'])) {
            $sent = $this->sendEmail($agent, $company, $tasks, $windowLabel);
        }

        if (in_array($channel, ['whatsapp', 'both'])) {
            $sent = $this->sendWhatsApp($agent, $company, $tasks, $windowLabel) || $sent;
        }

        return $sent;
    }

    private function sendEmail(Agent $agent, Company $company, $tasks, string $windowLabel): bool
    {
        $email = $agent->email;

        if (app()->environment('local')) {
            $email = env('EMAIL_LOCAL', 'it@alphia.net');
        }

        if (empty($email)) {
            Log::warning("[UninvoicedTaskReminder] No email for agent {$agent->id} ({$agent->name})");
            return false;
        }

        Mail::to($email)->send(new UninvoicedTaskReminderMail($agent, $tasks, $company, $windowLabel));
        Log::info("[UninvoicedTaskReminder] Email sent to {$email} for agent {$agent->id} ({$tasks->count()} tasks)");
        $this->line("    Email sent to {$email}");

        return true;
    }

    private function sendWhatsApp(Agent $agent, Company $company, $tasks, string $windowLabel): bool
    {
        $phone = $agent->phone_number;
        $countryCode = $agent->country_code ?? '';

        if (app()->environment('local')) {
            $phone = env('PHONE_LOCAL', $phone);
            $countryCode = '';
        }

        if (empty($phone)) {
            Log::warning("[UninvoicedTaskReminder] No phone for agent {$agent->id} ({$agent->name})");
            return false;
        }

        $pdfPath = $this->generatePdf($agent, $company, $tasks, $windowLabel);

        $resayil = new ResayilController();
        $response = $resayil->document(
            phone: $phone,
            country_code: $countryCode,
            filePath: $pdfPath,
            caption: "*Uninvoiced Task Reminder*\n\n"
                . "Dear {$agent->name},\n\n"
                . "You have *{$tasks->count()} task(s)* that have not been invoiced yet.\n\n"
                . "Please create invoices for these tasks as soon as possible.\n\n"
                . "Attached is the full list of uninvoiced tasks for your reference.\n\n"
                . "_{$company->name}_",
        );

        // Clean up temp file
        if (file_exists($pdfPath)) {
            unlink($pdfPath);
        }

        if ($response['success'] ?? false) {
            Log::info("[UninvoicedTaskReminder] WhatsApp sent to {$countryCode}{$phone} for agent {$agent->id}");
            $this->line("    WhatsApp sent to {$countryCode}{$phone}");
            return true;
        }

        Log::error("[UninvoicedTaskReminder] WhatsApp failed for agent {$agent->id}: " . json_encode($response));
        $this->error("    WhatsApp failed: " . ($response['error'] ?? 'Unknown error'));
        return false;
    }

    private function generatePdf(Agent $agent, Company $company, $tasks, string $windowLabel): string
    {
        $pdf = Pdf::loadView('notifications.pdf.uninvoiced-tasks', [
            'agent' => $agent,
            'tasks' => $tasks,
            'company' => $company,
            'windowLabel' => $windowLabel,
            'isPdf' => true,
        ]);

        $filename = "uninvoiced_tasks_{$agent->id}_" . now()->format('Ymd_His') . '.pdf';
        $path = "temp/{$filename}";

        Storage::disk('local')->put($path, $pdf->output());

        return storage_path("app/{$path}");
    }
}
