<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amountCents = fake()->numberBetween(100, 100000);

        return [
            'expense_date' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'title' => fake()->sentence(3),
            'expense_category_id' => ExpenseCategory::factory(),
            'amount' => number_format($amountCents / 100, 2, '.', ''),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
