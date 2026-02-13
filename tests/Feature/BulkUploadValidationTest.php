<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\InvoiceDetail;
use App\Models\Supplier;
use App\Models\Task;
use App\Services\BulkUploadValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkUploadValidationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $skipPermissionSeeder = true;

    private BulkUploadValidationService $service;

    private Company $company;

    private Supplier $supplier;

    private Client $client;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new BulkUploadValidationService;

        // Create test data
        $this->company = Company::factory()->create();

        $this->supplier = Supplier::create([
            'name' => 'Test Supplier',
            'is_manual' => true,
        ]);

        $this->client = Client::create([
            'name' => 'Test Client',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '12345678',
            'company_id' => $this->company->id,
        ]);

        $this->task = Task::create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'client_id' => $this->client->id,
            'type' => 'flight',
            'status' => 'issued',
            'reference' => 'TEST123',
            'total' => 100.00,
        ]);
    }

    /** @test */
    public function it_validates_correct_headers()
    {
        $headers = ['task_id', 'client_mobile', 'supplier_name', 'task_type'];

        $result = $this->service->validateHeaders($headers);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['missing']);
        $this->assertEmpty($result['extra']);
    }

    /** @test */
    public function it_detects_missing_required_headers()
    {
        $headers = ['task_id', 'client_mobile']; // Missing supplier_name and task_type

        $result = $this->service->validateHeaders($headers);

        $this->assertFalse($result['valid']);
        $this->assertContains('supplier_name', $result['missing']);
        $this->assertContains('task_type', $result['missing']);
    }

    /** @test */
    public function it_allows_extra_headers_with_warning()
    {
        $headers = ['task_id', 'client_mobile', 'supplier_name', 'task_type', 'extra_column'];

        $result = $this->service->validateHeaders($headers);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['missing']);
        $this->assertContains('extra_column', $result['extra']);
    }

    /** @test */
    public function it_validates_row_with_all_correct_data()
    {
        $row = [
            'task_id' => $this->task->id,
            'client_mobile' => '12345678',
            'supplier_name' => 'Test Supplier',
            'task_type' => 'flight',
        ];

        $result = $this->service->validateRow($row, 1, $this->company->id);

        $this->assertEquals('valid', $result['status']);
        $this->assertEmpty($result['errors']);
        $this->assertNull($result['flag_reason']);
        $this->assertEquals($this->client->id, $result['matched']['client_id']);
        $this->assertEquals($this->task->id, $result['matched']['task_id']);
        $this->assertEquals($this->supplier->id, $result['matched']['supplier_id']);
    }

    /** @test */
    public function it_requires_task_id()
    {
        $row = [
            'task_id' => null,
            'client_mobile' => '12345678',
            'supplier_name' => 'Test Supplier',
            'task_type' => 'flight',
        ];

        $result = $this->service->validateRow($row, 3, $this->company->id);

        $this->assertEquals('error', $result['status']);
        $this->assertContains('Row 3: task_id is required', $result['errors']);
    }

    /** @test */
    public function it_validates_task_exists_and_belongs_to_company()
    {
        $otherCompany = Company::factory()->create();

        $row = [
            'task_id' => 99999, // Non-existent task
            'client_mobile' => '12345678',
            'supplier_name' => 'Test Supplier',
            'task_type' => 'flight',
        ];

        $result = $this->service->validateRow($row, 2, $this->company->id);

        $this->assertEquals('error', $result['status']);
        $this->assertTrue(count($result['errors']) > 0);
    }

    /** @test */
    public function it_flags_unknown_client_without_error()
    {
        $row = [
            'task_id' => $this->task->id,
            'client_mobile' => '99999999', // Unknown phone
            'supplier_name' => 'Test Supplier',
            'task_type' => 'flight',
        ];

        $result = $this->service->validateRow($row, 5, $this->company->id);

        $this->assertEquals('flagged', $result['status']);
        $this->assertEquals('unknown_client', $result['flag_reason']);
        $this->assertEmpty($result['errors']); // Should NOT be in errors
        $this->assertNull($result['matched']['client_id']);
    }

    /** @test */
    public function it_validates_task_not_already_invoiced()
    {
        // Create an invoice detail for this task
        InvoiceDetail::factory()->create([
            'task_id' => $this->task->id,
        ]);

        $row = [
            'task_id' => $this->task->id,
            'client_mobile' => '12345678',
            'supplier_name' => 'Test Supplier',
            'task_type' => 'flight',
        ];

        $result = $this->service->validateRow($row, 4, $this->company->id);

        $this->assertEquals('error', $result['status']);
        $this->assertTrue(
            collect($result['errors'])->contains(function ($error) {
                return str_contains($error, 'already invoiced');
            })
        );
    }

    /** @test */
    public function it_validates_task_type_enum()
    {
        $validTypes = ['flight', 'hotel', 'visa', 'insurance', 'tour', 'cruise', 'car', 'rail', 'esim', 'event', 'lounge', 'ferry'];

        foreach ($validTypes as $type) {
            $row = [
                'task_id' => $this->task->id,
                'client_mobile' => '12345678',
                'supplier_name' => 'Test Supplier',
                'task_type' => $type,
            ];

            $result = $this->service->validateRow($row, 1, $this->company->id);

            // Should not have task_type error
            $hasTaskTypeError = collect($result['errors'])->contains(function ($error) {
                return str_contains($error, 'task_type');
            });
            $this->assertFalse($hasTaskTypeError, "Valid task_type '{$type}' should not produce error");
        }
    }

    /** @test */
    public function it_rejects_invalid_task_type()
    {
        $row = [
            'task_id' => $this->task->id,
            'client_mobile' => '12345678',
            'supplier_name' => 'Test Supplier',
            'task_type' => 'invalid_type',
        ];

        $result = $this->service->validateRow($row, 6, $this->company->id);

        $this->assertEquals('error', $result['status']);
        $this->assertTrue(
            collect($result['errors'])->contains(function ($error) {
                return str_contains($error, 'Row 6: task_type') && str_contains($error, 'invalid');
            })
        );
    }

    /** @test */
    public function it_validates_supplier_exists_case_insensitive()
    {
        $row = [
            'task_id' => $this->task->id,
            'client_mobile' => '12345678',
            'supplier_name' => 'TEST SUPPLIER', // Different case
            'task_type' => 'flight',
        ];

        $result = $this->service->validateRow($row, 1, $this->company->id);

        $this->assertEquals($this->supplier->id, $result['matched']['supplier_id']);
    }

    /** @test */
    public function it_errors_on_unknown_supplier()
    {
        $row = [
            'task_id' => $this->task->id,
            'client_mobile' => '12345678',
            'supplier_name' => 'Unknown Supplier',
            'task_type' => 'flight',
        ];

        $result = $this->service->validateRow($row, 7, $this->company->id);

        $this->assertEquals('error', $result['status']);
        $this->assertTrue(
            collect($result['errors'])->contains(function ($error) {
                return str_contains($error, 'supplier');
            })
        );
    }

    /** @test */
    public function it_collects_multiple_errors_per_row()
    {
        $row = [
            'task_id' => null, // Error 1
            'client_mobile' => '12345678',
            'supplier_name' => 'Unknown Supplier', // Error 2
            'task_type' => 'invalid_type', // Error 3
        ];

        $result = $this->service->validateRow($row, 8, $this->company->id);

        $this->assertEquals('error', $result['status']);
        $this->assertGreaterThanOrEqual(3, count($result['errors']));
    }

    /** @test */
    public function it_validates_all_rows_and_aggregates_counts()
    {
        // Create another task
        $task2 = Task::create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'client_id' => $this->client->id,
            'type' => 'hotel',
            'status' => 'issued',
            'reference' => 'TEST456',
            'total' => 200.00,
        ]);

        $rows = [
            // Row 1: Valid
            [
                'task_id' => $this->task->id,
                'client_mobile' => '12345678',
                'supplier_name' => 'Test Supplier',
                'task_type' => 'flight',
            ],
            // Row 2: Unknown client (flagged)
            [
                'task_id' => $task2->id,
                'client_mobile' => '99999999',
                'supplier_name' => 'Test Supplier',
                'task_type' => 'hotel',
            ],
            // Row 3: Error (invalid task_type)
            [
                'task_id' => $this->task->id,
                'client_mobile' => '12345678',
                'supplier_name' => 'Test Supplier',
                'task_type' => 'boat',
            ],
        ];

        $result = $this->service->validateAll($rows, $this->company->id);

        $this->assertEquals(3, $result['total']);
        $this->assertEquals(1, $result['valid']);
        $this->assertEquals(1, $result['errors']);
        $this->assertEquals(1, $result['flagged']);
        $this->assertCount(3, $result['rows']);
    }

    /** @test */
    public function it_validates_optional_task_status_when_provided()
    {
        $validStatuses = ['pending', 'issued', 'confirmed', 'reissued', 'refund', 'void', 'emd'];

        foreach ($validStatuses as $status) {
            $row = [
                'task_id' => $this->task->id,
                'client_mobile' => '12345678',
                'supplier_name' => 'Test Supplier',
                'task_type' => 'flight',
                'task_status' => $status,
            ];

            $result = $this->service->validateRow($row, 1, $this->company->id);

            // Should not have task_status error
            $hasStatusError = collect($result['errors'])->contains(function ($error) {
                return str_contains($error, 'task_status');
            });
            $this->assertFalse($hasStatusError, "Valid task_status '{$status}' should not produce error");
        }
    }

    /** @test */
    public function it_validates_optional_invoice_date_format()
    {
        $row = [
            'task_id' => $this->task->id,
            'client_mobile' => '12345678',
            'supplier_name' => 'Test Supplier',
            'task_type' => 'flight',
            'invoice_date' => '2026-02-13',
        ];

        $result = $this->service->validateRow($row, 1, $this->company->id);

        // Valid date should not produce error
        $hasDateError = collect($result['errors'])->contains(function ($error) {
            return str_contains($error, 'invoice_date');
        });
        $this->assertFalse($hasDateError);
    }

    /** @test */
    public function it_rejects_invalid_invoice_date_format()
    {
        $row = [
            'task_id' => $this->task->id,
            'client_mobile' => '12345678',
            'supplier_name' => 'Test Supplier',
            'task_type' => 'flight',
            'invoice_date' => 'invalid-date',
        ];

        $result = $this->service->validateRow($row, 9, $this->company->id);

        $this->assertEquals('error', $result['status']);
        $this->assertTrue(
            collect($result['errors'])->contains(function ($error) {
                return str_contains($error, 'invoice_date');
            })
        );
    }

    /** @test */
    public function it_validates_optional_currency_code()
    {
        $validCurrencies = ['KWD', 'USD', 'EUR', 'GBP', 'SAR', 'AED', 'BHD', 'OMR', 'QAR'];

        foreach ($validCurrencies as $currency) {
            $row = [
                'task_id' => $this->task->id,
                'client_mobile' => '12345678',
                'supplier_name' => 'Test Supplier',
                'task_type' => 'flight',
                'currency' => $currency,
            ];

            $result = $this->service->validateRow($row, 1, $this->company->id);

            // Should not have currency error
            $hasCurrencyError = collect($result['errors'])->contains(function ($error) {
                return str_contains($error, 'currency');
            });
            $this->assertFalse($hasCurrencyError, "Valid currency '{$currency}' should not produce error");
        }
    }
}
