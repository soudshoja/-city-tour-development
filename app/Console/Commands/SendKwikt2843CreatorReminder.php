<?php

namespace App\Console\Commands;

use App\Http\Controllers\ResayilController;
use App\Models\Agent;
use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendKwikt2843CreatorReminder extends Command
{
    protected $signature = 'reminders:kwikt2843-creator
                            {--dry-run : Preview without sending or recording}
                            {--limit= : Optional safety cap on PNRs processed per run}';

    protected $description = 'WhatsApp reminder to agents who CREATED a PNR in office KWIKT2843 while the task is still uninvoiced. One message per PNR, sent only once.';

    private const REMINDER_TYPE = 'kwikt2843_creator';
    private const WRONG_OFFICE = 'KWIKT2843';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $this->info('[' . ($dryRun ? 'DRY-RUN' : 'SEND') . "] Scanning uninvoiced tasks created in office " . self::WRONG_OFFICE);

        $query = Task::query()
            ->where('tasks.created_by', self::WRONG_OFFICE)
            ->whereIn('tasks.status', ['issued', 'reissued'])
            ->whereNotNull('tasks.agent_id')
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('invoice_details')
                    ->whereColumn('invoice_details.task_id', 'tasks.id');
            })
            ->whereNotNull('tasks.gds_reference')
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('pnr_reminders')
                    ->whereColumn('pnr_reminders.reference', 'tasks.gds_reference')
                    ->whereColumn('pnr_reminders.company_id', 'tasks.company_id')
                    ->where('pnr_reminders.type', self::REMINDER_TYPE);
            });

        // One row per (company_id, gds_reference) — a PNR can have multiple tasks (passengers / EMDs)
        $rows = $query
            ->select([
                'tasks.company_id',
                'tasks.gds_reference AS reference',
                DB::raw('MIN(tasks.id) AS task_id'),
                DB::raw('MIN(tasks.agent_id) AS agent_id'),
            ])
            ->groupBy('tasks.company_id', 'tasks.gds_reference')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No matching PNRs.');
            return self::SUCCESS;
        }

        $this->info("Found {$rows->count()} PNR(s)");

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

            $message = trans('kwikt2843_creator_reminder.whatsapp', [
                'name' => $firstName,
                'reference' => $row->reference,
            ], $locale);

            $this->line("  PNR {$row->reference} → agent {$agent->id} ({$agent->name}, {$phone}, {$locale})");

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
                    Log::error('[Kwikt2843CreatorReminder] WhatsApp failed', [
                        'agent_id' => $agent->id,
                        'reference' => $row->reference,
                        'response' => $response,
                    ]);
                    $this->error('    WhatsApp send failed');
                    $failed++;
                    continue;
                }

                DB::table('pnr_reminders')->insert([
                    'company_id' => $row->company_id,
                    'reference' => $row->reference,
                    'type' => self::REMINDER_TYPE,
                    'agent_id' => $agent->id,
                    'sent_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                Log::info('[Kwikt2843CreatorReminder] Sent', [
                    'agent_id' => $agent->id,
                    'reference' => $row->reference,
                ]);
                $sent++;
            } catch (\Throwable $e) {
                Log::error('[Kwikt2843CreatorReminder] Exception', [
                    'agent_id' => $agent->id,
                    'reference' => $row->reference,
                    'error' => $e->getMessage(),
                ]);
                $this->error('    Exception: ' . $e->getMessage());
                $failed++;
            }
        }

        $this->newLine();
        $this->info("--- Summary ---");
        $this->line("Sent:    {$sent}");
        $this->line("Skipped: {$skipped}");
        $this->line("Failed:  {$failed}");

        return self::SUCCESS;
    }
}
