<?php

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::get('/', function (): RedirectResponse {
    return auth()->check()
        ? to_route('dashboard')
        : to_route('login');
})->name('home');

Route::middleware('auth')->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('productions', 'pages::productions.index')->name('productions.index');
    Route::livewire('gradings', 'pages::gradings.index')->name('gradings.index');
    Route::livewire('sales', 'pages::sales.index')->name('sales.index');
    Route::livewire('customers', 'pages::customers.index')->name('customers.index');
    Route::livewire('egg-grades', 'pages::egg-grades.index')->name('egg-grades.index');
});

require __DIR__.'/settings.php';
