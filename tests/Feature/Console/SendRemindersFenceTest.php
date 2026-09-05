<?php

namespace Tests\Feature\Console;

use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\Invoice;
use App\Models\Reminder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Backlog safety fence (2026-09-02 hotfix). config/accounting.php 'reminders.send'
 * (REMINDERS_SEND_ENABLED / REMINDERS_SEND_MAX_AGE_HOURS / REMINDERS_SEND_MAX_PER_RUN) guards
 * process:reminder --proceed against mass-sending the entire accumulated backlog the first time
 * it runs after the reminder-engine-v2 migrations land in production (SendReminders' due-
 * reminders query has no lower bound and no per-run cap). Covers: disabled-by-default (nothing
 * sent, rows untouched, log line present), the send window (old row cancelled with the expired
 * message, fresh row sent), the per-run cap (exactly max_per_run sent, remainder still pending),
 * and the pre-existing --dry-run report mode (prints counts, sends nothing).
 */
class SendRemindersFenceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Agent $agent;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        Company::forgetModuleCache();

        $country = Country::factory()->create();
        $owner = User::factory()->create(['role_id' => Role::COMPANY]);
        $this->company = Company::factory()->create(['user_id' => $owner->id, 'country_id' => $country->id]);
        $branch = Branch::factory()->create(['company_id' => $this->company->id, 'user_id' => User::factory()->create()->id]);
        $agentType = AgentType::firstOrCreate(['name' => 'send-fence-test-type']);
        $this->agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'type_id' => $agentType->id,
            'user_id' => User::factory()->create()->id,
            'phone_number' => '+96599112233',
        ]);
        $this->client = Client::factory()->create(['agent_id' => $this->agent->id, 'phone' => '99887766', 'country_code' => '+965']);
    }

    protected function tearDown(): void
    {
        Company::forgetModuleCache();
        parent::tearDown();
    }

    private function makeReminder(array $overrides = []): Reminder
    {
        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'agent_id' => $this->agent->id,
            'status' => 'unpaid',
            'due_date' => now()->addDays(30),
        ]);

        return Reminder::create(array_merge([
            'company_id' => $this->company->id,
            'target_type' => 'invoice',
            'reminder_kind' => Reminder::KIND_OVERDUE_INVOICE,
            'invoice_id' => $invoice->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'send_to_client' => true,
            'frequency' => 'once',
            'scheduled_at' => now()->subMinute(),
            'status' => 'pending',
            'is_active' => true,
        ], $overrides));
    }

    public function test_disabled_by_default_sends_nothing_and_leaves_rows_untouched(): void
    {
        Http::fake(['*' => Http::response(['status' => 'sent'], 200)]);
        Log::spy();

        $this->assertSame(false, config('accounting.reminders.send.enabled'), 'REMINDERS_SEND_ENABLED must default to false.');

        $reminder = $this->makeReminder();

        $exitCode = Artisan::call('process:reminder', ['--proceed' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertSame('pending', $reminder->fresh()->status, 'A pending row must be left completely untouched while sending is disabled.');
        $this->assertNull($reminder->fresh()->sent_at);

        Log::shouldHaveReceived('info')
            ->withArgs(fn ($message, $context = []) => $message === 'reminder.send_disabled' && ($context['due_count'] ?? null) === 1)
            ->once();

        Http::assertNothingSent();
    }

    public function test_enabled_within_window_sends_fresh_row_and_cancels_expired_row(): void
    {
        config(['accounting.reminders.send.enabled' => true]);
        config(['accounting.reminders.send.max_age_hours' => 48]);
        Http::fake(['*' => Http::response(['status' => 'sent'], 200)]);

        $freshRow = $this->makeReminder(['scheduled_at' => now()->subMinute()]);
        $expiredRow = $this->makeReminder(['scheduled_at' => now()->subHours(72)]);

        Artisan::call('process:reminder', ['--proceed' => true]);

        $freshRow->refresh();
        $expiredRow->refresh();

        $this->assertSame('sent', $freshRow->status);
        $this->assertNotNull($freshRow->sent_at);

        $this->assertSame(Reminder::STATUS_CANCELLED, $expiredRow->status);
        $this->assertSame('expired: outside send window', $expiredRow->error_message);
    }

    public function test_enabled_with_cap_sends_exactly_max_per_run_and_leaves_remainder_pending(): void
    {
        config(['accounting.reminders.send.enabled' => true]);
        config(['accounting.reminders.send.max_per_run' => 2]);
        Http::fake(['*' => Http::response(['status' => 'sent'], 200)]);

        $rows = collect(range(1, 5))->map(fn (int $i) => $this->makeReminder([
            // Oldest-first ordering: row 1 is the oldest, row 5 the newest -- all still due.
            'scheduled_at' => now()->subMinutes(10 - $i),
        ]));

        Artisan::call('process:reminder', ['--proceed' => true]);

        $sent = $rows->map(fn (Reminder $r) => $r->fresh())->filter(fn (Reminder $r) => $r->status === 'sent');
        $stillPending = $rows->map(fn (Reminder $r) => $r->fresh())->filter(fn (Reminder $r) => $r->status === 'pending');

        $this->assertCount(2, $sent, 'Exactly max_per_run rows must be sent.');
        $this->assertCount(3, $stillPending, 'Everything beyond the cap must remain untouched (pending), not cancelled.');

        // Oldest-eligible-first: the two oldest rows (1 and 2) are the ones sent.
        $sentIds = $sent->pluck('id')->sort()->values()->all();
        $this->assertSame([$rows[0]->id, $rows[1]->id], $sentIds);
    }

    public function test_dry_run_report_mode_prints_counts_and_sends_nothing(): void
    {
        Http::fake(['*' => Http::response(['status' => 'sent'], 200)]);

        // Sending stays disabled by default -- --dry-run must still work as the owner's backlog
        // inspection tool regardless of the kill switch. (Artisan::output() reads back empty in
        // this suite -- see e.g. tests/Feature/Accounting/SeedAccountingSerialSchemasTest.php's
        // own docblock for why -- so this asserts the functional guarantee directly: the exit
        // code, that nothing is sent, and that the row is left completely untouched, rather than
        // scraping console text for the printed count.)
        $reminder = $this->makeReminder();

        $exitCode = Artisan::call('process:reminder', ['--dry-run' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertSame('pending', $reminder->fresh()->status);
        $this->assertNull($reminder->fresh()->sent_at);
        Http::assertNothingSent();
    }
}
