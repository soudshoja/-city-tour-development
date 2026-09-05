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
use App\Models\PaymentApplication;
use App\Models\Reminder;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\Reminders\ReminderGeneratorDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P2.5.I (p2_5-brief.md §P2.5.I). Covers the generator layer's own required behaviours:
 * "Generator idempotency via dedupe_key" and the dispatcher's kind routing (--kind=all sweeps
 * every generator; commission_unearned is a documented no-op here since it is event-driven;
 * an unknown kind is rejected).
 */
class ReminderGeneratorsTest extends TestCase
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
        $companyOwner = User::factory()->create(['role_id' => Role::COMPANY]);
        $this->company = Company::factory()->create(['user_id' => $companyOwner->id, 'country_id' => $country->id]);
        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $this->company->id, 'user_id' => $branchOwner->id]);
        $agentType = AgentType::firstOrCreate(['name' => 'p25i-reminder-type']);
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

    public function test_overdue_invoice_generator_fires_at_configured_offset_and_is_idempotent(): void
    {
        // config/accounting.php's reminders.default_enabled ships every kind OFF as of
        // commit 9585098d5 ("kinds ship OFF; tests opt in") -- generator mechanics still
        // need an explicit per-company opt-in row to exercise.
        Setting::updateOrCreate(
            ['key' => 'accounting.reminders.overdue_invoice.enabled', 'company_id' => $this->company->id],
            ['value' => true, 'type' => 'boolean']
        );

        // Due 3 days ago -> daysOverdue = 3, one of the default offsets (1,3,7,14,30).
        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'agent_id' => $this->agent->id,
            'status' => 'unpaid',
            'amount' => 100,
            'due_date' => now()->subDays(3),
        ]);

        $dispatcher = app(ReminderGeneratorDispatcher::class);
        $result = $dispatcher->run('overdue_invoice', $this->company->id);

        $this->assertSame(1, $result['overdue_invoice']['created']);
        $row = Reminder::where('invoice_id', $invoice->id)->where('reminder_kind', 'overdue_invoice')->first();
        $this->assertNotNull($row);
        $this->assertSame('overdue_invoice:'.$invoice->id.':3', $row->dedupe_key);
        $this->assertSame('pending', $row->status);

        // Re-run: same day, same offset -> dedupe_key already exists -> no new row.
        $result2 = $dispatcher->run('overdue_invoice', $this->company->id);
        $this->assertSame(0, $result2['overdue_invoice']['created']);
        $this->assertSame(1, Reminder::where('invoice_id', $invoice->id)->count());
    }

    public function test_overdue_invoice_generator_skips_a_settled_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'agent_id' => $this->agent->id,
            'status' => 'unpaid',
            'amount' => 100,
            'due_date' => now()->subDays(3),
        ]);

        PaymentApplication::create([
            'payment_id' => $this->makePayment($invoice)->id,
            'invoice_id' => $invoice->id,
            'amount' => 100,
        ]);

        $dispatcher = app(ReminderGeneratorDispatcher::class);
        $result = $dispatcher->run('overdue_invoice', $this->company->id);

        $this->assertSame(0, $result['overdue_invoice']['created']);
        $this->assertSame(0, Reminder::where('invoice_id', $invoice->id)->count());
    }

    public function test_overdue_invoice_generator_respects_company_disabled_switch(): void
    {
        Setting::updateOrCreate(
            ['key' => 'accounting.reminders.overdue_invoice.enabled', 'company_id' => $this->company->id],
            ['value' => false, 'type' => 'boolean']
        );

        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'agent_id' => $this->agent->id,
            'status' => 'unpaid',
            'amount' => 100,
            'due_date' => now()->subDays(3),
        ]);

        $dispatcher = app(ReminderGeneratorDispatcher::class);
        $result = $dispatcher->run('overdue_invoice', $this->company->id);

        $this->assertSame(0, $result['overdue_invoice']['created']);
    }

    public function test_payment_link_uninvoiced_generator_creates_agent_facing_row(): void
    {
        // config/accounting.php's reminders.default_enabled ships every kind OFF as of
        // commit 9585098d5 ("kinds ship OFF; tests opt in") -- generator mechanics still
        // need an explicit per-company opt-in row to exercise.
        Setting::updateOrCreate(
            ['key' => 'accounting.reminders.payment_link_uninvoiced.enabled', 'company_id' => $this->company->id],
            ['value' => true, 'type' => 'boolean']
        );

        $payment = Payment::factory()->create([
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $this->agent->user_id,
            'status' => 'completed',
        ]);

        $dispatcher = app(ReminderGeneratorDispatcher::class);
        $result = $dispatcher->run('payment_link_uninvoiced', $this->company->id);

        $this->assertSame(1, $result['payment_link_uninvoiced']['created']);
        $row = Reminder::where('payment_id', $payment->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('payment', $row->target_type);
        $this->assertTrue((bool) $row->send_to_agent);
        $this->assertFalse((bool) $row->send_to_client);

        // Idempotent within the same half-day slot.
        $result2 = $dispatcher->run('payment_link_uninvoiced', $this->company->id);
        $this->assertSame(0, $result2['payment_link_uninvoiced']['created']);
    }

    public function test_dispatcher_all_sweeps_every_generatable_kind(): void
    {
        // config/accounting.php's reminders.default_enabled ships every kind OFF as of
        // commit 9585098d5 ("kinds ship OFF; tests opt in") -- both swept kinds need an
        // explicit per-company opt-in row to actually create anything.
        Setting::updateOrCreate(
            ['key' => 'accounting.reminders.overdue_invoice.enabled', 'company_id' => $this->company->id],
            ['value' => true, 'type' => 'boolean']
        );
        Setting::updateOrCreate(
            ['key' => 'accounting.reminders.payment_link_uninvoiced.enabled', 'company_id' => $this->company->id],
            ['value' => true, 'type' => 'boolean']
        );

        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'agent_id' => $this->agent->id,
            'status' => 'unpaid',
            'amount' => 50,
            'due_date' => now()->subDays(7),
        ]);
        Payment::factory()->create([
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $this->agent->user_id,
            'status' => 'completed',
        ]);

        $dispatcher = app(ReminderGeneratorDispatcher::class);
        $results = $dispatcher->run('all', $this->company->id);

        $this->assertArrayHasKey('overdue_invoice', $results);
        $this->assertArrayHasKey('statement_balance', $results);
        $this->assertArrayHasKey('ticketing_deadline', $results);
        $this->assertArrayHasKey('payment_link_uninvoiced', $results);
        $this->assertGreaterThan(0, $results['overdue_invoice']['created']);
        $this->assertGreaterThan(0, $results['payment_link_uninvoiced']['created']);
    }

    public function test_dispatcher_commission_unearned_is_a_documented_noop(): void
    {
        $dispatcher = app(ReminderGeneratorDispatcher::class);
        $result = $dispatcher->run('commission_unearned', $this->company->id);

        $this->assertSame(['created' => 0, 'skipped' => 0], $result['commission_unearned']);
        $this->assertSame(0, Reminder::count());
    }

    public function test_dispatcher_rejects_unknown_kind(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(ReminderGeneratorDispatcher::class)->run('not_a_real_kind', $this->company->id);
    }

    private function makePayment(Invoice $invoice): Payment
    {
        return Payment::factory()->create([
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'invoice_id' => $invoice->id,
            'account_id' => null,
            'created_by' => $this->agent->user_id,
            'status' => 'completed',
            'amount' => 100,
        ]);
    }
}
