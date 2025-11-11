<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Branch;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Charge>
 */
class ChargeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Tap', 'MyFatoorah', 'Hesabe', 'UPayment', 'CustomGateway']),
            'type' => 'Payment Gateway',
            'description' => $this->faker->sentence(),
            'api_key' => $this->faker->uuid(),
            'paid_by' => $this->faker->randomElement(['Client', 'Company']),
            'amount' => $this->faker->randomFloat(2, 1, 10),
            'extra_charge' => $this->faker->randomFloat(2, 0, 5),
            'self_charge' => $this->faker->randomFloat(2, 0, 5),
            'is_active' => $this->faker->boolean(80),
            'can_generate_link' => $this->faker->boolean(70),
            'charge_type' => $this->faker->randomElement(['Percent', 'Flat Rate']),
            'branch_id' => null,
            'is_auto_paid' => $this->faker->boolean(30),
            'has_url' => $this->faker->boolean(50),
            'can_charge_invoice' => $this->faker->boolean(60),
            'is_system_default' => false,
            'can_be_deleted' => true,
            'enabled_by' => 'company',
        ];
    }

    /**
     * Indicate that this is a system default gateway.
     */
    public function systemDefault(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $this->faker->randomElement(['Tap', 'MyFatoorah', 'Hesabe', 'UPayment']),
            'is_system_default' => true,
            'can_be_deleted' => false,
            'enabled_by' => 'admin',
        ]);
    }

    /**
     * Indicate that this is a custom gateway.
     */
    public function custom(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'CustomGateway' . $this->faker->randomNumber(3),
            'is_system_default' => false,
            'can_be_deleted' => true,
            'enabled_by' => 'company',
        ]);
    }

    /**
     * Indicate that the gateway can generate payment links.
     */
    public function withLinkGeneration(): static
    {
        return $this->state(fn (array $attributes) => [
            'can_generate_link' => true,
        ]);
    }

    /**
     * Indicate that the gateway is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the gateway is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
