<?php

use App\Models\Production;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected from production management', function () {
    $this->get(route('productions.index'))->assertRedirectToRoute('login');
});

test('authenticated users can view production records newest first', function () {
    $user = User::factory()->create();
    Production::factory()->create([
        'production_date' => '2026-09-18',
        'total_eggs' => 80,
        'damaged_eggs' => 5,
    ]);
    Production::factory()->create([
        'production_date' => '2026-09-19',
        'total_eggs' => 120,
        'damaged_eggs' => 8,
    ]);

    $this->actingAs($user)
        ->get(route('productions.index'))
        ->assertOk()
        ->assertSeeInOrder(['19 Sep 2026', '18 Sep 2026']);
});

test('production records can be created', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::productions.index')
        ->set('productionDate', '2026-09-20')
        ->set('totalEggs', 150)
        ->set('damagedEggs', 12)
        ->set('notes', 'Morning collection')
        ->call('saveProduction')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('productions', [
        'production_date' => '2026-09-20 00:00:00',
        'total_eggs' => 150,
        'damaged_eggs' => 12,
        'notes' => 'Morning collection',
    ]);
});

test('production dates must be unique', function () {
    $user = User::factory()->create();
    Production::factory()->create(['production_date' => '2026-09-20']);

    Livewire::actingAs($user)
        ->test('pages::productions.index')
        ->set('productionDate', '2026-09-20')
        ->set('totalEggs', 100)
        ->set('damagedEggs', 5)
        ->call('saveProduction')
        ->assertHasErrors(['productionDate']);

    expect(Production::query()->count())->toBe(1);
});

test('damaged eggs cannot exceed collected eggs', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::productions.index')
        ->set('productionDate', '2026-09-20')
        ->set('totalEggs', 10)
        ->set('damagedEggs', 11)
        ->call('saveProduction')
        ->assertHasErrors(['damagedEggs' => ['lte']]);

    expect(Production::query()->count())->toBe(0);
});

test('production records can be edited without changing their date', function () {
    $user = User::factory()->create();
    $production = Production::factory()->create([
        'production_date' => '2026-09-20',
        'total_eggs' => 100,
        'damaged_eggs' => 4,
    ]);

    Livewire::actingAs($user)
        ->test('pages::productions.index')
        ->call('editProduction', $production->id)
        ->set('totalEggs', 125)
        ->set('damagedEggs', 6)
        ->call('saveProduction')
        ->assertHasNoErrors();

    expect($production->refresh())
        ->total_eggs->toBe(125)
        ->damaged_eggs->toBe(6);
});

test('production records can be deleted', function () {
    $user = User::factory()->create();
    $production = Production::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::productions.index')
        ->call('confirmProductionDeletion', $production->id)
        ->call('deleteProduction');

    expect($production->fresh())->toBeNull();
});

test('production write actions require authentication', function () {
    Livewire::test('pages::productions.index')
        ->set('productionDate', '2026-09-20')
        ->set('totalEggs', 100)
        ->set('damagedEggs', 5)
        ->call('saveProduction')
        ->assertForbidden();

    expect(Production::query()->count())->toBe(0);
});
