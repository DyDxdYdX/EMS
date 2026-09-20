<?php

namespace Database\Seeders;

use App\Models\EggGrade;
use App\Models\ExpenseCategory;
use App\Models\FarmSetting;
use Illuminate\Database\Seeder;

class FarmSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        FarmSetting::query()->firstOrCreate(
            ['id' => 1],
            ['eggs_per_tray' => 30],
        );

        $eggGrades = [
            ['name' => 'Grade AA', 'weight_range' => 'More than 70 g'],
            ['name' => 'Grade A', 'weight_range' => '65–69.9 g'],
            ['name' => 'Grade B', 'weight_range' => '60–64.9 g'],
            ['name' => 'Grade C', 'weight_range' => '55–59.9 g'],
            ['name' => 'Grade D', 'weight_range' => '50–54.9 g'],
            ['name' => 'Grade E', 'weight_range' => '45–49.9 g'],
            ['name' => 'Grade F', 'weight_range' => 'Below 45 g'],
        ];

        foreach ($eggGrades as $index => $eggGrade) {
            EggGrade::query()->firstOrCreate(
                ['name' => $eggGrade['name']],
                [
                    'is_active' => true,
                    'weight_range' => $eggGrade['weight_range'],
                    'sort_order' => $index + 1,
                ],
            );
        }

        $expenseCategories = [
            'Feed',
            'Transport',
            'Packaging',
            'Utilities',
            'Labour',
            'Maintenance',
            'Other',
        ];

        foreach ($expenseCategories as $index => $expenseCategory) {
            ExpenseCategory::query()->firstOrCreate(
                ['name' => $expenseCategory],
                [
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }
    }
}
