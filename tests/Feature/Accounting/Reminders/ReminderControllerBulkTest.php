<?php

namespace Tests\Feature\Accounting\Reminders;

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
use Tests\TestCase;

/**
 * P2.5.I (p2_5-brief.md §P2.5.I) required test: "bulk() writes valid rows with a real
 * scheduled_at". Pre-P2.5.I this endpoint wrote four non-existent columns and left scheduled_at
 * NULL, so a created row could never be selected as due -- see ReminderController::bulk()'s own
 * docblock for the full before/after.
 */
class ReminderControllerBulkTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Agent $agent;

    private Client $client;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        Company::forgetModuleCache();

        $country = Country::factory()->create();
        $this->adminUser = User::factory()->create(['role_id' => Role::ADMIN]);
        $this->company = Company::factory()->create(['user_id' => $this->adminUser->id, 'country_id' => $country->id]);
        $branch = Branch::factory()->create(['company_id' => $this->company->id, 'user_id' => User::factory()->create()->id]);
        $agentType = AgentType::firstOrCreate(['name' => 'p25i-bulk-type']);
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

    public function test_bulk_creates_a_valid_pending_row_with_a_real_scheduled_at(): void
    {
        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'agent_id' => $this->agent->id,
            'status' => 'unpaid',
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('reminder.bulk'), [
            'send_to_client' => '1',
            'frequency' => 'once',
        ]);

        $response->assertRedirect();

        $row = Reminder::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('pending', $row->status);
        $this->assertNotNull($row->scheduled_at, 'A real scheduled_at must be set, or the row can never be selected as due.');
        $this->assertTrue($row->scheduled_at->lessThanOrEqualTo(now()));
        $this->assertSame('overdue_invoice', $row->reminder_kind);
        $this->assertNotNull($row->dedupe_key);
        $this->assertNotNull($row->company_id);
    }

    public function test_bulk_is_idempotent_on_a_second_call(): void
    {
        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'agent_id' => $this->agent->id,
            'status' => 'unpaid',
        ]);

        $payload = ['send_to_client' => '1', 'frequency' => 'once'];

        $this->actingAs($this->adminUser)->post(route('reminder.bulk'), $payload);
        $countAfterFirst = Reminder::count();

        $this->actingAs($this->adminUser)->post(route('reminder.bulk'), $payload);
        $countAfterSecond = Reminder::count();

        $this->assertSame($countAfterFirst, $countAfterSecond, 'A second bulk call must not duplicate rows for the same invoice.');
    }

    public function test_bulk_leaves_paid_invoices_alone(): void
    {
        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'agent_id' => $this->agent->id,
            'status' => 'paid',
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('reminder.bulk'), [
            'send_to_client' => '1',
            'frequency' => 'once',
        ]);

        $response->assertRedirect();
        $this->assertSame(0, Reminder::count());
    }
}
