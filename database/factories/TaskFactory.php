<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\Agent;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => 1,
            'supplier_id' => 1,
            'type' => $this->faker->randomElement(['flight', 'hotel']),
            'status' => $this->faker->randomElement(['issued', 'confirmed', 'refund', 'void', 'reissued']),
            'supplier_status' => $this->faker->randomElement(['confirmed', 'pending', 'cancelled']),
            'client_name' => $this->faker->name(),
            'passenger_name' => $this->faker->name(),
            'reference' => strtoupper($this->faker->bothify('??###??')),
            'gds_reference' => $this->faker->regexify('[A-Z0-9]{6}'),
            'airline_reference' => $this->faker->regexify('[A-Z0-9]{6}'),
            'created_by' => $this->faker->name(),
            'issued_by' => $this->faker->regexify('[A-Z0-9]{8}'),
            'duration' => $this->faker->randomElement(['2h 30m', '1h 45m', '3h 15m']),
            'payment_type' => $this->faker->randomElement(['cash', 'credit_card', 'bank_transfer']),
            'price' => $this->faker->randomFloat(2, 100, 2000),
            'exchange_currency' => $this->faker->currencyCode(),
            'original_price' => $this->faker->randomFloat(2, 100, 2000),
            'original_currency' => $this->faker->currencyCode(),
            'tax' => $this->faker->randomFloat(2, 10, 200),
            'surcharge' => $this->faker->randomFloat(2, 0, 50),
            'penalty_fee' => $this->faker->randomFloat(2, 0, 100),
            'total' => $this->faker->randomFloat(2, 150, 2500),
            'cancellation_policy' => $this->faker->text(100),
            'cancellation_deadline' => $this->faker->dateTimeBetween('now', '+30 days'),
            'additional_info' => $this->faker->optional()->text(),
            'ticket_number' => $this->faker->regexify('[0-9]{13}'),
            'file_name' => $this->faker->optional()->word() . '.air',
            'venue' => $this->faker->optional()->city(),
            'invoice_price' => $this->faker->optional()->randomFloat(2, 100, 2000),
            'voucher_status' => $this->faker->randomElement(['pending', 'issued', 'used']),
            'enabled' => true,
            'refund_charge' => $this->faker->optional()->randomFloat(2, 0, 100),
            'refund_date' => $this->faker->optional()->date(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function flight(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'flight',
        ]);
    }

    public function hotel(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'hotel',
        ]);
    }

    public function issued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'issued',
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }
}
