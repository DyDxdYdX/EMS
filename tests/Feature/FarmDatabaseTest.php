<?php

use App\Models\Customer;
use App\Models\EggGrade;
use App\Models\EggGrading;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FarmSetting;
use App\Models\Production;
use App\Models\Sale;
use App\Models\StockAdjustment;
use Carbon\CarbonInterface;
use Database\Seeders\FarmSeeder;
use Illuminate\Database\QueryException;

test('farm seeder creates the default configuration once', function () {
    $this->seed(FarmSeeder::class);
    $this->seed(FarmSeeder::class);

    expect(FarmSetting::query()->sole()->eggs_per_tray)->toBe(30)
        ->and(EggGrade::query()->orderBy('sort_order')->pluck('name')->all())->toBe([
            'Grade AA',
            'Grade A',
            'Grade B',
            'Grade C',
            'Grade D',
            'Grade E',
            'Grade F',
        ])
        ->and(EggGrade::query()->where('name', 'Grade C')->value('weight_range'))->toBe('55–59.9 g')
        ->and(ExpenseCategory::query()->orderBy('sort_order')->pluck('name')->all())->toBe([
            'Feed',
            'Transport',
            'Packaging',
            'Utilities',
            'Labour',
            'Maintenance',
            'Other',
        ]);
});

test('farm models persist their relationships and casts', function () {
    $eggGrade = EggGrade::factory()->create(['is_active' => false]);
    $customer = Customer::factory()->create();
    $expenseCategory = ExpenseCategory::factory()->create();

    $grading = EggGrading::factory()->for($eggGrade)->create();
    $sale = Sale::factory()->for($eggGrade)->for($customer)->create([
        'unit_price' => '0.50',
        'total_amount' => '5.00',
    ]);
    $adjustment = StockAdjustment::factory()->for($eggGrade)->create();
    $expense = Expense::factory()->for($expenseCategory)->create(['amount' => '125.40']);

    expect($eggGrade->is_active)->toBeFalse()
        ->and($grading->eggGrade->is($eggGrade))->toBeTrue()
        ->and($grading->grading_date)->toBeInstanceOf(CarbonInterface::class)
        ->and($sale->customer->is($customer))->toBeTrue()
        ->and($sale->eggGrade->is($eggGrade))->toBeTrue()
        ->and($sale->unit_price)->toBe('0.50')
        ->and($sale->total_amount)->toBe('5.00')
        ->and($adjustment->eggGrade->is($eggGrade))->toBeTrue()
        ->and($expense->expenseCategory->is($expenseCategory))->toBeTrue()
        ->and($expense->amount)->toBe('125.40');
});

test('deleting a customer keeps the sale and clears its customer', function () {
    $customer = Customer::factory()->create();
    $sale = Sale::factory()->for($customer)->create();

    $customer->delete();

    expect($sale->refresh()->customer_id)->toBeNull();
});

test('egg grades with stock history cannot be deleted', function () {
    $eggGrade = EggGrade::factory()->create();
    EggGrading::factory()->for($eggGrade)->create();

    expect(fn () => $eggGrade->delete())->toThrow(QueryException::class);
});

test('expense categories with expenses cannot be deleted', function () {
    $expenseCategory = ExpenseCategory::factory()->create();
    Expense::factory()->for($expenseCategory)->create();

    expect(fn () => $expenseCategory->delete())->toThrow(QueryException::class);
});

test('production dates are unique', function () {
    Production::factory()->create(['production_date' => '2026-09-20']);

    expect(fn () => Production::factory()->create([
        'production_date' => '2026-09-20',
    ]))->toThrow(QueryException::class);
});
