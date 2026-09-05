<?php

namespace App\Console\Commands;

use App\Http\Controllers\ResayilController;
use App\Mail\NotificationMail;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Reminder;
use App\Services\Accounting\StatementOptions;
use App\Services\Accounting\StatementService;
use App\Services\Reminders\ReminderMessageRegistry;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * P2.5.I (p2_5-brief.md §P2.5.I) "sender repair": scheduled `everyMinute()` with
 * `withoutOverlapping()` (see routes/console.php's own P2.5.I block); `number_of_reminder` now
 * enforced as a real per-group send cap (see {@see self::groupCapReached()}'s own docblock for
 * the exact rule taken -- the brief names the cap but does not spell out its unit, so this is a
 * documented interpretation, not a re-derivation of an existing rule); `error_message` now
 * persists (the column exists as of the P2.5.I migration -- {@see self::markAsFailed()} itself is
 * unchanged, only the model/schema underneath it now actually keeps what it writes);
 * `buildMessage()` is replaced by {@see ReminderMessageRegistry} (template-per-kind, lang files);
 * a new {@see self::cancelStaleReminders()} pre-step marks a still-`pending` row `cancelled` once
 * its target has already resolved, instead of leaving it to fire forever or fail forever.
 */
class SendReminders extends Command
{
    protected $signature = 'process:reminder
                            {--dry-run : Run the command in dry-run mode}
                            {--proceed : Execute the command and make changes}';

    protected $description = 'Process and send due reminders to clients and agents';

    public function handle(ReminderMessageRegistry $registry)
    {
        $dryRun = $this->option('dry-run');
        $proceed = $this->option('proceed');

        if (! $dryRun && ! $proceed) {
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
            if ($proceed) {
                // Safety fence (2026-09-02 hotfix): default-OFF kill switch. Checked BEFORE
                // cancelStaleReminders() (which mutates rows) so a disabled sender genuinely
                // touches nothing -- not even the stale-cancel pass.
                if (! config('accounting.reminders.send.enabled', false)) {
                    $dueCount = $this->dueRemindersQuery()->count();
                    $message = "reminder send disabled by REMINDERS_SEND_ENABLED; {$dueCount} due rows would have been processed";
                    $this->warn($message);
                    Log::info('reminder.send_disabled', ['due_count' => $dueCount]);

                    return 0;
                }

                $cancelled = $this->cancelStaleReminders();
                if ($cancelled > 0) {
                    $this->info("Cancelled {$cancelled} stale pending reminder(s) whose target already resolved.");
                    Log::info('reminder.stale_cancelled', ['count' => $cancelled]);
                }
            }

            $dueReminders = $this->dueRemindersQuery()
                ->orderBy('scheduled_at')
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
                        'kind' => $reminder->reminder_kind ?? '-',
                        'target_type' => strtoupper($reminder->target_type),
                        'invoice_id' => $reminder->invoice_id ?? '-',
                        'payment_id' => $reminder->payment_id ?? '-',
                        'client' => strtoupper($reminder->client?->full_name ?? 'N/A'),
                        'agent' => strtoupper($reminder->agent?->name ?? 'N/A'),
                        'channel' => $reminder->channel ?? 'whatsapp',
                        'send_to' => implode(', ', array_filter([
                            $reminder->send_to_client ? 'Client' : null,
                            $reminder->send_to_agent ? 'Agent' : null,
                        ])) ?: '-',
                        'scheduled_at' => Carbon::parse($reminder->scheduled_at)->format('M d, Y h:i A'),
                        'message' => $reminder->message ? Str::limit($reminder->message, 30) : '-',
                    ];
                })->toArray();

                $this->table(
                    ['ID', 'Group ID', 'Kind', 'Target', 'Invoice ID', 'Payment ID', 'Client', 'Agent', 'Channel', 'Send To', 'Scheduled At', 'Message'],
                    $tableData
                );

                $this->newLine();
                $this->info('To actually send these reminders, run:');
                $this->info('  php artisan process:reminder --proceed');
                $this->newLine();

                return 0;
            }

            if ($proceed) {
                $dueReminders = $this->applySendWindow($dueReminders);
                $dueReminders = $this->applyPerRunCap($dueReminders);

                $this->processReminders($dueReminders, $registry);
            }

        } catch (\Exception $e) {
            Log::error('Error processing reminders: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error('Failed to process reminders. Check logs for details.');
            $this->error($e->getMessage());

            return 1;
        }

        return 0;
    }

    /**
     * Shared due-reminders WHERE clause (status/target-type/is_active/scheduled_at<=now), reused
     * by the disabled-sender count and the actual due-reminders fetch so the two never drift.
     */
    private function dueRemindersQuery()
    {
        return Reminder::where('status', 'pending')
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
                    ->orWhere('target_type', 'task')
                // P2.5.I: 'client' (statement_balance) and 'agent' (commission_unearned)
                // targets carry no invoice_id/payment_id either -- same gap, same fix. Both
                // kinds' own generator/listener is itself the sole writer and is
                // idempotent (dedupe_key), so no extra open/closed guard is needed here.
                    ->orWhereIn('target_type', ['client', 'agent'])
                    ->orWhere('reminder_kind', Reminder::KIND_PAYMENT_LINK_UNINVOICED);
            })
            ->where('is_active', true)
            ->where('scheduled_at', '<=', Carbon::now());
    }

    /**
     * Safety fence (2026-09-02 hotfix): a pending row already past due but scheduled further back
     * than `accounting.reminders.send.max_age_hours` is stale backlog, not "still worth sending"
     * -- e.g. the entire accumulated backlog that would exist the first time this command runs
     * after the reminder-engine-v2 migrations land in production. Cancelled (reusing the existing
     * STATUS_CANCELLED + error_message columns, no new enum value) rather than sent.
     *
     * @param  \Illuminate\Support\Collection<int, Reminder>  $dueReminders
     * @return \Illuminate\Support\Collection<int, Reminder> the remaining, in-window reminders.
     */
    private function applySendWindow($dueReminders)
    {
        $maxAgeHours = (int) config('accounting.reminders.send.max_age_hours', 48);
        $cutoff = Carbon::now()->subHours($maxAgeHours);

        [$expired, $eligible] = $dueReminders->partition(
            fn (Reminder $reminder) => Carbon::parse($reminder->scheduled_at)->lt($cutoff)
        );

        if ($expired->isNotEmpty()) {
            foreach ($expired as $reminder) {
                $reminder->update([
                    'status' => Reminder::STATUS_CANCELLED,
                    'error_message' => 'expired: outside send window',
                ]);
            }

            $this->info("Expired {$expired->count()} reminder(s) outside the {$maxAgeHours}h send window; cancelled.");
            Log::info('reminder.expired_outside_window', [
                'count' => $expired->count(),
                'max_age_hours' => $maxAgeHours,
            ]);
        }

        return $eligible->values();
    }

    /**
     * Safety fence (2026-09-02 hotfix): hard per-run cap on how many reminders one --proceed
     * invocation will send, oldest-eligible-first (the incoming collection is already ordered by
     * scheduled_at ASC from the due-reminders query). Independent of groupCapReached()'s own
     * per-group `number_of_reminder` cap -- left untouched. Anything beyond the cap is simply left
     * `pending` for the next run, never touched here.
     *
     * @param  \Illuminate\Support\Collection<int, Reminder>  $dueReminders
     * @return \Illuminate\Support\Collection<int, Reminder>
     */
    private function applyPerRunCap($dueReminders)
    {
        $maxPerRun = (int) config('accounting.reminders.send.max_per_run', 50);

        if ($maxPerRun <= 0 || $dueReminders->count() <= $maxPerRun) {
            return $dueReminders;
        }

        $remaining = $dueReminders->count() - $maxPerRun;
        $this->info("capped: {$remaining} remaining");
        Log::info('reminder.send_capped', [
            'processed' => $maxPerRun,
            'remaining' => $remaining,
        ]);

        return $dueReminders->take($maxPerRun);
    }

    /**
     * P2.5.I: a pending row whose target already resolved before it ever fired (paid invoice,
     * settled payment, an already-invoiced payment link, a ticketed/void task, a client whose
     * balance is now clear) is marked `cancelled` rather than left to sit forever or eventually
     * fail against a target that no longer needs the nudge. Runs before the due-reminders query
     * above (only under --proceed, never in --dry-run) so a stale row never even reaches the
     * "due" table. Bounded to reminders scheduled within the last 90 days -- the practical window
     * any of this wave's generators would still be sweeping; a genuinely ancient stuck row (the
     * pre-existing "37 pending-stuck" the 2026-08-29 audit found) is left for an operator to
     * triage rather than silently mass-cancelled by this pass.
     *
     * @return int number of rows cancelled.
     */
    private function cancelStaleReminders(): int
    {
        $cancelled = 0;

        Reminder::where('status', 'pending')
            ->where('is_active', true)
            ->where('scheduled_at', '>=', Carbon::now()->subDays(90))
            ->with(['invoice', 'payment', 'task', 'client'])
            ->chunkById(200, function ($reminders) use (&$cancelled) {
                foreach ($reminders as $reminder) {
                    if ($this->isStale($reminder)) {
                        $reminder->update(['status' => Reminder::STATUS_CANCELLED]);
                        $cancelled++;
                    }
                }
            });

        return $cancelled;
    }

    private function isStale(Reminder $reminder): bool
    {
        return match (true) {
            $reminder->reminder_kind === Reminder::KIND_PAYMENT_LINK_UNINVOICED => $reminder->payment !== null && $reminder->payment->invoice_id !== null,
            $reminder->target_type === 'invoice' => $reminder->invoice !== null && in_array($reminder->invoice->status, ['paid', 'paid by refund'], true),
            $reminder->target_type === 'payment' && $reminder->reminder_kind !== Reminder::KIND_PAYMENT_LINK_UNINVOICED => $reminder->payment !== null && $reminder->payment->status === 'completed',
            $reminder->reminder_kind === Reminder::KIND_TICKETING_DEADLINE => $reminder->task !== null && ! in_array($reminder->task->status, ['on hold', 'confirmed'], true),
            $reminder->reminder_kind === Reminder::KIND_STATEMENT_BALANCE => $this->statementBalanceCleared($reminder),
            default => false, // 'agent'/commission_unearned and 'custom' rows are never auto-stale.
        };
    }

    private function statementBalanceCleared(Reminder $reminder): bool
    {
        if ($reminder->client === null) {
            return false;
        }

        $companyId = (int) ($reminder->company_id ?? 0);
        if ($companyId === 0) {
            return false;
        }

        $statement = app(StatementService::class)->generate($companyId, StatementService::PARTY_CLIENT, $reminder->client_id, Carbon::now());
        $netOutstanding = (float) ($statement['totals']['net_outstanding'] ?? 0);

        return $netOutstanding <= StatementOptions::unsettledTolerance();
    }

    private function processReminders($dueReminders, ReminderMessageRegistry $registry)
    {
        $resayil = new ResayilController;
        $successCount = 0;
        $failedCount = 0;
        $cancelledCount = 0;

        foreach ($dueReminders as $reminder) {
            $this->info("Processing Reminder ID: {$reminder->id} (Group: {$reminder->group_id})");
            Log::info("Processing reminder ID: {$reminder->id}", [
                'group_id' => $reminder->group_id,
                'reminder_kind' => $reminder->reminder_kind,
                'target_type' => $reminder->target_type,
                'invoice_id' => $reminder->invoice_id,
                'payment_id' => $reminder->payment_id,
                'scheduled_at' => $reminder->scheduled_at,
            ]);

            if ($this->groupCapReached($reminder)) {
                $reminder->update(['status' => Reminder::STATUS_CANCELLED]);
                $cancelledCount++;
                $this->info('  — Group already reached its number_of_reminder cap; cancelled instead of sent.');
                $this->newLine();

                continue;
            }

            try {
                $client = Client::where('id', $reminder->client_id)->first();
                $agent = Agent::where('id', $reminder->agent_id)->first();

                if (! $client) {
                    $this->error("  ✗ Client not found for reminder ID: {$reminder->id}");
                    $this->markAsFailed($reminder, 'Client not found');
                    $failedCount++;

                    continue;
                }

                if (! $agent) {
                    $this->error("  ✗ Agent not found for reminder ID: {$reminder->id}");
                    $this->markAsFailed($reminder, 'Agent not found');
                    $failedCount++;

                    continue;
                }

                $clientPhone = preg_replace('/[^0-9]/', '', $client->phone ?? '');
                $clientCountryCode = $client->country_code;

                $agentPhone = preg_replace('/[^0-9]/', '', $agent->phone_number ?? '');

                $messageData = $registry->render($reminder);

                if (! $messageData) {
                    $this->error('  ✗ Failed to build message - missing template data');
                    $this->markAsFailed($reminder, 'Failed to build message - missing template data');
                    $failedCount++;

                    continue;
                }

                $company = $agent->branch?->company;
                $channel = $reminder->channel ?: 'whatsapp';

                $clientResult = ['success' => true];
                $agentResult = ['success' => true];

                if ($reminder->send_to_client && $messageData['client_message']) {
                    $clientResult = $this->deliver(
                        $resayil, $channel, $clientPhone, $clientCountryCode, $client->email ?? null,
                        $messageData['client_message'], $messageData['subject'], $company,
                        $reminder->client_id, $reminder->agent_id, $reminder->invoice_id ?? $reminder->payment_id,
                    );
                    Log::info("Reminder ID {$reminder->id} - Sent to CLIENT", $clientResult);
                    $this->info('  → Client: '.($clientResult['success'] ? '✓ Sent' : '✗ Failed'));
                }

                if ($reminder->send_to_agent && $messageData['agent_message']) {
                    $agentResult = $this->deliver(
                        $resayil, $channel, $agentPhone, '', $agent->email ?? null,
                        $messageData['agent_message'], $messageData['subject'], $company,
                        $reminder->client_id, $reminder->agent_id, $reminder->invoice_id ?? $reminder->payment_id,
                    );
                    Log::info("Reminder ID {$reminder->id} - Sent to AGENT", $agentResult);
                    $this->info('  → Agent: '.($agentResult['success'] ? '✓ Sent' : '✗ Failed'));
                }

                $clientSuccess = ! $reminder->send_to_client || ! $messageData['client_message'] || $clientResult['success'];
                $agentSuccess = ! $reminder->send_to_agent || ! $messageData['agent_message'] || $agentResult['success'];

                if ($clientSuccess && $agentSuccess) {
                    $reminder->update([
                        'status' => 'sent',
                        'sent_at' => Carbon::now(),
                    ]);
                    $successCount++;
                    $this->info('  ✓ Marked as sent');
                    Log::info("Reminder ID {$reminder->id} marked as sent.");
                } else {
                    $errorMessage = $this->buildErrorMessage($clientResult, $agentResult, $reminder);
                    $this->markAsFailed($reminder, $errorMessage);
                    $failedCount++;
                    $this->error('  ✗ Marked as failed');
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
        $this->info("  — Cancelled (cap reached): {$cancelledCount}");
        $this->info("  Total:     {$dueReminders->count()}");

        Log::info('Processing scheduled reminders completed', [
            'success' => $successCount,
            'failed' => $failedCount,
            'cancelled' => $cancelledCount,
            'total' => $dueReminders->count(),
        ]);
    }

    /**
     * P2.5.I: "enforce number_of_reminder as a real cap" -- the brief names the column but not
     * its exact enforcement unit. Interpretation taken here (documented, not silently assumed):
     * `number_of_reminder` is the ORIGINAL author's stated intent for the whole batch a
     * `group_id` represents (ReminderController::store()'s own reminderCount loop already writes
     * the SAME number_of_reminder value onto every row it creates for one group) -- so the cap is
     * enforced per group, at send time: once `number_of_reminder` rows in a group have already
     * reached `status = sent`, any further pending row in that SAME group is cancelled instead of
     * sent, regardless of who created it or how many rows exist. A row with no group_id (should
     * not happen given Reminder::boot() always assigns one) is never capped by this check.
     */
    private function groupCapReached(Reminder $reminder): bool
    {
        if (empty($reminder->group_id)) {
            return false;
        }

        $cap = max(1, (int) ($reminder->number_of_reminder ?? 1));
        $alreadySent = Reminder::where('group_id', $reminder->group_id)
            ->where('status', 'sent')
            ->count();

        return $alreadySent >= $cap;
    }

    /**
     * Channel-aware delivery for one audience (client or agent). WhatsApp uses the existing
     * ResayilController::shareReminder() path unchanged; email is the P2.5.I addition, routed
     * through the codebase's EXISTING generic mail channel ({@see \App\Mail\NotificationMail}) --
     * "email via the existing mail channel" per the brief, not a new Mailable per kind.
     *
     * @return array{success: bool, error?: string}
     */
    private function deliver(
        ResayilController $resayil,
        string $channel,
        string $phone,
        string $countryCode,
        ?string $email,
        string $message,
        string $subject,
        ?Company $company,
        ?int $clientId,
        ?int $agentId,
        ?int $referenceId,
    ): array {
        $wantsWhatsapp = in_array($channel, ['whatsapp', 'both'], true);
        $wantsEmail = in_array($channel, ['email', 'both'], true);

        $ok = true;
        $errors = [];

        if ($wantsWhatsapp) {
            if (empty($phone)) {
                $ok = false;
                $errors[] = 'phone number is missing';
            } else {
                $result = $resayil->shareReminder($phone, $countryCode, $message, $clientId, $agentId, $referenceId);
                if (! ($result['success'] ?? false)) {
                    $ok = false;
                    $errors[] = $result['error'] ?? 'WhatsApp send failed';
                }
            }
        }

        if ($wantsEmail) {
            if (empty($email)) {
                $ok = false;
                $errors[] = 'email address is missing';
            } else {
                try {
                    Mail::to($email)->send(new NotificationMail(['title' => $subject, 'message' => $message], $company));
                } catch (\Throwable $e) {
                    $ok = false;
                    $errors[] = 'email send failed: '.$e->getMessage();
                }
            }
        }

        return $errors === [] ? ['success' => $ok] : ['success' => $ok, 'error' => implode('; ', $errors)];
    }

    private function buildErrorMessage(array $clientResult, array $agentResult, Reminder $reminder): string
    {
        $errors = [];

        if ($reminder->send_to_client && ! $clientResult['success']) {
            $errors[] = 'Client: '.($clientResult['error'] ?? 'Unknown error');
        }

        if ($reminder->send_to_agent && ! $agentResult['success']) {
            $errors[] = 'Agent: '.($agentResult['error'] ?? 'Unknown error');
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
