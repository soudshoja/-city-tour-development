<?php

namespace Database\Factories;

use App\Models\DocumentProcessingLog;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DocumentProcessingLogFactory extends Factory
{
    protected $model = DocumentProcessingLog::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'supplier_id' => $this->faker->numberBetween(1, 10),
            'document_id' => Str::uuid()->toString(),
            'document_type' => $this->faker->randomElement(['air', 'pdf', 'image', 'email']),
            'file_path' => 'test_company/supplier_' . $this->faker->numberBetween(1, 10) . '/files_unprocessed/test_' . $this->faker->uuid . '.pdf',
            'file_size_bytes' => $this->faker->numberBetween(1000, 5000000),
            'file_hash' => hash('sha256', $this->faker->text),
            'status' => 'queued',
            'n8n_execution_id' => null,
            'n8n_workflow_id' => null,
            'extraction_result' => null,
            'error_code' => null,
            'error_message' => null,
            'error_context' => null,
            'hmac_signature' => null,
            'callback_received_at' => null,
            'processing_duration_ms' => null,
        ];
    }

    /**
     * State: Failed document
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_code' => $this->faker->randomElement([
                'ERR_FILE_NOT_FOUND',
                'ERR_EXTRACTION_FAILED',
                'ERR_TIMEOUT',
                'ERR_PARSE_FAILURE',
                'ERR_UNSUPPORTED_FORMAT',
            ]),
            'error_message' => $this->faker->sentence,
            'error_context' => [
                'file_path' => $attributes['file_path'] ?? 'test/path.pdf',
                'line' => $this->faker->numberBetween(1, 100),
            ],
            'n8n_execution_id' => 'exec-' . Str::random(12),
            'n8n_workflow_id' => 'wf-' . Str::random(12),
            'callback_received_at' => now(),
            'processing_duration_ms' => $this->faker->numberBetween(100, 5000),
        ]);
    }

    /**
     * State: Completed document
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'n8n_execution_id' => 'exec-' . Str::random(12),
            'n8n_workflow_id' => 'wf-' . Str::random(12),
            'extraction_result' => [
                'tasks' => [
                    [
                        'type' => 'flight',
                        'supplier_reference' => 'EK' . $this->faker->numberBetween(100, 999),
                        'passenger' => $this->faker->name,
                    ],
                ],
            ],
            'callback_received_at' => now(),
            'processing_duration_ms' => $this->faker->numberBetween(500, 3000),
        ]);
    }

    /**
     * State: Processing document
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'n8n_execution_id' => 'exec-' . Str::random(12),
            'n8n_workflow_id' => 'wf-' . Str::random(12),
        ]);
    }
}
