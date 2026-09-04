<?php

namespace App\Console\Commands;

use App\Http\Controllers\ResayilController;
use App\Models\Agent;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendKwikt2843MonthlyReport extends Command
{
    protected $signature = 'reminders:kwikt2843-monthly-report
                            {--dry-run : Preview without sending}
                            {--month= : YYYY-MM to report for (default: current month)}';

    protected $description = 'End-of-month WhatsApp to each agent showing how many PNRs they created in office KWIKT2843 during the month.';

    private const WRONG_OFFICE = 'KWIKT2843';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $monthInput = $this->option('month');
        if ($monthInput) {
            try {
                $monthStart = Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth();
            } catch (\Throwable $e) {
                $this->error('Invalid --month, expected YYYY-MM');
                return self::FAILURE;
            }
        } else {
            $monthStart = Carbon::now('Asia/Kuwait')->startOfMonth();
        }
        $monthEnd = $monthStart->copy()->endOfMonth();
        $monthLabel = $monthStart->format('F Y');

        $this->info('[' . ($dryRun ? 'DRY-RUN' : 'SEND') . "] Monthly KWIKT2843 report for {$monthLabel}");

        // For each agent: count of distinct PNRs created in KWIKT2843 within the month.
        $rows = DB::table('tasks')
            ->select([
                'agent_id',
                'company_id',
                DB::raw('COUNT(DISTINCT `gds_reference`) AS pnr_count'),
            ])
            ->where('created_by', self::WRONG_OFFICE)
            ->whereNotNull('agent_id')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->groupBy('agent_id', 'company_id')
            ->having('pnr_count', '>', 0)
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No KWIKT2843 PNRs created this month — nothing to report.');
            return self::SUCCESS;
        }

        $this->info("Found {$rows->count()} agent(s) with KWIKT2843 PNRs this month");

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($rows as $row) {
            $agent = Agent::find($row->agent_id);
            if (!$agent) {
                $skipped++;
                continue;
            }

            $phone = $agent->phone_number;
            if (empty($phone)) {
                $this->warn("  skip agent {$agent->id} ({$agent->name}) — no phone");
                $skipped++;
                continue;
            }

            $locale = strtolower($agent->language ?? '') === 'ar' ? 'ar' : 'en';
            $firstName = $agent->first_name ?: ($agent->name ?: 'agent');

            $message = trans('kwikt2843_monthly_report.whatsapp', [
                'name' => $firstName,
                'count' => $row->pnr_count,
                'month' => $monthLabel,
            ], $locale);

            $this->line("  agent {$agent->id} ({$agent->name}, {$phone}, {$locale}) — {$row->pnr_count} PNR(s)");

            if ($dryRun) {
                $sent++;
                continue;
            }

            try {
                $resayil = new ResayilController();
                $response = $resayil->message(
                    phone: $phone,
                    country_code: $agent->country_code ?? '',
                    message: $message,
                    isDummyNumber: false,
                );

                if (!($response['success'] ?? false)) {
                    Log::error('[Kwikt2843MonthlyReport] WhatsApp failed', [
                        'agent_id' => $agent->id,
                        'response' => $response,
                    ]);
                    $this->error('    WhatsApp send failed');
                    $failed++;
                    continue;
                }

                Log::info('[Kwikt2843MonthlyReport] Sent', [
                    'agent_id' => $agent->id,
                    'month' => $monthLabel,
                    'pnr_count' => $row->pnr_count,
                ]);
                $sent++;
            } catch (\Throwable $e) {
                Log::error('[Kwikt2843MonthlyReport] Exception', [
                    'agent_id' => $agent->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error('    Exception: ' . $e->getMessage());
                $failed++;
            }
        }

        $this->newLine();
        $this->info('--- Summary ---');
        $this->line("Sent:    {$sent}");
        $this->line("Skipped: {$skipped}");
        $this->line("Failed:  {$failed}");

        return self::SUCCESS;
    }
}
