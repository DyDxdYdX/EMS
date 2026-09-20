<?php

test('registration screen is unavailable', function () {
    $this->get('/register')->assertNotFound();
});

test('public registration is unavailable', function () {
    $this->post('/register', [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
});
