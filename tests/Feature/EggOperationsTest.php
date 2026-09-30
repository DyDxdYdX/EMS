<?php

use App\Models\EggGrade;
use App\Models\EggGrading;
use App\Models\Production;
use App\Models\Sale;
use App\Models\StockAdjustment;
use App\Models\User;
use Livewire\Livewire;

test('guests cannot open egg operations', function () {
    $this->get(route('egg-operations.index'))->assertRedirectToRoute('login');
});

test('old egg operation URLs redirect to their matching tabs', function (string $oldRoute, string $tab) {
    $this->actingAs(User::factory()->create())
        ->get(route($oldRoute))
        ->assertRedirect(route('egg-operations.index', ['tab' => $tab]));
})->with([
    ['productions.index', 'production'],
    ['gradings.index', 'grading'],
    ['stock-adjustments.index', 'adjustments'],
]);

test('egg operations defaults to production and keeps other tasks behind bookmarkable tabs', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('egg-operations.index'))
        ->assertOk()
        ->assertSee('data-test="egg-tab-production"', escape: false)
        ->assertSee('aria-current="page"', escape: false)
        ->assertSee(route('egg-operations.index', ['tab' => 'grading']))
        ->assertSee(route('egg-operations.index', ['tab' => 'adjustments']))
        ->assertSee('Record production');
});

test('each egg operation tab shows its own recording action', function (string $tab, string $action) {
    $this->actingAs(User::factory()->create())
        ->get(route('egg-operations.index', ['tab' => $tab]))
        ->assertOk()
        ->assertSee($action);
})->with([
    ['production', 'Record production'],
    ['grading', 'Record grading'],
    ['adjustments', 'Add adjustment'],
]);

test('an unknown egg operation tab falls back to production', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('egg-operations.index', ['tab' => 'unknown']))
        ->assertOk()
        ->assertSee('Record production');
});

test('dashboard shortcuts open the matching egg operations tabs', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertSee(route('egg-operations.index', ['tab' => 'production']))
        ->assertSee(route('egg-operations.index', ['tab' => 'grading']))
        ->assertSee(route('egg-operations.index', ['tab' => 'adjustments']))
        ->assertDontSee('href="'.route('productions.index').'"', escape: false)
        ->assertDontSee('href="'.route('gradings.index').'"', escape: false)
        ->assertDontSee('href="'.route('stock-adjustments.index').'"', escape: false);
});

test('egg operation overview reflects ungraded eggs and stock after sales and adjustments', function () {
    $grade = EggGrade::factory()->create();
    Production::factory()->create(['total_eggs' => 100, 'damaged_eggs' => 10]);
    EggGrading::factory()->for($grade)->create(['quantity' => 60]);
    Sale::factory()->for($grade)->create(['normalized_egg_quantity' => 12]);
    StockAdjustment::factory()->for($grade)->create(['type' => 'add', 'quantity' => 5]);
    StockAdjustment::factory()->for($grade)->remove()->create(['quantity' => 3]);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::egg-operations.index')
        ->assertSee('Available to grade')
        ->assertSee('30')
        ->assertSee('Graded stock on hand')
        ->assertSee('50');
});

test('saving production offers grading as an optional next step', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::productions.index')
        ->set('productionDate', '2026-09-25')
        ->set('totalEggs', 80)
        ->set('damagedEggs', 5)
        ->call('saveProduction')
        ->assertHasNoErrors()
        ->assertDispatched('egg-operations-changed', showGradingPrompt: true);

    $this->assertDatabaseHas('productions', ['total_eggs' => 80, 'damaged_eggs' => 5]);

    Livewire::actingAs($user)
        ->test('pages::egg-operations.index')
        ->dispatch('egg-operations-changed', showGradingPrompt: true)
        ->assertSee('When you are ready, record grading')
        ->assertSee(route('egg-operations.index', ['tab' => 'grading']));
});

test('production without gradable eggs does not suggest grading', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::productions.index')
        ->set('productionDate', '2026-09-26')
        ->set('totalEggs', 10)
        ->set('damagedEggs', 10)
        ->call('saveProduction')
        ->assertHasNoErrors()
        ->assertDispatched('egg-operations-changed', showGradingPrompt: false);

    $this->assertDatabaseHas('productions', ['total_eggs' => 10, 'damaged_eggs' => 10]);
});
