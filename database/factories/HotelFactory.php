<?php

namespace Database\Factories;

use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Merge fixup: no HotelFactory existed on either merge parent, but
 * TaskHotelDetailFactory's hotel_id (NOT NULL, FK to hotels.id, no seeded
 * rows in a fresh RefreshDatabase run) needs a real parent to be created
 * against — same class of fix as InvoiceFactory/AgentFactory/BranchFactory
 * above.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Hotel>
 */
class HotelFactory extends Factory
{
    protected $model = Hotel::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company().' Hotel',
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'country' => $this->faker->country(),
            'rating' => (string) $this->faker->numberBetween(1, 5),
        ];
    }
}
