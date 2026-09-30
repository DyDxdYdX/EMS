<?php

use App\Models\EggGrade;
use App\Models\EggGrading;
use App\Models\Sale;
use App\Models\StockAdjustment;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected from stock adjustment management', function () {
    $this->get(route('stock-adjustments.index'))->assertRedirectToRoute('login');
});

test('stock additions increase grade inventory', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::stock-adjustments.index')
        ->set('adjustmentDate', '2026-09-21')
        ->set('eggGradeId', $grade->id)
        ->set('type', 'add')
        ->set('quantity', 40)
        ->set('reason', 'Opening stock correction')
        ->call('saveAdjustment')
        ->assertHasNoErrors();

    $adjustment = StockAdjustment::query()->sole();

    expect($adjustment)
        ->adjustment_date->toDateString()->toBe('2026-09-21')
        ->egg_grade_id->toBe($grade->id)
        ->type->toBe('add')
        ->quantity->toBe(40)
        ->reason->toBe('Opening stock correction')
        ->and($grade->stockQuantity())->toBe(40);
});

test('stock removals decrease grade inventory', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->create();
    EggGrading::factory()->for($grade, 'eggGrade')->create(['quantity' => 100]);

    Livewire::actingAs($user)
        ->test('pages::stock-adjustments.index')
        ->set('adjustmentDate', '2026-09-21')
        ->set('eggGradeId', $grade->id)
        ->set('type', 'remove')
        ->set('quantity', 15)
        ->set('reason', 'Broken eggs')
        ->call('saveAdjustment')
        ->assertHasNoErrors();

    expect($grade->stockQuantity())->toBe(85);
});

test('stock removals cannot reduce inventory below zero', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->create(['name' => 'Grade A']);
    EggGrading::factory()->for($grade, 'eggGrade')->create(['quantity' => 10]);

    Livewire::actingAs($user)
        ->test('pages::stock-adjustments.index')
        ->set('adjustmentDate', '2026-09-21')
        ->set('eggGradeId', $grade->id)
        ->set('type', 'remove')
        ->set('quantity', 11)
        ->set('reason', 'Count correction')
        ->call('saveAdjustment')
        ->assertHasErrors(['quantity']);

    expect(StockAdjustment::query()->count())->toBe(0)
        ->and($grade->stockQuantity())->toBe(10);
});

test('adjustments require a valid type positive quantity and reason', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::stock-adjustments.index')
        ->set('adjustmentDate', '2026-09-21')
        ->set('eggGradeId', $grade->id)
        ->set('type', 'transfer')
        ->set('quantity', 0)
        ->set('reason', '')
        ->call('saveAdjustment')
        ->assertHasErrors(['type', 'quantity', 'reason']);

    expect(StockAdjustment::query()->count())->toBe(0);
});

test('new adjustments cannot use an inactive egg grade', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->inactive()->create();

    Livewire::actingAs($user)
        ->test('pages::stock-adjustments.index')
        ->set('adjustmentDate', '2026-09-21')
        ->set('eggGradeId', $grade->id)
        ->set('type', 'add')
        ->set('quantity', 10)
        ->set('reason', 'Count correction')
        ->call('saveAdjustment')
        ->assertHasErrors(['eggGradeId']);

    expect(StockAdjustment::query()->count())->toBe(0);
});

test('existing adjustments can retain their inactive egg grade', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->inactive()->create();
    $adjustment = StockAdjustment::factory()->for($grade, 'eggGrade')->create([
        'type' => 'add',
        'quantity' => 20,
    ]);

    Livewire::actingAs($user)
        ->test('pages::stock-adjustments.index')
        ->call('editAdjustment', $adjustment->id)
        ->set('quantity', 25)
        ->set('reason', 'Corrected count')
        ->call('saveAdjustment')
        ->assertHasNoErrors();

    expect($adjustment->refresh())
        ->quantity->toBe(25)
        ->reason->toBe('Corrected count');
});

test('editing a stock addition cannot make already used inventory negative', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->create();
    $adjustment = StockAdjustment::factory()->for($grade, 'eggGrade')->create([
        'type' => 'add',
        'quantity' => 50,
    ]);
    Sale::factory()->for($grade, 'eggGrade')->create([
        'normalized_egg_quantity' => 40,
    ]);

    Livewire::actingAs($user)
        ->test('pages::stock-adjustments.index')
        ->call('editAdjustment', $adjustment->id)
        ->set('quantity', 20)
        ->call('saveAdjustment')
        ->assertHasErrors(['quantity']);

    expect($adjustment->refresh()->quantity)->toBe(50)
        ->and($grade->stockQuantity())->toBe(10);
});

