<?php

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected from expense category management', function () {
    $this->get(route('expense-categories.index'))->assertRedirectToRoute('login');
});

test('expense categories can be created', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::expense-categories.index')
        ->set('name', 'Feed')
        ->set('sortOrder', 2)
        ->set('isActive', true)
        ->call('saveCategory')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('expense_categories', [
        'name' => 'Feed',
        'sort_order' => 2,
        'is_active' => true,
    ]);
});

test('expense category names must be unique', function () {
    $user = User::factory()->create();
    ExpenseCategory::factory()->create(['name' => 'Utilities']);

    Livewire::actingAs($user)
        ->test('pages::expense-categories.index')
        ->set('name', 'Utilities')
        ->call('saveCategory')
        ->assertHasErrors(['name']);
});

test('expense categories can be edited reordered and toggled', function () {
    $user = User::factory()->create();
    $category = ExpenseCategory::factory()->create([
        'name' => 'Old category',
        'sort_order' => 9,
        'is_active' => true,
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::expense-categories.index')
        ->call('editCategory', $category->id)
        ->set('name', 'Farm supplies')
        ->set('sortOrder', 3)
        ->call('saveCategory')
        ->assertHasNoErrors()
        ->call('toggleCategory', $category->id);

    expect($category->refresh())
        ->name->toBe('Farm supplies')
        ->sort_order->toBe(3)
        ->is_active->toBeFalse();

    $component->call('toggleCategory', $category->id);

    expect($category->refresh()->is_active)->toBeTrue();
});

test('unused expense categories can be deleted', function () {
    $user = User::factory()->create();
    $category = ExpenseCategory::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::expense-categories.index')
        ->call('confirmCategoryDeletion', $category->id)
        ->call('deleteCategory');

    expect($category->fresh())->toBeNull();
});

test('expense categories with history are deactivated instead of deleted', function () {
    $user = User::factory()->create();
    $category = ExpenseCategory::factory()->create(['is_active' => true]);
    Expense::factory()->for($category, 'expenseCategory')->create();

    Livewire::actingAs($user)
        ->test('pages::expense-categories.index')
        ->call('confirmCategoryDeletion', $category->id)
        ->call('deleteCategory');

    expect($category->refresh()->is_active)->toBeFalse()
        ->and($category->expenses()->count())->toBe(1);
});

test('expense category write actions require authentication', function () {
    Livewire::test('pages::expense-categories.index')
        ->set('name', 'Unauthorized category')
        ->call('saveCategory')
        ->assertForbidden();

    expect(ExpenseCategory::query()->count())->toBe(0);
});
