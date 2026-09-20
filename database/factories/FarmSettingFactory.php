<?php

namespace Database\Factories;

use App\Models\FarmSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FarmSetting>
 */
class FarmSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'eggs_per_tray' => 30,
        ];
    }
}
