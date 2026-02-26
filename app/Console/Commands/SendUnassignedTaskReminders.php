<?php

namespace App\Console\Commands;

use App\Http\Controllers\ResayilController;
use App\Mail\UnassignedTaskReminderMail;
use App\Models\Company;
use App\Models\Setting;
use App\Models\Task;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SendUnassignedTaskReminders extends Command
{
    protected $signature = 'reminder:unassigned-tasks {--dry-run : Preview without sending} {--from= : Start date (d/m/Y)} {--to= : End date (d/m/Y)}';

    protected $description = 'Send reminders for unassigned tasks';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        $this->info($isDryRun ? '[DRY RUN] Previewing unassigned task reminders...' : 'Sending unassigned task reminders...');

        $companies = Company::all();
        $totalSent = 0;

        foreach ($companies as $company) {
            $channel = Setting::getByKey($company->id, 'notification.unassigned_task.channel', 'none');

            if ($channel === 'none') {
                continue;
            }

            $email = Setting::getByKey($company->id, 'notification.unassigned_task.email');
            $phone = Setting::getByKey($company->id, 'notification.unassigned_task.phone');

            // Get the first non-empty 7-day batch (newest first)
            // e.g. today → 7 days ago, then 7-14 days ago, etc.
            $tasks = null;
            $windowLabel = '';

            $optFrom = $this->option('from');
            $optTo = $this->option('to');

            if ($optFrom && $optTo) {
                $from = \Carbon\Carbon::createFromFormat('d/m/Y', $optFrom)->startOfDay();
                $to = \Carbon\Carbon::createFromFormat('d/m/Y', $optTo)->endOfDay();

                $tasks = Task::with('client')
                    ->whereNull('agent_id')
                    ->where('company_id', $company->id)
                    ->whereNotNull('created_at')
                    ->whereBetween('created_at', [$from, $to])
                    ->orderBy('created_at', 'desc')
                    ->get();

                $windowLabel = $from->format('d/m/Y') . ' - ' . $to->format('d/m/Y');
            } else {
                for ($week = 0; $week < 100; $week++) {
                    $from = now()->subDays(($week + 1) * 7);
                    $to = $week === 0 ? now() : now()->subDays($week * 7);

                    $batch = Task::with('client')
                        ->whereNull('agent_id')
                        ->where('company_id', $company->id)
                        ->whereNotNull('created_at')
                        ->whereBetween('created_at', [$from, $to])
                        ->orderBy('created_at', 'desc')
                        ->get();

                    if ($batch->isNotEmpty()) {
                        $tasks = $batch;
                        $windowLabel = $from->format('d/m/Y') . ' - ' . $to->format('d/m/Y');
                        break;
                    }
                }
            }

            if (!$tasks || $tasks->isEmpty()) {
                $this->line("  {$company->name}: No unassigned tasks");
                continue;
            }

            $this->info("  {$company->name}: {$tasks->count()} unassigned task(s) [{$windowLabel}]");

            if ($isDryRun) {
                $this->table(
                    ['#', 'Reference', 'Type', 'Client', 'Created', 'Days'],
                    $tasks->map(function ($task, $index) {
                        return [
                            $index + 1,
                            $task->reference ?? 'N/A',
                            ucfirst($task->type ?? 'N/A'),
                            $task->client->full_name ?? $task->client_name ?? 'N/A',
                            $task->created_at ? $task->created_at->format('d/m/Y') : 'N/A',
                            $task->created_at ? (int) $task->created_at->diffInDays(now()) : '-',
                        ];
                    })
                );
                $this->line("  Window: {$windowLabel} | Via: {$channel} (email: {$email}, phone: {$phone})");
                continue;
            }

            try {
                $sent = $this->sendReminder($company, $tasks, $channel, $email, $phone, $windowLabel);

                if ($sent) {
                    $totalSent++;
                }
            } catch (\Exception $e) {
                Log::error("[UnassignedTaskReminder] Error for company {$company->id}: {$e->getMessage()}");
                $this->error("  Error: {$e->getMessage()}");
            }
        }

        $this->info($isDryRun ? '[DRY RUN] Complete.' : "Done. Sent {$totalSent} reminder(s).");
    }

    private function sendReminder(Company $company, $tasks, string $channel, ?string $email, ?string $phone, string $windowLabel): bool
    {
        $sent = false;

        if (in_array($channel, ['email', 'both'])) {
            $sent = $this->sendEmail($company, $tasks, $email, $windowLabel);
        }

        if (in_array($channel, ['whatsapp', 'both'])) {
            $sent = $this->sendWhatsApp($company, $tasks, $phone, $windowLabel) || $sent;
        }

        return $sent;
    }

    private function sendEmail(Company $company, $tasks, ?string $email, string $windowLabel): bool
    {
        if (app()->environment('local')) {
            $email = env('EMAIL_LOCAL', 'it@alphia.net');
        }

        if (empty($email)) {
            Log::warning("[UnassignedTaskReminder] No email configured for company {$company->id}");
            return false;
        }

        Mail::to($email)->send(new UnassignedTaskReminderMail($tasks, $company, $windowLabel));
        Log::info("[UnassignedTaskReminder] Email sent to {$email} for company {$company->id} ({$tasks->count()} tasks)");
        $this->line("    Email sent to {$email}");

        return true;
    }

    private function sendWhatsApp(Company $company, $tasks, ?string $phone, string $windowLabel): bool
    {
        if (app()->environment('local')) {
            $phone = env('PHONE_LOCAL', $phone);
        }

        if (empty($phone)) {
            Log::warning("[UnassignedTaskReminder] No phone configured for company {$company->id}");
            return false;
        }

        $pdfPath = $this->generatePdf($company, $tasks, $windowLabel);

        $resayil = new ResayilController();
        $response = $resayil->document(
            phone: $phone,
            country_code: '',
            filePath: $pdfPath,
            caption: "*Unassigned Task Reminder*\n\n"
                . "Hello,\n\n"
                . "You have *{$tasks->count()} task(s)* that have not been assigned to any agent yet.\n\n"
                . "Please review and assign an agent as soon as possible.\n\n"
                . "Attached is the full list of unassigned tasks for your reference.\n\n"
                . "_{$company->name}_",
        );

        // Clean up temp file
        if (file_exists($pdfPath)) {
            unlink($pdfPath);
        }

        if ($response['success'] ?? false) {
            Log::info("[UnassignedTaskReminder] WhatsApp sent to {$phone} for company {$company->id}");
            $this->line("    WhatsApp sent to {$phone}");
            return true;
        }

        Log::error("[UnassignedTaskReminder] WhatsApp failed for company {$company->id}: " . json_encode($response));
        $this->error("    WhatsApp failed: " . ($response['error'] ?? 'Unknown error'));
        return false;
    }

    private function generatePdf(Company $company, $tasks, string $windowLabel): string
    {
        $pdf = Pdf::loadView('notifications.pdf.unassigned-tasks', [
            'tasks' => $tasks,
            'company' => $company,
            'windowLabel' => $windowLabel,
            'isPdf' => true,
        ]);

        $filename = "unassigned_tasks_{$company->id}_" . now()->format('Ymd_His') . '.pdf';
        $path = "temp/{$filename}";

        Storage::disk('public')->put($path, $pdf->output());

        return storage_path("app/public/{$path}");
    }
}
