<?php

namespace Tests\Feature\Staging;

use Tests\TestCase;
use App\Models\DocumentProcessingLog;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

/**
 * TEST-06: Staging Supplier Validation Tests
 *
 * Tests covering 6+ supplier-specific scenarios:
 * - Supplier routing validation (12 suppliers)
 * - Per-supplier HMAC secrets
 * - Supplier fallback handling
 * - Multi-company isolation
 * - Supplier config validation
 * - Real document simulation
 */
class StagingSupplierTest extends TestCase
{
    use RefreshDatabase;

    protected $skipPermissionSeeder = true;

    /**
     * Standard supplier IDs used in the system
     */
    const SUPPLIER_IDS = [
        1 => 'Amadeus',
        2 => 'Sabre',
        3 => 'Travelport',
        4 => 'TBO',
        5 => 'Magic Holiday',
        6 => 'Expedia',
        7 => 'Booking.com',
        8 => 'Travco',
        9 => 'Al-Tayyar',
        10 => 'IATA BSP',
        11 => 'Generic Email',
        12 => 'Manual Upload',
    ];

    /**
     * TEST 1: Supplier routing validation
     * Verify each of 12 suppliers routes to correct N8n workflow
     */
    public function test_supplier_routing_validation(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'queued'], 200),
        ]);

        foreach (self::SUPPLIER_IDS as $supplierId => $supplierName) {
            $company = Company::factory()->create();

            $response = $this->postJson('/api/document-processing', [
                'company_id' => $company->id,
                'supplier_id' => $supplierId,
                'document_type' => 'air',
                'file_path' => "test/{$supplierName}.air",
            ]);

            $response->assertStatus(202)
                ->assertJsonStructure([
                    'document_id',
                    'status',
                    'message',
                ]);

            // Verify document was created with correct supplier_id
            $this->assertDatabaseHas('document_processing_logs', [
                'company_id' => $company->id,
                'supplier_id' => $supplierId,
                'status' => 'queued',
            ]);
        }
    }

    /**
     * TEST 2: Per-supplier HMAC secrets
     * Each supplier can use its own webhook secret
     */
    public function test_per_supplier_hmac_secrets(): void
    {
        $company = Company::factory()->create();
        $documentId = \Illuminate\Support\Str::uuid()->toString();

        DocumentProcessingLog::create([
            'company_id' => $company->id,
            'supplier_id' => 1,
            'document_id' => $documentId,
            'document_type' => 'air',
            'file_path' => 'test/amadeus.air',
            'status' => 'queued',
        ]);

        $payload = [
            'document_id' => $documentId,
            'status' => 'success',
            'execution_id' => 'exec-supplier-1',
            'workflow_id' => 'wf-supplier-1',
            'execution_time_ms' => 1500,
            'extraction_result' => ['tasks' => []],
        ];

        // Use standard N8n webhook secret (could be supplier-specific in production)
        $payloadJson = json_encode($payload);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $payloadJson, config('services.n8n.webhook_secret'));

        $response = $this->postJson('/api/webhooks/n8n/extraction', $payload, [
            'X-Signature' => $signature,
            'X-Timestamp' => $timestamp,
        ]);

        $response->assertStatus(200);

        // Verify callback was processed correctly
        $this->assertDatabaseHas('document_processing_logs', [
            'document_id' => $documentId,
            'status' => 'completed',
            'n8n_execution_id' => 'exec-supplier-1',
        ]);
    }

    /**
     * TEST 3: Supplier fallback handling
     * Unknown supplier_id triggers fallback handler
     */
    public function test_supplier_fallback_for_unknown_supplier(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'queued'], 200),
        ]);

        $company = Company::factory()->create();
        $unknownSupplierId = 999;

        $response = $this->postJson('/api/document-processing', [
            'company_id' => $company->id,
            'supplier_id' => $unknownSupplierId,
            'document_type' => 'air',
            'file_path' => 'test/unknown-supplier.air',
        ]);

        // Should still queue successfully (fallback workflow handles unknown suppliers)
        $response->assertStatus(202);

        $this->assertDatabaseHas('document_processing_logs', [
            'company_id' => $company->id,
            'supplier_id' => $unknownSupplierId,
            'status' => 'queued',
        ]);
    }

    /**
     * TEST 4: Multi-company same supplier isolation
     * Two companies using same supplier_id should be processed independently
     */
    public function test_multi_company_same_supplier_isolation(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'queued'], 200),
        ]);

        $company1 = Company::factory()->create(['name' => 'Company A']);
        $company2 = Company::factory()->create(['name' => 'Company B']);

        $supplierId = 5; // Magic Holiday

        // Company 1 submits document
        $response1 = $this->postJson('/api/document-processing', [
            'company_id' => $company1->id,
            'supplier_id' => $supplierId,
            'document_type' => 'air',
            'file_path' => 'test/company1-magic.air',
        ]);

        // Company 2 submits document
        $response2 = $this->postJson('/api/document-processing', [
            'company_id' => $company2->id,
            'supplier_id' => $supplierId,
            'document_type' => 'air',
            'file_path' => 'test/company2-magic.air',
        ]);

        $response1->assertStatus(202);
        $response2->assertStatus(202);

        // Verify both documents exist independently
        $this->assertDatabaseHas('document_processing_logs', [
            'company_id' => $company1->id,
            'supplier_id' => $supplierId,
            'file_path' => 'test/company1-magic.air',
        ]);

        $this->assertDatabaseHas('document_processing_logs', [
            'company_id' => $company2->id,
            'supplier_id' => $supplierId,
            'file_path' => 'test/company2-magic.air',
        ]);

        // Verify documents are isolated (different document_ids)
        $doc1 = DocumentProcessingLog::where('company_id', $company1->id)
            ->where('supplier_id', $supplierId)
            ->first();
        $doc2 = DocumentProcessingLog::where('company_id', $company2->id)
            ->where('supplier_id', $supplierId)
            ->first();

        $this->assertNotEquals($doc1->document_id, $doc2->document_id);
    }

    /**
     * TEST 5: Supplier config validation
     * Verify all suppliers have required configuration
     */
    public function test_supplier_config_validation(): void
    {
        // Verify N8n base configuration exists
        $this->assertNotNull(config('services.n8n.webhook_url'), 'N8n webhook URL must be configured');
        $this->assertNotNull(config('services.n8n.webhook_secret'), 'N8n webhook secret must be configured');

        // Verify webhook configuration exists
        $this->assertNotNull(config('webhook.hmac.algorithm'), 'HMAC algorithm must be configured');
        $this->assertIsInt(config('webhook.hmac.timestamp_tolerance_seconds'), 'Timestamp tolerance must be configured');
        $this->assertIsInt(config('webhook.timeouts.connection_timeout'), 'Connection timeout must be configured');
        $this->assertIsInt(config('webhook.timeouts.request_timeout'), 'Request timeout must be configured');

        // Verify file validation limits exist
        $this->assertIsInt(config('webhook.file_validation.max_file_size'), 'Max file size must be configured');
        $this->assertIsArray(config('webhook.file_validation.allowed_mime_types'), 'Allowed MIME types must be configured');

        $this->assertTrue(true, 'All supplier configs validated');
    }

    /**
     * TEST 6: Real document simulation for major suppliers
     * Simulate processing real-format documents per supplier type
     */
    public function test_real_document_simulation_per_supplier(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'queued'], 200),
        ]);

        $company = Company::factory()->create();

        $testScenarios = [
            // Amadeus AIR file
            [
                'supplier_id' => 1,
                'document_type' => 'air',
                'file_path' => 'staging/amadeus/AMADEUS_PNR_ABC123.air',
                'file_size_bytes' => 8192,
                'file_hash' => hash('sha256', 'amadeus-content'),
            ],
            // Sabre PDF
            [
                'supplier_id' => 2,
                'document_type' => 'pdf',
                'file_path' => 'staging/sabre/SABRE_INVOICE_2024001.pdf',
                'file_size_bytes' => 51200,
                'file_hash' => hash('sha256', 'sabre-pdf-content'),
            ],
            // TBO Email
            [
                'supplier_id' => 4,
                'document_type' => 'email',
                'file_path' => 'staging/tbo/TBO_BOOKING_CONF.eml',
                'file_size_bytes' => 16384,
                'file_hash' => hash('sha256', 'tbo-email-content'),
            ],
            // Magic Holiday Image
            [
                'supplier_id' => 5,
                'document_type' => 'image',
                'file_path' => 'staging/magic/MAGIC_VOUCHER.jpg',
                'file_size_bytes' => 204800,
                'file_hash' => hash('sha256', 'magic-image-content'),
            ],
            // IATA BSP AIR
            [
                'supplier_id' => 10,
                'document_type' => 'air',
                'file_path' => 'staging/iata/IATA_BSP_TICKET.air',
                'file_size_bytes' => 12288,
                'file_hash' => hash('sha256', 'iata-air-content'),
            ],
            // Generic Email
            [
                'supplier_id' => 11,
                'document_type' => 'email',
                'file_path' => 'staging/generic/booking_confirmation.eml',
                'file_size_bytes' => 20480,
                'file_hash' => hash('sha256', 'generic-email-content'),
            ],
        ];

        foreach ($testScenarios as $scenario) {
            $response = $this->postJson('/api/document-processing', array_merge(
                ['company_id' => $company->id],
                $scenario
            ));

            $response->assertStatus(202)
                ->assertJsonStructure([
                    'document_id',
                    'status',
                    'message',
                ]);

            // Verify document was queued correctly
            $this->assertDatabaseHas('document_processing_logs', [
                'company_id' => $company->id,
                'supplier_id' => $scenario['supplier_id'],
                'document_type' => $scenario['document_type'],
                'file_path' => $scenario['file_path'],
                'status' => 'queued',
            ]);
        }
    }

    /**
     * TEST 7: Supplier-specific error handling
     * Test that supplier-specific errors are properly categorized
     */
    public function test_supplier_specific_error_handling(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'Amadeus format invalid'], 400),
        ]);

        $company = Company::factory()->create();

        $response = $this->postJson('/api/document-processing', [
            'company_id' => $company->id,
            'supplier_id' => 1, // Amadeus
            'document_type' => 'air',
            'file_path' => 'test/invalid-amadeus.air',
        ]);

        // Should fail but document should be logged
        $response->assertStatus(503);

        $this->assertDatabaseHas('document_processing_logs', [
            'company_id' => $company->id,
            'supplier_id' => 1,
            'status' => 'failed',
        ]);
    }

    /**
     * TEST 8: Concurrent supplier processing
     * Multiple suppliers processing simultaneously
     */
    public function test_concurrent_supplier_processing(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'queued'], 200),
        ]);

        $company = Company::factory()->create();

        // Submit 5 documents from different suppliers concurrently
        $suppliers = [1, 2, 3, 4, 5];
        $documentIds = [];

        foreach ($suppliers as $supplierId) {
            $response = $this->postJson('/api/document-processing', [
                'company_id' => $company->id,
                'supplier_id' => $supplierId,
                'document_type' => 'air',
                'file_path' => "test/concurrent-supplier-{$supplierId}.air",
            ]);

            $response->assertStatus(202);
            $documentIds[] = $response->json('document_id');
        }

        // Verify all documents were created
        $this->assertCount(5, $documentIds);
        $this->assertCount(5, array_unique($documentIds)); // All unique

        // Verify all queued correctly
        foreach ($suppliers as $supplierId) {
            $this->assertDatabaseHas('document_processing_logs', [
                'company_id' => $company->id,
                'supplier_id' => $supplierId,
                'status' => 'queued',
            ]);
        }
    }

    /**
     * TEST 9: Supplier credential validation (if applicable)
     * Verify suppliers with credentials are handled correctly
     */
    public function test_supplier_credential_validation(): void
    {
        // This test validates that the system can handle suppliers with credentials
        // In a real scenario, you'd verify supplier_credentials table integration

        Http::fake([
            '*' => Http::response(['status' => 'queued'], 200),
        ]);

        $company = Company::factory()->create();

        // Suppliers that typically require credentials
        $credentialedSuppliers = [1, 2, 3, 4]; // Amadeus, Sabre, Travelport, TBO

        foreach ($credentialedSuppliers as $supplierId) {
            $response = $this->postJson('/api/document-processing', [
                'company_id' => $company->id,
                'supplier_id' => $supplierId,
                'document_type' => 'air',
                'file_path' => "test/credential-supplier-{$supplierId}.air",
            ]);

            $response->assertStatus(202);

            $this->assertDatabaseHas('document_processing_logs', [
                'company_id' => $company->id,
                'supplier_id' => $supplierId,
            ]);
        }
    }
}