test('adjustments can be moved between grades when both stocks remain valid', function () {
    $user = User::factory()->create();
    $sourceGrade = EggGrade::factory()->create();
    $targetGrade = EggGrade::factory()->create();
    $adjustment = StockAdjustment::factory()->for($sourceGrade, 'eggGrade')->create([
        'type' => 'add',
        'quantity' => 30,
    ]);
    EggGrading::factory()->for($targetGrade, 'eggGrade')->create(['quantity' => 20]);

    Livewire::actingAs($user)
        ->test('pages::stock-adjustments.index')
        ->call('editAdjustment', $adjustment->id)
        ->set('eggGradeId', $targetGrade->id)
        ->set('type', 'remove')
        ->set('quantity', 5)
        ->set('reason', 'Transferred correction')
        ->call('saveAdjustment')
        ->assertHasNoErrors();

    expect($adjustment->refresh())
        ->egg_grade_id->toBe($targetGrade->id)
        ->type->toBe('remove')
        ->quantity->toBe(5)
        ->and($sourceGrade->stockQuantity())->toBe(0)
        ->and($targetGrade->stockQuantity())->toBe(15);
});

test('deleting a removal restores stock', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->create();
    EggGrading::factory()->for($grade, 'eggGrade')->create(['quantity' => 50]);
    $adjustment = StockAdjustment::factory()->for($grade, 'eggGrade')->remove()->create([
        'quantity' => 10,
    ]);

    Livewire::actingAs($user)
        ->test('pages::stock-adjustments.index')
        ->call('confirmAdjustmentDeletion', $adjustment->id)
        ->call('deleteAdjustment')
        ->assertHasNoErrors();

    expect($adjustment->fresh())->toBeNull()
        ->and($grade->stockQuantity())->toBe(50);
});

test('unused stock additions can be deleted', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->create();
    $adjustment = StockAdjustment::factory()->for($grade, 'eggGrade')->create([
        'type' => 'add',
        'quantity' => 30,
    ]);

    Livewire::actingAs($user)
        ->test('pages::stock-adjustments.index')
        ->call('confirmAdjustmentDeletion', $adjustment->id)
        ->call('deleteAdjustment')
        ->assertHasNoErrors();

    expect($adjustment->fresh())->toBeNull()
        ->and($grade->stockQuantity())->toBe(0);
});

test('stock additions cannot be deleted after their inventory is used', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->create();
    $adjustment = StockAdjustment::factory()->for($grade, 'eggGrade')->create([
        'type' => 'add',
        'quantity' => 30,
    ]);
    Sale::factory()->for($grade, 'eggGrade')->create([
        'normalized_egg_quantity' => 20,
    ]);

    Livewire::actingAs($user)
        ->test('pages::stock-adjustments.index')
        ->call('confirmAdjustmentDeletion', $adjustment->id)
        ->call('deleteAdjustment')
        ->assertHasErrors(['deleteAdjustment']);

    expect($adjustment->fresh())->not->toBeNull()
        ->and($grade->stockQuantity())->toBe(10);
});

test('adjustment reasons are escaped when rendered', function () {
    $user = User::factory()->create();
    StockAdjustment::factory()->create([
        'reason' => '<script>alert("stock")</script>',
    ]);

    $this->actingAs($user)
        ->get(route('egg-operations.index', ['tab' => 'adjustments']))
        ->assertOk()
        ->assertSee('&lt;script&gt;', escape: false)
        ->assertDontSee('<script>alert("stock")</script>', escape: false);
});

test('stock adjustment write actions require authentication', function () {
    $grade = EggGrade::factory()->create();

    Livewire::test('pages::stock-adjustments.index')
        ->set('adjustmentDate', '2026-09-21')
        ->set('eggGradeId', $grade->id)
        ->set('type', 'add')
        ->set('quantity', 10)
        ->set('reason', 'Unauthorized correction')
        ->call('saveAdjustment')
        ->assertForbidden();

    expect(StockAdjustment::query()->count())->toBe(0);
});
