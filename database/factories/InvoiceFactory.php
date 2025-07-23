<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_number' => $this->faker->unique()->numerify('INV-#####'),
            'client_id' => null,
            'agent_id' => null, // Will be overridden in tests
            'currency' => 'USD',
            'sub_amount' => $this->faker->randomFloat(2, 100, 10000),
            'amount' => $this->faker->randomFloat(2, 100, 10000),
            'status' => 'unpaid',
            'invoice_date' => now(),
            'paid_date' => null,
            'due_date' => now()->addDays(30),
            'label' => $this->faker->word(),
            'account_number' => $this->faker->bankAccountNumber(),
            'bank_name' => $this->faker->company(),
            'swift_no' => $this->faker->swiftBicNumber(),
            'iban_no' => $this->faker->iban(),
            'country_id' => $this->faker->numberBetween(1, 200), // Assuming country IDs are between 1 and 200
            'tax' => $this->faker->randomFloat(2, 0, 20),
            'discount' => $this->faker->randomFloat(2, 0, 20),
            'shipping' => $this->faker->randomFloat(2, 0, 50),
            'accept_payment' => true,
            'payment_type' => 'credit_card',
            'is_client_credit' => false,
        ];
    }
}
