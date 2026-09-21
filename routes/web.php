<?php

use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\ReportPdfController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::get('/', function (): RedirectResponse {
    return auth()->check()
        ? to_route('dashboard')
        : to_route('login');
})->name('home');

Route::middleware('auth')->group(function () {
    Route::livewire('dashboard', 'pages::dashboard.index')->name('dashboard');
    Route::livewire('reports', 'pages::reports.index')->name('reports.index');
    Route::get('reports/exports/{export}', ReportExportController::class)->name('reports.exports');
    Route::get('reports/pdf', ReportPdfController::class)->name('reports.pdf');
    Route::livewire('productions', 'pages::productions.index')->name('productions.index');
    Route::livewire('gradings', 'pages::gradings.index')->name('gradings.index');
    Route::livewire('stock-adjustments', 'pages::stock-adjustments.index')->name('stock-adjustments.index');
    Route::livewire('sales', 'pages::sales.index')->name('sales.index');
    Route::livewire('expenses', 'pages::expenses.index')->name('expenses.index');
    Route::livewire('customers', 'pages::customers.index')->name('customers.index');
    Route::livewire('egg-grades', 'pages::egg-grades.index')->name('egg-grades.index');
    Route::livewire('expense-categories', 'pages::expense-categories.index')->name('expense-categories.index');
});

require __DIR__.'/settings.php';
