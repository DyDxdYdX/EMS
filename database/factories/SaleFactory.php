<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\EggGrade;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 100);
        $unitPriceCents = fake()->numberBetween(30, 2000);

        return [
            'sale_date' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'customer_id' => fake()->boolean(70) ? Customer::factory() : null,
            'egg_grade_id' => EggGrade::factory(),
            'unit' => 'egg',
            'quantity' => $quantity,
            'unit_price' => number_format($unitPriceCents / 100, 2, '.', ''),
            'total_amount' => number_format(($quantity * $unitPriceCents) / 100, 2, '.', ''),
            'normalized_egg_quantity' => $quantity,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function tray(int $eggsPerTray = 30): static
    {
        return $this->state(function (array $attributes) use ($eggsPerTray): array {
            $quantity = fake()->numberBetween(1, 10);
            $unitPriceCents = fake()->numberBetween(1000, 3000);

            return [
                'unit' => 'tray',
                'quantity' => $quantity,
                'unit_price' => number_format($unitPriceCents / 100, 2, '.', ''),
                'total_amount' => number_format(($quantity * $unitPriceCents) / 100, 2, '.', ''),
                'normalized_egg_quantity' => $quantity * $eggsPerTray,
            ];
        });
    }
}
