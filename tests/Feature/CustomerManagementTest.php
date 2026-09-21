<?php

use App\Models\Customer;
use App\Models\EggGrade;
use App\Models\Sale;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected from customer management', function () {
    $this->get(route('customers.index'))->assertRedirectToRoute('login');
});

test('customers can be created', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::customers.index')
        ->set('name', 'Kuching Market')
        ->set('phone', '012-3456789')
        ->set('notes', 'Weekly delivery')
        ->call('saveCustomer')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('customers', [
        'name' => 'Kuching Market',
        'phone' => '012-3456789',
        'notes' => 'Weekly delivery',
    ]);
});

test('customer names are required', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::customers.index')
        ->set('name', '')
        ->call('saveCustomer')
        ->assertHasErrors(['name' => ['required']]);

    expect(Customer::query()->count())->toBe(0);
});

test('customers can be edited', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['name' => 'Old Name']);

    Livewire::actingAs($user)
        ->test('pages::customers.index')
        ->call('editCustomer', $customer->id)
        ->set('name', 'New Name')
        ->set('phone', '019-1002003')
        ->call('saveCustomer')
        ->assertHasNoErrors();

    expect($customer->refresh())
        ->name->toBe('New Name')
        ->phone->toBe('019-1002003');
});

test('deleting a customer preserves their sales as walk-in sales', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create();
    $grade = EggGrade::factory()->create();
    $sale = Sale::factory()->for($customer)->for($grade)->create();

    Livewire::actingAs($user)
        ->test('pages::customers.index')
        ->call('confirmCustomerDeletion', $customer->id)
        ->call('deleteCustomer');

    expect($customer->fresh())->toBeNull()
        ->and($sale->refresh()->customer_id)->toBeNull();
});

test('customer details are escaped when rendered', function () {
    $user = User::factory()->create();
    Customer::factory()->create([
        'name' => '<script>alert("customer")</script>',
        'notes' => '<img src=x onerror=alert(1)>',
    ]);

    $this->actingAs($user)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertSee('&lt;script&gt;', escape: false)
        ->assertDontSee('<script>alert("customer")</script>', escape: false)
        ->assertDontSee('<img src=x onerror=alert(1)>', escape: false);
});

test('customer write actions require authentication', function () {
    Livewire::test('pages::customers.index')
        ->set('name', 'Unauthorized Customer')
        ->call('saveCustomer')
        ->assertForbidden();

    expect(Customer::query()->count())->toBe(0);
});
