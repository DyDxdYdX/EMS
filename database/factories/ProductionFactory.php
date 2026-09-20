<?php

namespace Database\Factories;

use App\Models\Production;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Production>
 */
class ProductionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalEggs = fake()->numberBetween(1, 500);

        return [
            'production_date' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'total_eggs' => $totalEggs,
            'damaged_eggs' => fake()->numberBetween(0, $totalEggs),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
