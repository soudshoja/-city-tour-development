<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Account;
use App\Models\User;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id' => 1, // Will be overridden in tests
            'client_id' => 1, // Will be overridden in tests
            'voucher_number' => $this->faker->unique()->regexify('[A-Z]{2}[0-9]{6}'),
            'payment_reference' => $this->faker->unique()->regexify('PAY-[0-9]{8}'),
            'invoice_id' => 1, // Will be overridden in tests
            'account_id' => 1, // Will be overridden in tests
            'from' => $this->faker->name(),
            'pay_to' => $this->faker->company(),
            'created_by' => 1, // Will be overridden in tests
            'service_charge' => $this->faker->randomFloat(2, 0, 50),
            'amount' => $this->faker->randomFloat(2, 100, 5000),
            'currency' => $this->faker->randomElement(['USD', 'EUR', 'GBP', 'SAR', 'AED']),
            'payment_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'notes' => $this->faker->optional()->sentence(),
            'payment_gateway' => $this->faker->randomElement(['stripe', 'paypal', 'bank_transfer', 'cash', 'none']),
            'payment_method_id' => null, // Optional, will be set if needed
            'payment_url' => $this->faker->optional()->url(),
            'expiry_date' => $this->faker->optional()->dateTimeBetween('now', '+1 year'),
            'status' => $this->faker->randomElement(['pending', 'completed', 'failed', 'cancelled', 'refunded']),
            'account_number' => $this->faker->optional()->numerify('##########'),
            'bank_name' => $this->faker->optional()->company() . ' Bank',
            'swift_no' => $this->faker->optional()->regexify('[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?'),
            'iban_no' => $this->faker->optional()->iban(),
            'country' => $this->faker->optional()->country(),
            'tax' => $this->faker->optional()->randomFloat(2, 0, 100),
            'shipping' => $this->faker->optional()->randomFloat(2, 0, 50),
            'completed' => $this->faker->boolean(70), // 70% chance of being completed
        ];
    }

    /**
     * Indicate that the payment is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'completed' => true,
            'payment_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Indicate that the payment is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'completed' => false,
            'payment_date' => null,
        ]);
    }

    /**
     * Indicate that the payment failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'completed' => false,
            'payment_date' => null,
        ]);
    }

    /**
     * Indicate that the payment was refunded.
     */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refunded',
            'completed' => true,
        ]);
    }

    /**
     * Indicate that the payment uses bank transfer.
     */
    public function bankTransfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_gateway' => 'bank_transfer',
            'account_number' => $this->faker->numerify('##########'),
            'bank_name' => $this->faker->company() . ' Bank',
            'swift_no' => $this->faker->regexify('[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?'),
            'iban_no' => $this->faker->iban(),
        ]);
    }

    /**
     * Indicate that the payment uses online gateway.
     */
    public function online(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_gateway' => $this->faker->randomElement(['stripe', 'paypal']),
            'payment_url' => $this->faker->url(),
            'expiry_date' => $this->faker->dateTimeBetween('+1 hour', '+24 hours'),
        ]);
    }

    /**
     * Indicate that the payment is cash.
     */
    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_gateway' => 'cash',
            'payment_url' => null,
            'account_number' => null,
            'bank_name' => null,
            'swift_no' => null,
            'iban_no' => null,
        ]);
    }

    /**
     * Create a high-value payment.
     */
    public function highValue(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $this->faker->randomFloat(2, 5000, 50000),
            'service_charge' => $this->faker->randomFloat(2, 50, 500),
        ]);
    }

    /**
     * Create a payment with minimal data.
     */
    public function minimal(): static
    {
        return $this->state(fn (array $attributes) => [
            'voucher_number' => null,
            'account_id' => null,
            'from' => null,
            'pay_to' => null,
            'service_charge' => null,
            'notes' => null,
            'payment_method_id' => null,
            'payment_url' => null,
            'expiry_date' => null,
            'account_number' => null,
            'bank_name' => null,
            'swift_no' => null,
            'iban_no' => null,
            'country' => null,
            'tax' => null,
            'shipping' => null,
        ]);
    }
}
