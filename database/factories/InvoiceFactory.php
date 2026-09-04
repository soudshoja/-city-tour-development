<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Client;
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
            // Merge fixup: invoices.client_id is a required (NOT NULL) foreignId
            // (migration: $table->foreignId('client_id')->constrained()), so a bare
            // null default always violated that constraint on the real MySQL
            // connection this suite runs against — pre-existing on both merge
            // parents, only surfaced now because tests/Unit/Vouchers/
            // VoucherDataRepositoryTest.php (new, theirs) is the first test to call
            // Invoice::factory()->create() without an explicit client_id override.
            'client_id' => Client::factory(),
            // Same NOT NULL / constrained() gap as client_id above.
            'agent_id' => Agent::factory(),
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
            'tax' => $this->faker->randomFloat(2, 0, 20),
            'discount' => $this->faker->randomFloat(2, 0, 20),
            'shipping' => $this->faker->randomFloat(2, 0, 50),
            'accept_payment' => true,
            'payment_type' => 'credit_card',
            'is_client_credit' => false,
        ];
    }
}
