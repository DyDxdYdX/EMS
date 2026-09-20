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
    Route::livewire('egg-grades', 'pages::egg-grades.index')->name('egg-grades.index');
});

require __DIR__.'/settings.php';
