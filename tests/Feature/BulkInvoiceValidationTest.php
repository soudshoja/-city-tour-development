<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Services\BulkInvoiceValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkInvoiceValidationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $skipPermissionSeeder = true;

    private BulkInvoiceValidationService $service;

    private Company $company;

    private Supplier $supplier;

    private Client $client;

    private Task $task;

    private Agent $agent;

    private Branch $branch;

    private Payment $payment;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new BulkInvoiceValidationService;

        // Create prerequisites for factory foreign keys (IDs must be explicit
        // because MySQL auto-increment doesn't reset on transaction rollback)
        $this->user = User::factory()->create();
        $user = $this->user;
        $agentType = AgentType::factory()->create();
        $country = Country::factory()->create();

        // Create test data
        $this->company = Company::factory()->create([
            'user_id' => $user->id,
            'country_id' => $country->id,
        ]);

        $this->branch = Branch::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
        ]);

        $this->agent = Agent::factory()->create([
            'branch_id' => $this->branch->id,
            'user_id' => $user->id,
            'type_id' => $agentType->id,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Test Supplier',
            'is_manual' => true,
            'country_id' => $country->id,
        ]);

        $this->client = Client::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '12345678',
            'company_id' => $this->company->id,
        ]);

        $this->task = Task::create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'client_id' => $this->client->id,
            'agent_id' => $this->agent->id,
            'type' => 'flight',
            'status' => 'issued',
            'reference' => 'TEST123',
            'total' => 100.00,
        ]);

        $this->payment = Payment::factory()->create([
            'client_id' => $this->client->id,
            'agent_id' => $this->agent->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $user->id,
            'status' => 'completed',
            'amount' => 5000.00,
        ]);

        // Create topup credit so payment has available balance
        Credit::create([
            'client_id' => $this->client->id,
            'payment_id' => $this->payment->id,
            'type' => Credit::TOPUP,
            'amount' => 5000.00,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
        ]);
    }

    /**
     * Create a Payment with proper FK overrides.
     */
    private function createPayment(array $overrides = []): Payment
    {
        return Payment::factory()->create(array_merge([
            'client_id' => $this->client->id,
            'agent_id' => $this->agent->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $this->user->id,
            'status' => 'completed',
            'amount' => 1000.00,
        ], $overrides));
    }

    /**
     * Build a valid row array with optional overrides.
     */
    private function validRow(array $overrides = []): array
    {
        return array_merge([
            'invoice_date' => '2026-03-15',
            'client_name' => 'John Doe',
            'client_mobile' => '12345678',
            'task_reference' => 'TEST123',
            'task_status' => 'issued',
            'selling_price' => '100.00',
            'payment_reference' => $this->payment->voucher_number,
        ], $overrides);
    }

    // ─── Header Validation ───────────────────────────────────────────

    /** @test */
    public function it_validates_correct_headers()
    {
        $headers = [
            'invoice_date', 'client_name', 'client_mobile', 'task_reference',
            'task_status', 'passenger_name', 'selling_price', 'payment_reference', 'notes',
        ];

        $result = $this->service->validateHeaders($headers);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['missing']);
        $this->assertEmpty($result['extra']);
    }

    /** @test */
    public function it_detects_missing_required_headers()
    {
        $headers = ['invoice_date', 'client_name']; // Missing most required headers

        $result = $this->service->validateHeaders($headers);

        $this->assertFalse($result['valid']);
        $this->assertContains('client_mobile', $result['missing']);
        $this->assertContains('task_reference', $result['missing']);
        $this->assertContains('task_status', $result['missing']);
        $this->assertContains('selling_price', $result['missing']);
        $this->assertContains('payment_reference', $result['missing']);
    }

    /** @test */
    public function it_allows_extra_headers_with_warning()
    {
        $headers = [
            'invoice_date', 'client_name', 'client_mobile', 'task_reference',
            'task_status', 'passenger_name', 'selling_price', 'payment_reference',
            'notes', 'extra_column',
        ];

        $result = $this->service->validateHeaders($headers);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['missing']);
        $this->assertContains('extra_column', $result['extra']);
    }

    // ─── Row Validation: Valid Row ───────────────────────────────────

    /** @test */
    public function it_validates_row_with_all_correct_data()
    {
        $result = $this->service->validateRow($this->validRow(), 1, $this->company->id);

        $this->assertEquals('valid', $result['status']);
        $this->assertEmpty($result['errors']);
        $this->assertNull($result['flag_reason']);
        $this->assertEquals($this->client->id, $result['matched']['client_id']);
        $this->assertEquals($this->task->id, $result['matched']['task_id']);
        $this->assertEquals($this->payment->id, $result['matched']['payment_id']);
    }

    // ─── Row Validation: Required Fields ─────────────────────────────

    /** @test */
    public function it_requires_invoice_date()
    {
        $result = $this->service->validateRow(
            $this->validRow(['invoice_date' => null]),
            1,
            $this->company->id
        );

        $this->assertEquals('error', $result['status']);
        $this->assertContains('Row 1: invoice_date is required', $result['errors']);
    }

    /** @test */
    public function it_requires_client_name()
    {
        $result = $this->service->validateRow(
            $this->validRow(['client_name' => null]),
            1,
            $this->company->id
        );

        $this->assertEquals('error', $result['status']);
        $this->assertContains('Row 1: client_name is required', $result['errors']);
    }

    /** @test */
    public function it_requires_client_mobile()
    {
        $result = $this->service->validateRow(
            $this->validRow(['client_mobile' => null]),
            2,
            $this->company->id
        );

        $this->assertEquals('error', $result['status']);
        $this->assertContains('Row 2: client_mobile is required', $result['errors']);
    }

    /** @test */
    public function it_requires_task_reference()
    {
        $result = $this->service->validateRow(
            $this->validRow(['task_reference' => null]),
            3,
            $this->company->id
        );

        $this->assertEquals('error', $result['status']);
        $this->assertContains('Row 3: task_reference is required', $result['errors']);
    }

    /** @test */
    public function it_requires_task_status()
    {
        $result = $this->service->validateRow(
            $this->validRow(['task_status' => null]),
            4,
            $this->company->id
        );

        $this->assertEquals('error', $result['status']);
        $this->assertContains('Row 4: task_status is required', $result['errors']);
    }

    /** @test */
    public function it_requires_selling_price()
    {
        $result = $this->service->validateRow(
            $this->validRow(['selling_price' => null]),
            5,
            $this->company->id
        );

        $this->assertEquals('error', $result['status']);
        $this->assertContains('Row 5: selling_price is required', $result['errors']);
    }

    /** @test */
    public function it_requires_payment_reference()
    {
        $result = $this->service->validateRow(
            $this->validRow(['payment_reference' => null]),
            6,
            $this->company->id
        );

        $this->assertEquals('error', $result['status']);
        $this->assertContains('Row 6: payment_reference is required', $result['errors']);
    }

    // ─── Row Validation: Date Format ─────────────────────────────────

    /** @test */
    public function it_validates_invoice_date_format()
    {
        $result = $this->service->validateRow(
            $this->validRow(['invoice_date' => '2026-03-15']),
            1,
            $this->company->id
        );

        $hasDateError = collect($result['errors'])->contains(fn ($e) => str_contains($e, 'invoice_date'));
        $this->assertFalse($hasDateError);
    }

    /** @test */
    public function it_rejects_invalid_invoice_date_format()
    {
        $result = $this->service->validateRow(
            $this->validRow(['invoice_date' => 'invalid-date']),
            9,
            $this->company->id
        );

        $this->assertEquals('error', $result['status']);
        $this->assertTrue(
            collect($result['errors'])->contains(fn ($e) => str_contains($e, 'invoice_date'))
        );
    }

    // ─── Row Validation: Client Matching ─────────────────────────────

    /** @test */
    public function it_errors_when_client_mobile_not_found()
    {
        $result = $this->service->validateRow(
            $this->validRow(['client_mobile' => '99999999']),
            1,
            $this->company->id
        );

        $this->assertEquals('error', $result['status']);
        $this->assertTrue(
            collect($result['errors'])->contains(fn ($e) => str_contains($e, 'not found in your company'))
        );
        $this->assertNull($result['matched']['client_id']);
    }

    /** @test */
    public function it_errors_when_client_name_does_not_match_phone()
    {
        $result = $this->service->validateRow(
            $this->validRow(['client_name' => 'Wrong Name']),
            1,
            $this->company->id
        );

        $this->assertEquals('error', $result['status']);
        $this->assertTrue(
            collect($result['errors'])->contains(fn ($e) => str_contains($e, 'does not match'))
        );
    }

    /** @test */
    public function it_matches_client_with_flexible_name()
    {
        // Client full_name is "John Doe", searching with just "John Doe" should match
        $result = $this->service->validateRow(
            $this->validRow(['client_name' => 'John Doe']),
            1,
            $this->company->id
        );

        $this->assertEquals($this->client->id, $result['matched']['client_id']);
    }

    // ─── Row Validation: Task Matching ───────────────────────────────

    /** @test */
    public function it_validates_task_exists_by_reference()
    {
        $result = $this->service->validateRow(
            $this->validRow(['task_reference' => 'NONEXISTENT']),
            2,
            $this->company->id
        );

        $this->assertEquals('error', $result['status']);
        $this->assertTrue(
            collect($result['errors'])->contains(fn ($e) => str_contains($e, 'task_reference') && str_contains($e, 'not found'))
        );
    }

    /** @test */
    public function it_validates_task_not_already_invoiced()
    {
        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'agent_id' => $this->agent->id,
        ]);
        InvoiceDetail::factory()->create([
            'task_id' => $this->task->id,
            'invoice_id' => $invoice->id,
        ]);

        $result = $this->service->validateRow($this->validRow(), 4, $this->company->id);

        $this->assertEquals('error', $result['status']);
        $this->assertTrue(
            collect($result['errors'])->contains(fn ($e) => str_contains($e, 'already invoiced'))
        );
    }

    /** @test */
    public function it_validates_task_has_agent_assigned()
    {
        // Create task without agent
        $taskNoAgent = Task::create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'client_id' => $this->client->id,
            'agent_id' => null,
            'type' => 'hotel',
            'status' => 'issued',
            'reference' => 'NOAGENT001',
            'total' => 200.00,
        ]);

        $result = $this->service->validateRow(
            $this->validRow(['task_reference' => 'NOAGENT001']),
            1,
            $this->company->id
        );

        $this->assertEquals('error', $result['status']);
        $this->assertTrue(
            collect($result['errors'])->contains(fn ($e) => str_contains($e, 'no agent assigned'))
        );
    }

    /** @test */
    public function it_validates_task_client_mismatch()
    {
        // Create a different client
        $otherClient = Client::create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'phone' => '87654321',
            'company_id' => $this->company->id,
        ]);

        // Task belongs to otherClient, but row says client_mobile = 12345678 (John Doe)
        $taskOtherClient = Task::create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'client_id' => $otherClient->id,
            'agent_id' => $this->agent->id,
            'type' => 'visa',
            'status' => 'issued',
            'reference' => 'OTHERCLIENT',
            'total' => 50.00,
        ]);

        $result = $this->service->validateRow(
            $this->validRow(['task_reference' => 'OTHERCLIENT']),
            1,
            $this->company->id
        );

        $this->assertEquals('error', $result['status']);
        $this->assertTrue(
            collect($result['errors'])->contains(fn ($e) => str_contains($e, 'different client'))
        );
    }

    // ─── Row Validation: Task Status Enum ────────────────────────────

    /** @test */
    public function it_validates_task_status_enum()
    {
        $validStatuses = ['pending', 'issued', 'confirmed', 'reissued', 'refund', 'void', 'emd'];

        foreach ($validStatuses as $status) {
            $result = $this->service->validateRow(
                $this->validRow(['task_status' => $status]),
                1,
                $this->company->id
            );

            $hasStatusError = collect($result['errors'])->contains(fn ($e) => str_contains($e, 'task_status'));
            $this->assertFalse($hasStatusError, "Valid task_status '{$status}' should not produce error");
        }
    }

    /** @test */
    public function it_rejects_invalid_task_status()
    {
        $result = $this->service->validateRow(
            $this->validRow(['task_status' => 'invalid_status']),
            6,
            $this->company->id
        );

        $this->assertEquals('error', $result['status']);
        $this->assertTrue(
            collect($result['errors'])->contains(fn ($e) => str_contains($e, 'task_status') && str_contains($e, 'invalid'))
        );
    }

    // ─── Row Validation: Selling Price ───────────────────────────────

    /** @test */
    public function it_rejects_non_numeric_selling_price()
    {
        $result = $this->service->validateRow(
            $this->validRow(['selling_price' => 'abc']),
            1,
            $this->company->id
        );

        $this->assertEquals('error', $result['status']);
        $this->assertTrue(
            collect($result['errors'])->contains(fn ($e) => str_contains($e, 'selling_price') && str_contains($e, 'number'))
        );
    }

    /** @test */
    public function it_rejects_negative_selling_price()
    {
        $result = $this->service->validateRow(
            $this->validRow(['selling_price' => '-10']),
            1,
            $this->company->id
        );

        $this->assertEquals('error', $result['status']);
        $this->assertTrue(
            collect($result['errors'])->contains(fn ($e) => str_contains($e, 'selling_price') && str_contains($e, '>= 0'))
        );
    }

    // ─── Row Validation: Payment ─────────────────────────────────────

    /** @test */
    public function it_errors_on_unknown_payment_reference()
    {
        $result = $this->service->validateRow(
            $this->validRow(['payment_reference' => 'UNKNOWN-PAY-999']),
            7,
            $this->company->id
        );

        $this->assertEquals('error', $result['status']);
        $this->assertTrue(
            collect($result['errors'])->contains(fn ($e) => str_contains($e, 'payment_reference') && str_contains($e, 'not found'))
        );
    }

    /** @test */
    public function it_errors_when_payment_not_completed()
    {
        $pendingPayment = $this->createPayment([
            'status' => 'pending',
            'amount' => 1000.00,
        ]);

        Credit::create([
            'client_id' => $this->client->id,
            'payment_id' => $pendingPayment->id,
            'type' => Credit::TOPUP,
            'amount' => 1000.00,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
        ]);

        $result = $this->service->validateRow(
            $this->validRow(['payment_reference' => $pendingPayment->voucher_number]),
            1,
            $this->company->id
        );

        $this->assertEquals('error', $result['status']);
        $this->assertTrue(
            collect($result['errors'])->contains(fn ($e) => str_contains($e, 'not paid yet'))
        );
    }

    /** @test */
    public function it_errors_when_payment_belongs_to_different_client()
    {
        $otherClient = Client::create([
            'first_name' => 'Other',
            'last_name' => 'Person',
            'phone' => '55555555',
            'company_id' => $this->company->id,
        ]);

        $otherPayment = $this->createPayment([
            'client_id' => $otherClient->id,
            'status' => 'completed',
            'amount' => 2000.00,
        ]);

        Credit::create([
            'client_id' => $otherClient->id,
            'payment_id' => $otherPayment->id,
            'type' => Credit::TOPUP,
            'amount' => 2000.00,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
        ]);

        $result = $this->service->validateRow(
            $this->validRow(['payment_reference' => $otherPayment->voucher_number]),
            1,
            $this->company->id
        );

        $this->assertEquals('error', $result['status']);
        $this->assertTrue(
            collect($result['errors'])->contains(fn ($e) => str_contains($e, 'different client'))
        );
    }

    /** @test */
    public function it_flags_insufficient_payment_balance()
    {
        // Create payment with very small credit balance
        $lowPayment = $this->createPayment(['amount' => 10.00]);

        Credit::create([
            'client_id' => $this->client->id,
            'payment_id' => $lowPayment->id,
            'type' => Credit::TOPUP,
            'amount' => 10.00,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
        ]);

        $result = $this->service->validateRow(
            $this->validRow([
                'payment_reference' => $lowPayment->voucher_number,
                'selling_price' => '500.00',
            ]),
            1,
            $this->company->id
        );

        $this->assertEquals('flagged', $result['status']);
        $this->assertNotNull($result['flag_reason']);
        $this->assertStringContainsString('available balance', $result['flag_reason']);
    }

    // ─── Row Validation: Multiple Errors ─────────────────────────────

    /** @test */
    public function it_collects_multiple_errors_per_row()
    {
        $row = [
            'invoice_date' => null,          // Error 1
            'client_name' => null,           // Error 2
            'client_mobile' => null,         // Error 3
            'task_reference' => null,        // Error 4
            'task_status' => null,           // Error 5
            'selling_price' => null,         // Error 6
            'payment_reference' => null,     // Error 7
        ];

        $result = $this->service->validateRow($row, 8, $this->company->id);

        $this->assertEquals('error', $result['status']);
        $this->assertGreaterThanOrEqual(7, count($result['errors']));
    }

    // ─── Validate All ────────────────────────────────────────────────

    /** @test */
    public function it_validates_all_rows_and_aggregates_counts()
    {
        // Create a second task for flagged row
        $task2 = Task::create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'client_id' => $this->client->id,
            'agent_id' => $this->agent->id,
            'type' => 'hotel',
            'status' => 'issued',
            'reference' => 'TEST456',
            'total' => 200.00,
        ]);

        // Payment with low balance for flagged row
        $lowPayment = $this->createPayment(['amount' => 5.00]);

        Credit::create([
            'client_id' => $this->client->id,
            'payment_id' => $lowPayment->id,
            'type' => Credit::TOPUP,
            'amount' => 5.00,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
        ]);

        $rows = [
            // Row 1: Valid
            $this->validRow(),
            // Row 2: Flagged (insufficient balance)
            $this->validRow([
                'task_reference' => 'TEST456',
                'selling_price' => '500.00',
                'payment_reference' => $lowPayment->voucher_number,
            ]),
            // Row 3: Error (invalid task_status)
            $this->validRow(['task_status' => 'invalid_status']),
        ];

        $result = $this->service->validateAll($rows, $this->company->id);

        $this->assertEquals(3, $result['total']);
        $this->assertEquals(1, $result['valid']);
        $this->assertEquals(1, $result['errors']);
        $this->assertEquals(1, $result['flagged']);
        $this->assertCount(3, $result['rows']);
    }

    /** @test */
    public function it_detects_duplicate_tasks_in_validate_all()
    {
        $rows = [
            // Row 1 and Row 2 reference the same task
            $this->validRow(),
            $this->validRow(),
        ];

        $result = $this->service->validateAll($rows, $this->company->id);

        // Second row should be marked as duplicate error
        $this->assertEquals('error', $result['rows'][1]['status']);
        $this->assertTrue(
            collect($result['rows'][1]['errors'])->contains(fn ($e) => str_contains($e, 'duplicate task'))
        );
    }
}
