<?php

use App\Models\FarmSetting;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected from farm settings', function () {
    $this->get(route('farm-settings.edit'))->assertRedirectToRoute('login');
});

test('farm settings page displays the current tray size', function () {
    $user = User::factory()->create();
    FarmSetting::factory()->create(['id' => 1, 'eggs_per_tray' => 24]);

    $this->actingAs($user)
        ->get(route('farm-settings.edit'))
        ->assertOk()
        ->assertSee('Eggs per tray')
        ->assertSee('24');
});

test('tray size can be updated', function () {
    $user = User::factory()->create();
    FarmSetting::factory()->create(['id' => 1, 'eggs_per_tray' => 30]);

    Livewire::actingAs($user)
        ->test('pages::settings.farm')
        ->set('eggsPerTray', 36)
        ->call('updateFarmSettings')
        ->assertHasNoErrors();

    expect(FarmSetting::query()->sole()->eggs_per_tray)->toBe(36);
});

test('tray size must be a positive integer', function () {
    $user = User::factory()->create();
    FarmSetting::factory()->create(['id' => 1, 'eggs_per_tray' => 30]);

    Livewire::actingAs($user)
        ->test('pages::settings.farm')
        ->set('eggsPerTray', 0)
        ->call('updateFarmSettings')
        ->assertHasErrors(['eggsPerTray']);

    expect(FarmSetting::query()->sole()->eggs_per_tray)->toBe(30);
});
