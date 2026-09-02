<?php

namespace Tests\Unit\Services\Reminders;

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
use App\Services\Reminders\ReminderMessageRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P2.5.I (p2_5-brief.md §P2.5.I) required test: "template renders per kind". One assertion per
 * reminder_kind the registry knows about, plus the two legacy (null-kind) fallback paths.
 */
class ReminderMessageRegistryTest extends TestCase
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
        $agentType = AgentType::firstOrCreate(['name' => 'p25i-registry-type']);
        $this->agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'type_id' => $agentType->id,
            'user_id' => User::factory()->create()->id,
        ]);
        $this->client = Client::factory()->create(['agent_id' => $this->agent->id]);
    }

    protected function tearDown(): void
    {
        Company::forgetModuleCache();
        parent::tearDown();
    }

    private function registry(): ReminderMessageRegistry
    {
        return app(ReminderMessageRegistry::class);
    }

    public function test_renders_overdue_invoice_kind(): void
    {
        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'agent_id' => $this->agent->id,
            'invoice_number' => 'INV-9001',
            'amount' => 250,
            'currency' => 'KWD',
            'due_date' => now()->subDays(3),
        ]);

        $reminder = Reminder::create([
            'company_id' => $this->company->id,
            'target_type' => 'invoice',
            'reminder_kind' => Reminder::KIND_OVERDUE_INVOICE,
            'invoice_id' => $invoice->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'send_to_client' => true,
            'send_to_agent' => true,
            'frequency' => 'once',
            'scheduled_at' => now(),
            'status' => 'pending',
            'is_active' => true,
        ]);

        $result = $this->registry()->render($reminder);

        $this->assertNotNull($result);
        $this->assertStringContainsString('INV-9001', $result['client_message']);
        $this->assertStringContainsString('250', $result['client_message']);
        $this->assertStringContainsString('INV-9001', $result['agent_message']);
        $this->assertStringContainsString('INV-9001', $result['subject']);
    }

    public function test_renders_payment_due_fallback_when_kind_is_null(): void
    {
        $payment = Payment::factory()->create([
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $this->agent->user_id,
            'voucher_number' => 'PV-7002',
            'amount' => 75,
            'currency' => 'KWD',
        ]);

        $reminder = Reminder::create([
            'company_id' => $this->company->id,
            'target_type' => 'payment',
            'reminder_kind' => null,
            'payment_id' => $payment->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'send_to_client' => true,
            'send_to_agent' => false,
            'frequency' => 'once',
            'scheduled_at' => now(),
            'status' => 'pending',
            'is_active' => true,
        ]);

        $result = $this->registry()->render($reminder);

        $this->assertNotNull($result);
        $this->assertStringContainsString('PV-7002', $result['client_message']);
        // Distinct wording from payment_link_uninvoiced -- this is a "please pay" nudge.
        $this->assertStringContainsString('outstanding payment', $result['client_message']);
    }

    public function test_renders_ticketing_deadline_kind(): void
    {
        $supplier = Supplier::factory()->create();
        $task = Task::factory()->create([
            'company_id' => $this->company->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'supplier_id' => $supplier->id,
            'status' => 'confirmed',
            'reference' => 'PNR123',
            'deadline_at' => now()->addHours(5),
        ]);

        $reminder = Reminder::create([
            'company_id' => $this->company->id,
            'target_type' => 'task',
            'reminder_kind' => Reminder::KIND_TICKETING_DEADLINE,
            'task_id' => $task->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'send_to_agent' => true,
            'send_to_client' => false,
            'frequency' => 'once',
            'scheduled_at' => now(),
            'status' => 'pending',
            'is_active' => true,
        ]);

        $result = $this->registry()->render($reminder);

        $this->assertNotNull($result);
        $this->assertStringContainsString('PNR123', $result['agent_message']);
    }

    public function test_renders_statement_balance_kind(): void
    {
        $reminder = Reminder::create([
            'company_id' => $this->company->id,
            'target_type' => 'client',
            'reminder_kind' => Reminder::KIND_STATEMENT_BALANCE,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'send_to_client' => true,
            'send_to_agent' => true,
            'frequency' => 'once',
            'scheduled_at' => now(),
            'status' => 'pending',
            'is_active' => true,
        ]);

        $result = $this->registry()->render($reminder);

        $this->assertNotNull($result);
        $this->assertStringContainsString('balance', $result['client_message']);
        $this->assertStringContainsString('balance', $result['agent_message']);
    }

    public function test_renders_commission_unearned_kind_agent_only(): void
    {
        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'agent_id' => $this->agent->id,
            'invoice_number' => 'INV-5005',
        ]);

        $reminder = Reminder::create([
            'company_id' => $this->company->id,
            'target_type' => 'agent',
            'reminder_kind' => Reminder::KIND_COMMISSION_UNEARNED,
            'invoice_id' => $invoice->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'send_to_agent' => true,
            'send_to_client' => false,
            'frequency' => 'once',
            'message' => '12.500',
            'scheduled_at' => now(),
            'status' => 'pending',
            'is_active' => true,
        ]);

        $result = $this->registry()->render($reminder);

        $this->assertNotNull($result);
        $this->assertNull($result['client_message']);
        $this->assertStringContainsString('12.500', $result['agent_message']);
        $this->assertStringContainsString('INV-5005', $result['agent_message']);
    }

    public function test_renders_payment_link_uninvoiced_kind_agent_only(): void
    {
        $payment = Payment::factory()->create([
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $this->agent->user_id,
            'voucher_number' => 'PV-8003',
            'status' => 'completed',
        ]);

        $reminder = Reminder::create([
            'company_id' => $this->company->id,
            'target_type' => 'payment',
            'reminder_kind' => Reminder::KIND_PAYMENT_LINK_UNINVOICED,
            'payment_id' => $payment->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'send_to_agent' => true,
            'send_to_client' => false,
            'frequency' => 'once',
            'scheduled_at' => now(),
            'status' => 'pending',
            'is_active' => true,
        ]);

        $result = $this->registry()->render($reminder);

        $this->assertNotNull($result);
        $this->assertNull($result['client_message']);
        $this->assertStringContainsString('PV-8003', $result['agent_message']);
    }

    public function test_renders_custom_kind(): void
    {
        $reminder = Reminder::create([
            'company_id' => $this->company->id,
            'target_type' => 'client',
            'reminder_kind' => Reminder::KIND_CUSTOM,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'send_to_client' => true,
            'send_to_agent' => false,
            'frequency' => 'once',
            'message' => 'Please call the office about your booking.',
            'scheduled_at' => now(),
            'status' => 'pending',
            'is_active' => true,
        ]);

        $result = $this->registry()->render($reminder);

        $this->assertNotNull($result);
        $this->assertSame('Please call the office about your booking.', $result['client_message']);
    }

    public function test_returns_null_when_target_relation_is_missing(): void
    {
        // reminder_kind=overdue_invoice but no invoice_id set at all -- no template data to render.
        $reminder = Reminder::create([
            'company_id' => $this->company->id,
            'target_type' => 'invoice',
            'reminder_kind' => Reminder::KIND_OVERDUE_INVOICE,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'send_to_client' => true,
            'send_to_agent' => true,
            'frequency' => 'once',
            'scheduled_at' => now(),
            'status' => 'pending',
            'is_active' => true,
        ]);

        $this->assertNull($this->registry()->render($reminder));
    }
}
