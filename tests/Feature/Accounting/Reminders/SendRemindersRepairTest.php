<?php

namespace Tests\Feature\Accounting\Reminders;

use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Reminder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P2.5.I (p2_5-brief.md §P2.5.I) "sender repair" required tests: cap enforcement, error
 * persisted, stale->cancelled. (Generator idempotency, quiet-hours shift, and template-per-kind
 * are covered in their own dedicated test files.)
 */
class SendRemindersRepairTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Agent $agent;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        Company::forgetModuleCache();

        // 2026-09-02 send-fence hotfix: REMINDERS_SEND_ENABLED now defaults FALSE, so --proceed
        // would otherwise exit before even reaching cancelStaleReminders(). This file predates
        // the fence and exercises the sender directly, so it opts back in explicitly.
        config(['accounting.reminders.send.enabled' => true]);

        $country = Country::factory()->create();
        $owner = User::factory()->create(['role_id' => Role::COMPANY]);
        $this->company = Company::factory()->create(['user_id' => $owner->id, 'country_id' => $country->id]);
        $branch = Branch::factory()->create(['company_id' => $this->company->id, 'user_id' => User::factory()->create()->id]);
        $agentType = AgentType::firstOrCreate(['name' => 'p25i-send-repair-type']);
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

    public function test_group_cap_reached_cancels_instead_of_sending(): void
    {
        Http::fake(['*' => Http::response(['status' => 'sent'], 200)]);

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'agent_id' => $this->agent->id,
            'status' => 'unpaid',
            'due_date' => now()->addDays(30), // not overdue -- irrelevant to invoice status guard used by the query.
        ]);

        $groupId = (string) Str::uuid();

        // Two rows sharing one group_id, number_of_reminder = 1 on both (the cap the whole batch
        // was meant to respect). Row 1 already sent; row 2 is due -- the cap (1) is already met,
        // so row 2 must be cancelled, never sent.
        $sentRow = Reminder::create([
            'company_id' => $this->company->id,
            'target_type' => 'invoice',
            'invoice_id' => $invoice->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'group_id' => $groupId,
            'send_to_client' => true,
            'frequency' => 'auto',
            'number_of_reminder' => 1,
            'scheduled_at' => now()->subHour(),
            'status' => 'sent',
            'sent_at' => now()->subHour(),
            'is_active' => true,
        ]);

        $cappedRow = Reminder::create([
            'company_id' => $this->company->id,
            'target_type' => 'invoice',
            'invoice_id' => $invoice->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'group_id' => $groupId,
            'send_to_client' => true,
            'frequency' => 'auto',
            'number_of_reminder' => 1,
            'scheduled_at' => now()->subMinute(),
            'status' => 'pending',
            'is_active' => true,
        ]);

        Artisan::call('process:reminder', ['--proceed' => true]);

        $this->assertSame('sent', $sentRow->fresh()->status);
        $this->assertSame(Reminder::STATUS_CANCELLED, $cappedRow->fresh()->status);
    }

    public function test_group_cap_not_reached_still_sends(): void
    {
        Http::fake(['*' => Http::response(['status' => 'sent'], 200)]);

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'agent_id' => $this->agent->id,
            'status' => 'unpaid',
            'due_date' => now()->addDays(30),
        ]);

        $groupId = (string) Str::uuid();

        Reminder::create([
            'company_id' => $this->company->id,
            'target_type' => 'invoice',
            'invoice_id' => $invoice->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'group_id' => $groupId,
            'send_to_client' => true,
            'frequency' => 'auto',
            'number_of_reminder' => 3,
            'scheduled_at' => now()->subMinute(),
            'status' => 'pending',
            'is_active' => true,
        ]);

        Artisan::call('process:reminder', ['--proceed' => true]);

        $this->assertSame('sent', Reminder::where('group_id', $groupId)->first()->status);
    }

    public function test_error_message_persists_on_a_failed_send(): void
    {
        Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'boom'], 500)]);

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'agent_id' => $this->agent->id,
            'status' => 'unpaid',
            'due_date' => now()->addDays(30),
        ]);

        $reminder = Reminder::create([
            'company_id' => $this->company->id,
            'target_type' => 'invoice',
            'invoice_id' => $invoice->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'send_to_client' => true,
            'frequency' => 'once',
            'scheduled_at' => now()->subMinute(),
            'status' => 'pending',
            'is_active' => true,
        ]);

        Artisan::call('process:reminder', ['--proceed' => true]);

        $reminder->refresh();
        $this->assertSame('failed', $reminder->status);
        $this->assertNotNull($reminder->error_message);
        $this->assertNotSame('', $reminder->error_message);
    }

    public function test_stale_pending_invoice_reminder_is_cancelled_once_paid(): void
    {
        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'agent_id' => $this->agent->id,
            'status' => 'paid', // already resolved before the reminder ever fired.
            'due_date' => now()->subDays(5),
        ]);

        $reminder = Reminder::create([
            'company_id' => $this->company->id,
            'target_type' => 'invoice',
            'reminder_kind' => Reminder::KIND_OVERDUE_INVOICE,
            'invoice_id' => $invoice->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'send_to_client' => true,
            'frequency' => 'once',
            'scheduled_at' => now()->addHour(), // not yet due -- still scanned by the stale pass.
            'status' => 'pending',
            'is_active' => true,
        ]);

        Artisan::call('process:reminder', ['--proceed' => true]);

        $this->assertSame(Reminder::STATUS_CANCELLED, $reminder->fresh()->status);
    }

    public function test_stale_ticketing_deadline_reminder_is_cancelled_once_task_leaves_hold(): void
    {
        $supplier = Supplier::factory()->create();
        $task = Task::factory()->create([
            'company_id' => $this->company->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'supplier_id' => $supplier->id,
            'status' => 'issued', // no longer on hold/confirmed -- already ticketed.
            'deadline_at' => now()->addHour(),
        ]);

        $reminder = Reminder::create([
            'company_id' => $this->company->id,
            'target_type' => 'task',
            'reminder_kind' => Reminder::KIND_TICKETING_DEADLINE,
            'task_id' => $task->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'send_to_agent' => true,
            'frequency' => 'once',
            'scheduled_at' => now()->addMinutes(30),
            'status' => 'pending',
            'is_active' => true,
        ]);

        Artisan::call('process:reminder', ['--proceed' => true]);

        $this->assertSame(Reminder::STATUS_CANCELLED, $reminder->fresh()->status);
    }

    public function test_stale_payment_link_uninvoiced_reminder_is_cancelled_once_invoiced(): void
    {
        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'agent_id' => $this->agent->id,
        ]);

        $payment = Payment::factory()->create([
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'account_id' => null,
            'created_by' => $this->agent->user_id,
            'status' => 'completed',
            'invoice_id' => $invoice->id, // already invoiced since the reminder was created.
        ]);

        $reminder = Reminder::create([
            'company_id' => $this->company->id,
            'target_type' => 'payment',
            'reminder_kind' => Reminder::KIND_PAYMENT_LINK_UNINVOICED,
            'payment_id' => $payment->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'send_to_agent' => true,
            'frequency' => 'once',
            'scheduled_at' => now()->addHour(),
            'status' => 'pending',
            'is_active' => true,
        ]);

        Artisan::call('process:reminder', ['--proceed' => true]);

        $this->assertSame(Reminder::STATUS_CANCELLED, $reminder->fresh()->status);
    }

    public function test_non_stale_pending_reminder_is_left_alone(): void
    {
        Http::fake(['*' => Http::response(['status' => 'sent'], 200)]);

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'agent_id' => $this->agent->id,
            'status' => 'unpaid', // still open -- not stale.
            'due_date' => now()->subDays(5),
        ]);

        $reminder = Reminder::create([
            'company_id' => $this->company->id,
            'target_type' => 'invoice',
            'reminder_kind' => Reminder::KIND_OVERDUE_INVOICE,
            'invoice_id' => $invoice->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'send_to_client' => true,
            'frequency' => 'once',
            'scheduled_at' => now()->addHour(), // not yet due.
            'status' => 'pending',
            'is_active' => true,
        ]);

        Artisan::call('process:reminder', ['--proceed' => true]);

        // Neither sent (not due yet) nor cancelled (not stale) -- stays pending.
        $this->assertSame('pending', $reminder->fresh()->status);
    }
}
