<?php

namespace Database\Factories;

use App\Models\EggGrade;
use App\Models\StockAdjustment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAdjustment>
 */
class StockAdjustmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'adjustment_date' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'egg_grade_id' => EggGrade::factory(),
            'type' => 'add',
            'quantity' => fake()->numberBetween(1, 100),
            'reason' => fake()->randomElement([
                'Counting correction',
                'Manual correction',
                'Recovered stock',
            ]),
        ];
    }

    public function remove(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'remove',
            'reason' => fake()->randomElement([
                'Broken eggs',
                'Damaged eggs',
                'Missing stock',
            ]),
        ]);
    }
}
