<?php

use App\Models\EggGrade;
use App\Models\EggGrading;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected from egg grade management', function () {
    $this->get(route('egg-grades.index'))->assertRedirectToRoute('login');
});

test('authenticated users can view egg grades', function () {
    $user = User::factory()->create();
    EggGrade::factory()->create(['name' => 'Grade A']);

    $this->actingAs($user)
        ->get(route('egg-grades.index'))
        ->assertOk()
        ->assertSee('Grade A');
});

test('egg grades can be created', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::egg-grades.index')
        ->set('name', 'Grade Premium')
        ->set('weightRange', '75 g and above')
        ->set('sortOrder', 8)
        ->set('isActive', true)
        ->call('saveGrade')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('egg_grades', [
        'name' => 'Grade Premium',
        'weight_range' => '75 g and above',
        'sort_order' => 8,
        'is_active' => true,
    ]);
});

test('egg grade names must be unique', function () {
    $user = User::factory()->create();
    EggGrade::factory()->create(['name' => 'Grade A']);

    Livewire::actingAs($user)
        ->test('pages::egg-grades.index')
        ->set('name', 'Grade A')
        ->set('sortOrder', 10)
        ->call('saveGrade')
        ->assertHasErrors(['name']);
});

test('egg grades can be edited and reordered', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->create([
        'name' => 'Grade Old',
        'sort_order' => 5,
    ]);

    Livewire::actingAs($user)
        ->test('pages::egg-grades.index')
        ->call('editGrade', $grade->id)
        ->set('name', 'Grade Updated')
        ->set('weightRange', '60–64.9 g')
        ->set('sortOrder', 2)
        ->call('saveGrade')
        ->assertHasNoErrors();

    expect($grade->refresh())
        ->name->toBe('Grade Updated')
        ->weight_range->toBe('60–64.9 g')
        ->sort_order->toBe(2);
});

test('egg grades can be deactivated and reactivated', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->create(['is_active' => true]);

    $component = Livewire::actingAs($user)
        ->test('pages::egg-grades.index')
        ->call('toggleGrade', $grade->id);

    expect($grade->refresh()->is_active)->toBeFalse();

    $component->call('toggleGrade', $grade->id);

    expect($grade->refresh()->is_active)->toBeTrue();
});

test('unused egg grades can be deleted', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::egg-grades.index')
        ->call('confirmGradeDeletion', $grade->id)
        ->call('deleteGrade');

    expect($grade->fresh())->toBeNull();
});

test('egg grades with history are deactivated instead of deleted', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->create(['is_active' => true]);
    EggGrading::factory()->for($grade)->create();

    Livewire::actingAs($user)
        ->test('pages::egg-grades.index')
        ->call('confirmGradeDeletion', $grade->id)
        ->call('deleteGrade');

    expect($grade->refresh()->is_active)->toBeFalse();
});
