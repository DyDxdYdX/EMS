<?php

use App\Models\EggGrade;
use App\Models\EggGrading;
use App\Models\Production;
use App\Models\Sale;
use App\Models\StockAdjustment;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected from egg grading management', function () {
    $this->get(route('gradings.index'))->assertRedirectToRoute('login');
});

test('authenticated users can view current stock by grade', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->create(['name' => 'Grade A']);
    EggGrading::factory()->for($grade)->create(['quantity' => 100]);
    Sale::factory()->for($grade)->create(['normalized_egg_quantity' => 20]);
    StockAdjustment::factory()->for($grade)->create(['type' => 'add', 'quantity' => 5]);
    StockAdjustment::factory()->for($grade)->remove()->create(['quantity' => 7]);

    $this->actingAs($user)
        ->get(route('egg-operations.index', ['tab' => 'grading']))
        ->assertOk()
        ->assertSee('Grade A')
        ->assertSee('78');

    expect($grade->stockQuantity())->toBe(78);
});

test('grading records add eggs to grade stock', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->create();
    Production::factory()->create([
        'production_date' => '2026-09-20',
        'total_eggs' => 100,
        'damaged_eggs' => 10,
    ]);

    Livewire::actingAs($user)
        ->test('pages::gradings.index')
        ->set('gradingDate', '2026-09-21')
        ->set('eggGradeId', $grade->id)
        ->set('quantity', 60)
        ->set('notes', 'First grading run')
        ->call('saveGrading')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('egg_gradings', [
        'grading_date' => '2026-09-21 00:00:00',
        'egg_grade_id' => $grade->id,
        'quantity' => 60,
        'notes' => 'First grading run',
    ]);
    expect($grade->stockQuantity())->toBe(60)
        ->and(EggGrading::availableEggQuantity())->toBe(30);
});

test('grading cannot exceed ungraded production', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->create();
    Production::factory()->create(['total_eggs' => 50, 'damaged_eggs' => 5]);

    Livewire::actingAs($user)
        ->test('pages::gradings.index')
        ->set('gradingDate', '2026-09-21')
        ->set('eggGradeId', $grade->id)
        ->set('quantity', 46)
        ->call('saveGrading')
        ->assertHasErrors(['quantity']);

    expect(EggGrading::query()->count())->toBe(0);
});

test('new grading cannot use an inactive grade', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->inactive()->create();
    Production::factory()->create(['total_eggs' => 50, 'damaged_eggs' => 0]);

    Livewire::actingAs($user)
        ->test('pages::gradings.index')
        ->set('gradingDate', '2026-09-21')
        ->set('eggGradeId', $grade->id)
        ->set('quantity', 10)
        ->call('saveGrading')
        ->assertHasErrors(['eggGradeId']);

    expect(EggGrading::query()->count())->toBe(0);
});

test('grading records can be edited and stock is recalculated', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->create();
    Production::factory()->create(['total_eggs' => 100, 'damaged_eggs' => 0]);
    $grading = EggGrading::factory()->for($grade)->create(['quantity' => 60]);

    Livewire::actingAs($user)
        ->test('pages::gradings.index')
        ->call('editGrading', $grading->id)
        ->set('quantity', 80)
        ->call('saveGrading')
        ->assertHasNoErrors();

    expect($grading->refresh()->quantity)->toBe(80)
        ->and($grade->stockQuantity())->toBe(80)
        ->and(EggGrading::availableEggQuantity())->toBe(20);
});

test('grading can be moved to another grade when its stock is unused', function () {
    $user = User::factory()->create();
    $originalGrade = EggGrade::factory()->create();
    $newGrade = EggGrade::factory()->create();
    Production::factory()->create(['total_eggs' => 100, 'damaged_eggs' => 0]);
    $grading = EggGrading::factory()->for($originalGrade)->create(['quantity' => 80]);

    Livewire::actingAs($user)
        ->test('pages::gradings.index')
        ->call('editGrading', $grading->id)
        ->set('eggGradeId', $newGrade->id)
        ->call('saveGrading')
        ->assertHasNoErrors();

    expect($grading->refresh()->egg_grade_id)->toBe($newGrade->id)
        ->and($originalGrade->stockQuantity())->toBe(0)
        ->and($newGrade->stockQuantity())->toBe(80);
});

