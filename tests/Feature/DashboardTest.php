<?php

use App\Models\EggGrade;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Sale;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Profit and loss')
        ->assertSee('No financial activity in this period')
        ->assertSee('No sales in this period')
        ->assertSee('No expenses in this period');
});

test('authenticated pages provide the mobile navigation destinations', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSee('aria-label="Main navigation"', false)
        ->assertSee('href="'.route('egg-operations.index').'"', false)
        ->assertSee('href="'.route('sales.index').'"', false)
        ->assertSee('href="'.route('expenses.index').'"', false)
        ->assertSee('id="mobile-more-menu"', false);
});

test('dashboard calculates exact profit and loss for the current month', function () {
    $this->travelTo('2026-09-21 12:00:00');

    $user = User::factory()->create();
    $premiumGrade = EggGrade::factory()->create(['name' => 'Premium']);
    $standardGrade = EggGrade::factory()->create(['name' => 'Standard']);
    $feedCategory = ExpenseCategory::factory()->create(['name' => 'Feed']);
    $utilitiesCategory = ExpenseCategory::factory()->create(['name' => 'Utilities']);

    Sale::factory()->for($premiumGrade, 'eggGrade')->create([
        'sale_date' => '2026-09-05',
        'total_amount' => '100.10',
        'normalized_egg_quantity' => 100,
    ]);
    Sale::factory()->for($standardGrade, 'eggGrade')->create([
        'sale_date' => '2026-09-20',
        'total_amount' => '50.25',
        'normalized_egg_quantity' => 60,
    ]);
    Sale::factory()->for($premiumGrade, 'eggGrade')->create([
        'sale_date' => '2026-08-31',
        'total_amount' => '999.00',
        'normalized_egg_quantity' => 999,
    ]);

    Expense::factory()->for($feedCategory, 'expenseCategory')->create([
        'expense_date' => '2026-09-10',
        'amount' => '30.05',
    ]);
    Expense::factory()->for($utilitiesCategory, 'expenseCategory')->create([
        'expense_date' => '2026-09-18',
        'amount' => '20.10',
    ]);
    Expense::factory()->for($feedCategory, 'expenseCategory')->create([
        'expense_date' => '2026-08-31',
        'amount' => '888.00',
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard.index')
        ->assertSee('150.35')
        ->assertSee('50.15')
        ->assertSee('100.20')
        ->assertSee('160')
        ->assertSee('Premium')
        ->assertSee('Standard')
        ->assertSee('Feed')
        ->assertSee('Utilities');
});

test('dashboard applies a custom reporting period', function () {
    $this->travelTo('2026-09-21 12:00:00');

    $user = User::factory()->create();
    Sale::factory()->create([
        'sale_date' => '2026-08-15',
        'total_amount' => '75.40',
    ]);
    Expense::factory()->create([
        'expense_date' => '2026-08-16',
        'amount' => '25.15',
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::dashboard.index')
        ->set('startDate', '2026-08-01')
        ->set('endDate', '2026-08-31')
        ->call('applyPeriod')
        ->assertHasNoErrors()
        ->assertSet('appliedStartDate', '2026-08-01')
        ->assertSet('appliedEndDate', '2026-08-31')
        ->assertSee('75.40')
        ->assertSee('25.15')
        ->assertSee('50.25');

    $component
        ->call('resetPeriod')
        ->assertSet('appliedStartDate', '2026-09-01')
        ->assertSet('appliedEndDate', '2026-09-21');
});

test('dashboard applies preset reporting periods', function () {
    $this->travelTo('2026-09-21 12:00:00');

    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::dashboard.index')
        ->set('period', 'last_7_days')
        ->assertSet('appliedStartDate', '2026-09-15')
        ->assertSet('appliedEndDate', '2026-09-21');

    $component
        ->set('period', 'today')
        ->assertSet('appliedStartDate', '2026-09-21')
        ->assertSet('appliedEndDate', '2026-09-21');

    $component
        ->set('period', 'this_year')
        ->assertSet('appliedStartDate', '2026-01-01')
        ->assertSet('appliedEndDate', '2026-09-21');
});

test('dashboard rejects a reporting period whose end precedes its start', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard.index')
        ->set('startDate', '2026-09-30')
        ->set('endDate', '2026-09-01')
        ->call('applyPeriod')
        ->assertHasErrors(['startDate', 'endDate']);
});

test('dashboard identifies an operating loss', function () {
    $this->travelTo('2026-09-21 12:00:00');

    $user = User::factory()->create();
    Sale::factory()->create([
        'sale_date' => '2026-09-10',
        'total_amount' => '50.00',
    ]);
    Expense::factory()->create([
        'expense_date' => '2026-09-10',
        'amount' => '200.00',
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard.index')
        ->assertSee('Loss')
        ->assertSee('-150.00');
});

test('dashboard filter actions require authentication', function () {
    $component = Livewire::test('pages::dashboard.index')
        ->set('startDate', '2026-09-01')
        ->set('endDate', '2026-09-30')
        ->call('applyPeriod')
        ->assertForbidden();

    Livewire::test('pages::dashboard.index')
        ->call('resetPeriod')
        ->assertForbidden();
});
