<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('guests cannot open user management', function () {
    $this->get(route('users.index'))->assertRedirectToRoute('login');
});

test('signed-in users can add another user', function () {
    $creator = User::factory()->create();

    Livewire::actingAs($creator)
        ->test('pages::settings.users')
        ->set('name', 'Farm Helper')
        ->set('email', 'helper@example.com')
        ->set('password', 'strong-password-123')
        ->set('password_confirmation', 'strong-password-123')
        ->call('addUser')
        ->assertHasNoErrors()
        ->assertSee('Farm Helper');

    $createdUser = User::query()->where('email', 'helper@example.com')->sole();

    expect($createdUser->name)->toBe('Farm Helper')
        ->and(Hash::check('strong-password-123', $createdUser->password))->toBeTrue();

    $this->post(route('logout'));
    $this->post(route('login'), [
        'email' => 'helper@example.com',
        'password' => 'strong-password-123',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($createdUser);
});

test('another signed-in user can also add users', function () {
    User::factory()->create();
    $secondUser = User::factory()->create();

    Livewire::actingAs($secondUser)
        ->test('pages::settings.users')
        ->set('name', 'Third User')
        ->set('email', 'third@example.com')
        ->set('password', 'strong-password-123')
        ->set('password_confirmation', 'strong-password-123')
        ->call('addUser')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('users', ['email' => 'third@example.com']);
});

test('user creation rejects an email already in use', function () {
    $creator = User::factory()->create();
    $existingUser = User::factory()->create();

    Livewire::actingAs($creator)
        ->test('pages::settings.users')
        ->set('name', 'Duplicate')
        ->set('email', strtoupper($existingUser->email))
        ->set('password', 'strong-password-123')
        ->set('password_confirmation', 'strong-password-123')
        ->call('addUser')
        ->assertHasErrors(['email']);

    expect(User::query()->count())->toBe(2);
});

test('user creation requires password confirmation', function () {
    $creator = User::factory()->create();

    Livewire::actingAs($creator)
        ->test('pages::settings.users')
        ->set('name', 'Farm Helper')
        ->set('email', 'helper@example.com')
        ->set('password', 'strong-password-123')
        ->set('password_confirmation', 'different-password')
        ->call('addUser')
        ->assertHasErrors(['password']);

    expect(User::query()->count())->toBe(1);
});

test('user creation actions require authentication', function () {
    Livewire::test('pages::settings.users')
        ->call('addUser')
        ->assertForbidden();

    expect(User::query()->count())->toBe(0);
});
