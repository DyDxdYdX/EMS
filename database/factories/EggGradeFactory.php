<?php

namespace Database\Factories;

use App\Models\EggGrade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EggGrade>
 */
class EggGradeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Grade '.fake()->unique()->bothify('?##'),
            'is_active' => true,
            'weight_range' => fake()->optional()->randomElement([
                '45–49.9 g',
                '50–54.9 g',
                '55–59.9 g',
                '60–64.9 g',
                '65–69.9 g',
            ]),
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
