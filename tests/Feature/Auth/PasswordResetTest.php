<?php

test('password reset request screen is unavailable', function () {
    $this->get('/forgot-password')->assertNotFound();
});

test('password reset links cannot be requested', function () {
    $this->post('/forgot-password', [
        'email' => 'owner@example.com',
    ])->assertNotFound();
});

test('password reset screen is unavailable', function () {
    $this->get('/reset-password/example-token')->assertNotFound();
});

test('password reset submissions are unavailable', function () {
    $this->post('/reset-password', [
        'token' => 'example-token',
        'email' => 'owner@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertNotFound();
});
