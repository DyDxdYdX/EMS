<?php

use App\Models\User;

test('guests are redirected from home to login', function () {
    $this->get(route('home'))->assertRedirectToRoute('login');
});

test('authenticated users are redirected from home to dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertRedirectToRoute('dashboard');
});
