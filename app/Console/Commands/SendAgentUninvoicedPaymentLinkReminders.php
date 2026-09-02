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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * P2.5.I prod-drift port (verbatim from /home/citycomm/tour.citycommerce.group
 * app/Console/Commands/SendAgentUninvoicedPaymentLinkReminders.php, 2026-08-31; see
 * p2_5-brief.md §P2.5.I "port ... as-is"). Ran on prod ONLY via a direct crontab line
 * (`0 9,16 * * * ... reminder:uninvoiced-payment-links`) with no equivalent command or
 * Schedule:: entry in this repo before this port -- see routes/console.php for the new
 * ->twiceDaily(9, 16) entry that now drives it via `php artisan schedule:run`, and that
 * file's own DEPLOY NOTE for the matching direct-crontab-line removal this enables
 * (user-go, never executed by an agent).
 *
 * Rebuild note (feat/accounting-dev-line): the dedup guard below (DEDUP_WINDOW_HOURS /
 * dedupCacheKey / wasRecentlyReminded / markReminded) is City Travelers' own Fix 5
 * (pre-pilot defect list) and is NOT part of the prod-drift port above; it is re-spliced
 * back in on top of the verbatim port because the two changes are independent and both
 * are real fixes.
 */
class SendAgentUninvoicedPaymentLinkReminders extends Command
{
    protected $signature = 'reminder:uninvoiced-payment-links
        {--dry-run : Preview without sending}
        {--agent= : Only this agent id}
        {--days=30 : Cap window in days (paid within last N days)}';

    protected $description = 'Send reminders to agents about paid payment links not yet invoiced';

    /**
     * Fix 5 (pre-pilot defect list): dedup guard. This command is
     * scheduled twice daily (app/Console/Kernel.php: 12:00 + 19:00
     * Asia/Kuwait, a 7-hour gap) via ->withoutOverlapping(), which only
     * protects against the SAME Laravel-scheduled invocation overlapping
     * itself — not a duplicated raw crontab line or a genuinely separate
     * concurrent run. Nothing previously stopped the same agent being
     * reminded twice back-to-back if either happened.
     *
     * Mechanism: a cache key per (agent, company), set only after a
     * genuinely successful send (mirrors
     * NotifyStaleTaskActionRequests::handle()'s escalated_at precedent —
     * check-before, set-after-success-only, so a failed attempt is never
     * counted as "already reminded" and can still retry). A cache key
     * rather than a last_reminded_at column: this dev environment's
     * CACHE_STORE=database, so it persists across separate CLI process
     * invocations exactly like a DB column would, without adding a column
     * to agent_notification_settings — a table whose migration has
     * already run here (2026_02_26_104808), so a new column would need its
     * own pending migration and "missing column" degrade-safely handling
     * for no real benefit over the cache, which needs neither.
     *
     * The window (6h) is deliberately shorter than the 7h gap between the
     * two legitimate daily runs, so the intended twice-daily cadence is
     * preserved — only a genuine near-duplicate run is deduplicated.
     */
    public const DEDUP_WINDOW_HOURS = 6;

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

            // P2.5.I port fix: prod's own `? $agent->language : 'en'` re-read the raw (possibly
            // null) attribute in the ternary's true-branch after already null-coalescing it for
            // the in_array() check -- harmless on prod, where agents.language is a real column
            // that is rarely null, but Akeed's agents table has no language column at all, so
            // $agent->language is ALWAYS null and this threw "must be of type string, null given"
            // on every single agent (caught by the outer try/catch below, so the command "ran"
            // but silently sent nothing to anyone -- found via this port's own test suite).
            // Coalescing on both sides makes the two branches agree, matching the line's own
            // clearly-intended behavior.
            $locale = in_array($agent->language ?? 'en', ['en', 'ar'], true) ? ($agent->language ?? 'en') : 'en';
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

            if ($this->wasRecentlyReminded($agent, $company)) {
                $this->line("    Skipped: already reminded within the last " . self::DEDUP_WINDOW_HOURS . 'h.');
                continue;
            }

            try {
                if ($this->sendReminder($agent, $company, $payments, $channel, $windowLabel, $locale)) {
                    $totalSent++;
                    $this->markReminded($agent, $company);
                }
            } catch (\Throwable $e) {
                Log::error("[UninvoicedPaymentLinkReminder] Error for agent {$agent->id}: {$e->getMessage()}");
                $this->error("  Error: {$e->getMessage()}");
            }
        }

        $this->info($isDryRun ? '[DRY RUN] Complete.' : "Done. Sent {$totalSent} reminder(s).");

        return self::SUCCESS;
    }

    /**
     * Fix 5 dedup guard — see the class docblock for the full rationale.
     */
    private function dedupCacheKey(Agent $agent, Company $company): string
    {
        return "reminder:uninvoiced-payment-links:{$company->id}:{$agent->id}";
    }

    private function wasRecentlyReminded(Agent $agent, Company $company): bool
    {
        return Cache::has($this->dedupCacheKey($agent, $company));
    }

    private function markReminded(Agent $agent, Company $company): void
    {
        Cache::put($this->dedupCacheKey($agent, $company), now()->toIso8601String(), now()->addHours(self::DEDUP_WINDOW_HOURS));
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
