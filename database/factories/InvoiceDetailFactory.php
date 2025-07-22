<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoiceDetail>
 */
class InvoiceDetailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => 1, // Will be overridden in tests
            'invoice_number' => $this->faker->unique()->numerify('INV-DETAIL-#####'),
            'task_id' => null,
            'task_description' => $this->faker->sentence(),
            'task_remark' => $this->faker->text(),
            'client_notes' => $this->faker->paragraph(),
            'task_price' => $this->faker->randomFloat(2, 10, 1000),
            'supplier_price' => $this->faker->randomFloat(2, 5, 500),
            'markup_price' => $this->faker->randomFloat(2, 0, 100),
            'paid' => false,  
        ];
    }
}
