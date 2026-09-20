<?php

namespace Database\Factories;

use App\Models\EggGrade;
use App\Models\EggGrading;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EggGrading>
 */
class EggGradingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grading_date' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'egg_grade_id' => EggGrade::factory(),
            'quantity' => fake()->numberBetween(1, 500),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
