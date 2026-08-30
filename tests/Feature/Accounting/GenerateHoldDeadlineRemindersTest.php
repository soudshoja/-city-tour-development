<?php

namespace Tests\Feature\Accounting;

use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\Reminder;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * W6.U "Reminders" (owner addition, 2026-08-28). Tests (hold/follow-up) item 4:
 * "reminder:generate-deadlines run against a confirmed task with deadline_at in 26h and offsets
 * '24,2' -> exactly one Reminder row scheduled at deadline_at - 24h is currently due-creatable
 * (the -2h row is also created, scheduled later); re-running the command immediately -> zero new
 * rows (idempotency assert). SendReminders/process:reminder --proceed picks up the due row and
 * sends via the new buildMessage() branch (assert status=sent, not swallowed as failed from a
 * null message)."
 */
class GenerateHoldDeadlineRemindersTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Agent $agent;
    private Client $client;
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        Company::forgetModuleCache();

        $country = Country::factory()->create();
        $companyOwner = User::factory()->create(['role_id' => Role::COMPANY]);
        $this->company = Company::factory()->create(['user_id' => $companyOwner->id, 'country_id' => $country->id]);
        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $this->company->id, 'user_id' => $branchOwner->id]);
        $agentType = AgentType::firstOrCreate(['name' => 'w6u-reminder-type']);
        $this->agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'type_id' => $agentType->id,
            'user_id' => User::factory()->create()->id,
            'phone_number' => '+96599112233',
        ]);
        $this->client = Client::factory()->create(['agent_id' => $this->agent->id, 'phone' => '99887766', 'country_code' => '+965']);

        $supplier = Supplier::factory()->create();
        $this->task = Task::factory()->create([
            'company_id' => $this->company->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'supplier_id' => $supplier->id,
            'status' => 'confirmed',
            'deadline_at' => now()->addHours(26),
        ]);
    }

    protected function tearDown(): void
    {
        Company::forgetModuleCache();
        parent::tearDown();
    }

    public function test_generate_deadlines_creates_one_row_per_offset(): void
    {
        Setting::updateOrCreate(
            ['key' => 'accounting.hold_reminder_offsets_hours', 'company_id' => $this->company->id],
            ['value' => '24,2', 'type' => 'string']
        );

        Artisan::call('reminder:generate-deadlines');

        $rows = Reminder::where('task_id', $this->task->id)->where('reminder_kind', 'ticketing_deadline')->get();

        $this->assertCount(2, $rows);

        $deadline = \Carbon\Carbon::parse($this->task->deadline_at);
        $offset24 = $rows->first(fn (Reminder $r) => \Carbon\Carbon::parse($r->scheduled_at)->equalTo($deadline->copy()->subHours(24)));
        $offset2 = $rows->first(fn (Reminder $r) => \Carbon\Carbon::parse($r->scheduled_at)->equalTo($deadline->copy()->subHours(2)));

        $this->assertNotNull($offset24, 'The 24h-before offset row must exist.');
        $this->assertNotNull($offset2, 'The 2h-before offset row must exist.');
        // deadline_at is 26h out: deadline-24h = now+2h, deadline-2h = now+24h -- the 24h-before
        // notice always lands SOONER in absolute time than the 2h-before one, regardless of
        // whether either has actually come due yet (a separate scenario, covered by
        // test_send_reminders_picks_up_the_due_row_and_sends_via_new_branch() below).
        $this->assertTrue(\Carbon\Carbon::parse($offset24->scheduled_at)->lessThan(\Carbon\Carbon::parse($offset2->scheduled_at)));
        $this->assertSame('task', $offset24->target_type);
        $this->assertTrue((bool) $offset24->send_to_agent);
        $this->assertFalse((bool) $offset24->send_to_client, 'hold_client_nudge defaults to false.');
    }

    public function test_generate_deadlines_is_idempotent_on_a_second_run(): void
    {
        Artisan::call('reminder:generate-deadlines');
        $countAfterFirst = Reminder::where('task_id', $this->task->id)->count();

        Artisan::call('reminder:generate-deadlines');
        $countAfterSecond = Reminder::where('task_id', $this->task->id)->count();

        $this->assertSame(2, $countAfterFirst);
        $this->assertSame($countAfterFirst, $countAfterSecond, 'A second run must create zero new rows.');
    }

    public function test_generate_deadlines_honours_hold_client_nudge_option(): void
    {
        Setting::updateOrCreate(
            ['key' => 'accounting.hold_client_nudge', 'company_id' => $this->company->id],
            ['value' => true, 'type' => 'boolean']
        );

        Artisan::call('reminder:generate-deadlines');

        $row = Reminder::where('task_id', $this->task->id)->first();
        $this->assertTrue((bool) $row->send_to_client);
    }

    public function test_send_reminders_picks_up_the_due_row_and_sends_via_new_branch(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'sent'], 200),
        ]);

        // A deadline only 3h out with the default "24,2" offsets guarantees the 24h-before
        // notice is already overdue (scheduled_at = deadline - 24h = now - 21h) while the 2h-
        // before one is not yet (scheduled_at = now + 1h) -- exactly the "one due, one not"
        // shape the brief's own worked example describes.
        $dueTask = Task::factory()->create([
            'company_id' => $this->company->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'supplier_id' => $this->task->supplier_id,
            'status' => 'confirmed',
            'deadline_at' => now()->addHours(3),
        ]);

        Artisan::call('reminder:generate-deadlines');

        $dueRow = Reminder::where('task_id', $dueTask->id)
            ->where('scheduled_at', '<=', now())
            ->first();
        $this->assertNotNull($dueRow, 'The 24h-before row must already be due given a deadline only 3h out.');

        $notYetDueRow = Reminder::where('task_id', $dueTask->id)->where('scheduled_at', '>', now())->first();
        $this->assertNotNull($notYetDueRow, 'The 2h-before row must exist but not be due yet.');

        Artisan::call('process:reminder', ['--proceed' => true]);

        $dueRow->refresh();
        $notYetDueRow->refresh();
        $this->assertSame('sent', $dueRow->status, 'buildMessage() must no longer return null for target_type=task, or this falls back to failed.');
        $this->assertNotNull($dueRow->sent_at);
        $this->assertSame('pending', $notYetDueRow->status, 'A not-yet-due row must not be sent early.');
    }

    public function test_send_reminders_query_actually_selects_task_kind_rows(): void
    {
        // Regression guard for the exact gap w6-brief.md flags: the due-reminders query's
        // whereHas('payment')/orWhereHas('invoice') pair must ALSO accept target_type=task rows
        // (which carry neither invoice_id nor payment_id) -- without the fix, this reminder is
        // silently never selected as "due" at all, regardless of buildMessage().
        Http::fake(['*' => Http::response(['status' => 'sent'], 200)]);

        $reminder = Reminder::create([
            'target_type' => 'task',
            'reminder_kind' => 'ticketing_deadline',
            'task_id' => $this->task->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'send_to_agent' => true,
            'send_to_client' => false,
            'frequency' => 'once',
            'scheduled_at' => now()->subMinute(),
            'status' => 'pending',
            'is_active' => true,
        ]);

        Artisan::call('process:reminder', ['--proceed' => true]);

        $this->assertSame('sent', $reminder->fresh()->status);
    }
}