test('grading cannot be reduced below stock already sold', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->create();
    Production::factory()->create(['total_eggs' => 100, 'damaged_eggs' => 0]);
    $grading = EggGrading::factory()->for($grade)->create(['quantity' => 100]);
    Sale::factory()->for($grade)->create(['normalized_egg_quantity' => 80]);

    Livewire::actingAs($user)
        ->test('pages::gradings.index')
        ->call('editGrading', $grading->id)
        ->set('quantity', 70)
        ->call('saveGrading')
        ->assertHasErrors(['quantity']);

    expect($grading->refresh()->quantity)->toBe(100)
        ->and($grade->stockQuantity())->toBe(20);
});

test('unused grading records can be deleted and removed from stock', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->create();
    $grading = EggGrading::factory()->for($grade)->create(['quantity' => 80]);

    Livewire::actingAs($user)
        ->test('pages::gradings.index')
        ->call('confirmGradingDeletion', $grading->id)
        ->call('deleteGrading')
        ->assertHasNoErrors();

    expect($grading->fresh())->toBeNull()
        ->and($grade->stockQuantity())->toBe(0);
});

test('grading records cannot be deleted after their stock is used', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->create();
    $grading = EggGrading::factory()->for($grade)->create(['quantity' => 80]);
    Sale::factory()->for($grade)->create(['normalized_egg_quantity' => 20]);

    Livewire::actingAs($user)
        ->test('pages::gradings.index')
        ->call('confirmGradingDeletion', $grading->id)
        ->call('deleteGrading')
        ->assertHasErrors(['deleteGrading']);

    $this->assertModelExists($grading);
    expect($grade->stockQuantity())->toBe(60);
});

test('grading notes are escaped when rendered', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->create();
    EggGrading::factory()->for($grade)->create([
        'notes' => '<script>alert("grading")</script>',
    ]);

    $this->actingAs($user)
        ->get(route('egg-operations.index', ['tab' => 'grading']))
        ->assertOk()
        ->assertSee('&lt;script&gt;', escape: false)
        ->assertDontSee('<script>alert("grading")</script>', escape: false);
});

test('grading write actions require authentication', function () {
    $grade = EggGrade::factory()->create();
    Production::factory()->create(['total_eggs' => 50, 'damaged_eggs' => 0]);

    Livewire::test('pages::gradings.index')
        ->set('gradingDate', '2026-09-21')
        ->set('eggGradeId', $grade->id)
        ->set('quantity', 10)
        ->call('saveGrading')
        ->assertForbidden();

    expect(EggGrading::query()->count())->toBe(0);
});

test('form initializes with the first selectable grade preselected', function () {
    $user = User::factory()->create();
    $gradeAa = EggGrade::factory()->create(['name' => 'Grade AA', 'sort_order' => 1]);
    $gradeA = EggGrade::factory()->create(['name' => 'Grade A', 'sort_order' => 2]);
    Production::factory()->create(['total_eggs' => 100, 'damaged_eggs' => 0]);

    Livewire::actingAs($user)
        ->test('pages::gradings.index')
        ->assertSet('eggGradeId', $gradeAa->id)
        ->set('quantity', 25)
        ->call('saveGrading')
        ->assertHasNoErrors()
        ->assertSet('eggGradeId', $gradeAa->id);

    $this->assertDatabaseHas('egg_gradings', [
        'egg_grade_id' => $gradeAa->id,
        'quantity' => 25,
    ]);
});

test('cannot record multiple gradings for the same grade on the same date', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->create();
    Production::factory()->create(['total_eggs' => 100, 'damaged_eggs' => 0]);
    EggGrading::factory()->for($grade)->create([
        'grading_date' => '2026-09-21',
        'quantity' => 40,
    ]);

    Livewire::actingAs($user)
        ->test('pages::gradings.index')
        ->set('gradingDate', '2026-09-21')
        ->set('eggGradeId', $grade->id)
        ->set('quantity', 10)
        ->call('saveGrading')
        ->assertHasErrors(['eggGradeId']);

    expect(EggGrading::query()->count())->toBe(1);
});

test('editing an existing grading for the same grade and date does not trigger duplicate validation', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->create();
    Production::factory()->create(['total_eggs' => 100, 'damaged_eggs' => 0]);
    $grading = EggGrading::factory()->for($grade)->create([
        'grading_date' => '2026-09-21',
        'quantity' => 40,
    ]);

    Livewire::actingAs($user)
        ->test('pages::gradings.index')
        ->call('editGrading', $grading->id)
        ->set('quantity', 50)
        ->call('saveGrading')
        ->assertHasNoErrors();

    expect($grading->fresh()->quantity)->toBe(50);
});
