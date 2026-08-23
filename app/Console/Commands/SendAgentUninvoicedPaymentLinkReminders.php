<?php

namespace App\Console\Commands;

use App\Http\Controllers\ResayilController;
use App\Mail\UninvoicedPaymentLinkReminderMail;
use App\Models\Agent;
use App\Models\AgentNotificationSetting;
use App\Models\Company;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SendAgentUninvoicedPaymentLinkReminders extends Command
{
    protected $signature = 'reminder:uninvoiced-payment-links
        {--dry-run : Preview without sending}
        {--agent= : Only this agent id}
        {--days=30 : Cap window in days (paid within last N days)}';

    protected $description = 'Send reminders to agents about paid payment links not yet invoiced';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $onlyAgent = $this->option('agent');
        $days = max(1, (int) $this->option('days'));

        $this->info($isDryRun ? '[DRY RUN] Previewing uninvoiced payment-link reminders...' : 'Sending uninvoiced payment-link reminders...');

        $cutoff = now()->subDays($days);

        $agents = Agent::query()
            ->when($onlyAgent, fn ($q) => $q->where('id', $onlyAgent))
            ->with('branch.company')
            ->get();

        if ($agents->isEmpty()) {
            $this->info('No agents to process.');
            return self::SUCCESS;
        }

        $totalSent = 0;

        foreach ($agents as $agent) {
            $company = $agent->branch->company ?? null;
            if (!$company) {
                continue;
            }

            $payments = Payment::with(['client'])
                ->where('agent_id', $agent->id)
                ->where('company_id', $company->id)
                ->where('completed', true)
                ->whereNull('invoice_id')
                ->where(function ($q) {
                    $q->where('is_disabled', false)->orWhereNull('is_disabled');
                })
                ->where('payment_date', '>=', $cutoff)
                ->orderBy('payment_date', 'desc')
                ->get();

            if ($payments->isEmpty()) {
                continue;
            }

            $setting = AgentNotificationSetting::getForAgent(
                $agent->id,
                $company->id,
                AgentNotificationSetting::TYPE_PAYMENT_LINK_UNINVOICED
            );

            $isExplicit = $setting->exists;
            $channel = $isExplicit ? $setting->channel : AgentNotificationSetting::CHANNEL_BOTH;
            $isActive = $isExplicit ? (bool) $setting->is_active : true;

            if (!$isActive) {
                continue;
            }

            $locale = in_array($agent->language ?? 'en', ['en', 'ar'], true) ? $agent->language : 'en';
            $windowLabel = $cutoff->format('d/m/Y') . ' - ' . now()->format('d/m/Y');

            $this->info("  {$agent->name}: {$payments->count()} uninvoiced payment(s) [{$windowLabel}] lang={$locale} ch={$channel}");

            if ($isDryRun) {
                $this->table(
                    ['#', 'Voucher', 'Client', 'Amount', 'Currency', 'Gateway', 'Paid', 'Reference'],
                    $payments->map(fn ($p, $i) => [
                        $i + 1,
                        $p->voucher_number ?? 'N/A',
                        $p->client->full_name ?? '—',
                        number_format($p->amount ?? 0, 3),
                        $p->currency ?? '',
                        ucfirst($p->payment_gateway ?? '—'),
                        $p->payment_date ? \Carbon\Carbon::parse($p->payment_date)->format('d/m/Y') : 'N/A',
                        $p->payment_reference ?? '—',
                    ])
                );
                continue;
            }

            try {
                if ($this->sendReminder($agent, $company, $payments, $channel, $windowLabel, $locale)) {
                    $totalSent++;
                }
            } catch (\Throwable $e) {
                Log::error("[UninvoicedPaymentLinkReminder] Error for agent {$agent->id}: {$e->getMessage()}");
                $this->error("  Error: {$e->getMessage()}");
            }
        }

        $this->info($isDryRun ? '[DRY RUN] Complete.' : "Done. Sent {$totalSent} reminder(s).");

        return self::SUCCESS;
    }

    private function sendReminder(Agent $agent, Company $company, $payments, string $channel, string $windowLabel, string $locale): bool
    {
        $sent = false;

        if (in_array($channel, [AgentNotificationSetting::CHANNEL_EMAIL, AgentNotificationSetting::CHANNEL_BOTH], true)) {
            try {
                $sent = $this->sendEmail($agent, $company, $payments, $windowLabel, $locale);
            } catch (\Throwable $e) {
                Log::error("[UninvoicedPaymentLinkReminder] Email failed for agent {$agent->id}: {$e->getMessage()}");
                $this->error('    Email error: ' . $e->getMessage());
            }
        }

        if (in_array($channel, [AgentNotificationSetting::CHANNEL_WHATSAPP, AgentNotificationSetting::CHANNEL_BOTH], true)) {
            try {
                $sent = $this->sendWhatsApp($agent, $company, $payments, $windowLabel, $locale) || $sent;
            } catch (\Throwable $e) {
                Log::error("[UninvoicedPaymentLinkReminder] WhatsApp failed for agent {$agent->id}: {$e->getMessage()}");
                $this->error('    WhatsApp error: ' . $e->getMessage());
            }
        }

        return $sent;
    }

    private function sendEmail(Agent $agent, Company $company, $payments, string $windowLabel, string $locale): bool
    {
        $email = $agent->email;
        if (app()->environment('local')) {
            $email = env('EMAIL_LOCAL', $email);
        }

        if (empty($email)) {
            Log::warning("[UninvoicedPaymentLinkReminder] No email for agent {$agent->id} ({$agent->name})");
            return false;
        }

        Mail::to($email)->send(new UninvoicedPaymentLinkReminderMail($agent, $payments, $company, $windowLabel, $locale));
        Log::info("[UninvoicedPaymentLinkReminder] Email sent to {$email} for agent {$agent->id} ({$payments->count()} payments)");
        $this->line("    Email sent to {$email}");

        return true;
    }

    private function sendWhatsApp(Agent $agent, Company $company, $payments, string $windowLabel, string $locale): bool
    {
        $phone = $agent->phone_number;
        $countryCode = $agent->country_code ?? '';

        if (app()->environment('local')) {
            $phone = env('PHONE_LOCAL', $phone);
            $countryCode = '';
        }

        if (empty($phone)) {
            Log::warning("[UninvoicedPaymentLinkReminder] No phone for agent {$agent->id} ({$agent->name})");
            return false;
        }

        $pdfPath = $this->generatePdf($agent, $company, $payments, $windowLabel, $locale);

        $caption = trans('payment_link_reminder.whatsapp_caption', [
            'name' => $agent->name,
            'count' => $payments->count(),
            'company' => $company->name ?? 'City Travelers',
        ], $locale);

        $resayil = new ResayilController();
        $response = $resayil->document(
            phone: $phone,
            country_code: $countryCode,
            filePath: $pdfPath,
            caption: $caption,
            isDummyNumber: false,
        );

        if (file_exists($pdfPath)) {
            unlink($pdfPath);
        }

        if ($response['success'] ?? false) {
            Log::info("[UninvoicedPaymentLinkReminder] WhatsApp sent to {$countryCode}{$phone} for agent {$agent->id}");
            $this->line("    WhatsApp sent to {$countryCode}{$phone}");
            return true;
        }

        Log::error("[UninvoicedPaymentLinkReminder] WhatsApp failed for agent {$agent->id}: " . json_encode($response));
        $this->error('    WhatsApp failed: ' . ($response['error'] ?? 'Unknown error'));
        return false;
    }

    private function generatePdf(Agent $agent, Company $company, $payments, string $windowLabel, string $locale): string
    {
        $pdf = Pdf::loadView('notifications.pdf.uninvoiced-payment-links', [
            'agent' => $agent,
            'payments' => $payments,
            'company' => $company,
            'windowLabel' => $windowLabel,
            'locale' => $locale,
            'isPdf' => true,
        ]);

        $filename = "uninvoiced_payment_links_{$agent->id}_" . now()->format('Ymd_His') . '.pdf';
        $path = "temp/{$filename}";

        Storage::disk('local')->put($path, $pdf->output());

        return storage_path("app/{$path}");
    }
}
