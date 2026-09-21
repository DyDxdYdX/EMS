<?php

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected from expense management', function () {
    $this->get(route('expenses.index'))->assertRedirectToRoute('login');
});

test('expenses can be created with an exact two decimal amount', function () {
    $user = User::factory()->create();
    $category = ExpenseCategory::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('expenseDate', '2026-09-21')
        ->set('title', 'Layer feed')
        ->set('expenseCategoryId', $category->id)
        ->set('amount', '1234.50')
        ->set('description', 'Monthly feed delivery')
        ->call('saveExpense')
        ->assertHasNoErrors();

    $expense = Expense::query()->sole();

    expect($expense)
        ->expense_date->toDateString()->toBe('2026-09-21')
        ->title->toBe('Layer feed')
        ->expense_category_id->toBe($category->id)
        ->amount->toBe('1234.50')
        ->description->toBe('Monthly feed delivery');
});

test('expense amounts must be positive and use at most two decimal places', function (string $amount) {
    $user = User::factory()->create();
    $category = ExpenseCategory::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('expenseDate', '2026-09-21')
        ->set('title', 'Invalid expense')
        ->set('expenseCategoryId', $category->id)
        ->set('amount', $amount)
        ->call('saveExpense')
        ->assertHasErrors(['amount']);

    expect(Expense::query()->count())->toBe(0);
})->with(['zero' => '0.00', 'negative' => '-1.00', 'too precise' => '12.345']);

test('new expenses cannot use an inactive category', function () {
    $user = User::factory()->create();
    $category = ExpenseCategory::factory()->inactive()->create();

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->set('expenseDate', '2026-09-21')
        ->set('title', 'Blocked expense')
        ->set('expenseCategoryId', $category->id)
        ->set('amount', '10.00')
        ->call('saveExpense')
        ->assertHasErrors(['expenseCategoryId']);

    expect(Expense::query()->count())->toBe(0);
});

test('expenses can be edited while retaining their inactive category', function () {
    $user = User::factory()->create();
    $category = ExpenseCategory::factory()->inactive()->create();
    $expense = Expense::factory()->for($category, 'expenseCategory')->create([
        'title' => 'Old expense',
        'amount' => '20.00',
    ]);

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->call('editExpense', $expense->id)
        ->assertSet('expenseCategoryId', $category->id)
        ->set('title', 'Corrected expense')
        ->set('amount', '25.75')
        ->call('saveExpense')
        ->assertHasNoErrors();

    expect($expense->refresh())
        ->title->toBe('Corrected expense')
        ->expense_category_id->toBe($category->id)
        ->amount->toBe('25.75');
});

test('expenses can be deleted', function () {
    $user = User::factory()->create();
    $expense = Expense::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::expenses.index')
        ->call('confirmExpenseDeletion', $expense->id)
        ->call('deleteExpense');

    expect($expense->fresh())->toBeNull();
});

test('expense details are escaped when rendered', function () {
    $user = User::factory()->create();
    Expense::factory()->create([
        'title' => '<script>alert("expense")</script>',
        'description' => '<img src=x onerror=alert(1)>',
    ]);

    $this->actingAs($user)
        ->get(route('expenses.index'))
        ->assertOk()
        ->assertSee('&lt;script&gt;', escape: false)
        ->assertDontSee('<script>alert("expense")</script>', escape: false)
        ->assertDontSee('<img src=x onerror=alert(1)>', escape: false);
});

test('expense write actions require authentication', function () {
    $category = ExpenseCategory::factory()->create();

    Livewire::test('pages::expenses.index')
        ->set('expenseDate', '2026-09-21')
        ->set('title', 'Unauthorized expense')
        ->set('expenseCategoryId', $category->id)
        ->set('amount', '10.00')
        ->call('saveExpense')
        ->assertForbidden();

    expect(Expense::query()->count())->toBe(0);
});
