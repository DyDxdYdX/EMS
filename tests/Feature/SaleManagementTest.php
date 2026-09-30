<?php

use App\Models\Customer;
use App\Models\EggGrade;
use App\Models\EggGrading;
use App\Models\FarmSetting;
use App\Models\Sale;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected from sales management', function () {
    $this->get(route('sales.index'))->assertRedirectToRoute('login');
});

test('egg sales calculate totals and reduce stock', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create();
    $grade = EggGrade::factory()->create();
    EggGrading::factory()->for($grade)->create(['quantity' => 100]);

    Livewire::actingAs($user)
        ->test('pages::sales.index')
        ->set('saleDate', '2026-09-21')
        ->set('customerType', 'existing')
        ->call('selectCustomer', $customer->id)
        ->set('eggGradeId', $grade->id)
        ->set('unit', 'egg')
        ->set('quantity', 12)
        ->set('unitPrice', '0.45')
        ->set('notes', 'Counter sale')
        ->call('saveSale')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('sales', [
        'customer_id' => $customer->id,
        'egg_grade_id' => $grade->id,
        'unit' => 'egg',
        'quantity' => 12,
        'unit_price' => '0.45',
        'total_amount' => '5.40',
        'normalized_egg_quantity' => 12,
    ]);
    expect($grade->stockQuantity())->toBe(88);
});

test('tray sales use the configured eggs per tray', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->create();
    FarmSetting::factory()->create(['eggs_per_tray' => 24]);
    EggGrading::factory()->for($grade)->create(['quantity' => 100]);

    Livewire::actingAs($user)
        ->test('pages::sales.index')
        ->set('saleDate', '2026-09-21')
        ->set('eggGradeId', $grade->id)
        ->set('unit', 'tray')
        ->set('quantity', 3)
        ->set('unitPrice', '12.50')
        ->call('saveSale')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('sales', [
        'unit' => 'tray',
        'quantity' => 3,
        'unit_price' => '12.50',
        'total_amount' => '37.50',
        'normalized_egg_quantity' => 72,
    ]);
    expect($grade->stockQuantity())->toBe(28);
});

test('sales can be recorded for walk-in customers', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create();
    $grade = EggGrade::factory()->create();
    EggGrading::factory()->for($grade)->create(['quantity' => 200]);

    Livewire::actingAs($user)
        ->test('pages::sales.index')
        ->set('customerId', $customer->id)
        ->set('customerId', '')
        ->set('saleDate', '2026-09-21')
        ->set('eggGradeId', $grade->id)
        ->set('quantity', 5)
        ->set('unitPrice', '0.50')
        ->call('saveSale')
        ->assertHasNoErrors();

    expect(Sale::query()->sole()->customer_id)->toBeNull();
});

test('sales form defaults unit to tray and preselects first egg grade', function () {
    $user = User::factory()->create();
    $gradeAa = EggGrade::factory()->create(['name' => 'Grade AA', 'sort_order' => 1]);
    $gradeA = EggGrade::factory()->create(['name' => 'Grade A', 'sort_order' => 2]);

    Livewire::actingAs($user)
        ->test('pages::sales.index')
        ->assertSet('unit', 'tray')
        ->assertSet('eggGradeId', $gradeAa->id);
});

test('customer picker shows a bounded list and searches names and phone numbers', function () {
    Customer::factory()->count(25)->sequence(fn ($sequence): array => [
        'name' => sprintf('Customer %02d', $sequence->index + 1),
        'phone' => sprintf('555-%04d', $sequence->index + 1),
    ])->create();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::sales.index')
        ->assertDontSee('role="combobox"', escape: false)
        ->set('customerType', 'existing')
        ->assertSee('role="combobox"', escape: false)
        ->assertSee('role="listbox"', escape: false)
        ->assertSee('Customer 20')
        ->assertDontSee('Customer 21')
        ->set('customerSearch', 'Customer 25')
        ->assertSee('Customer 25')
        ->assertDontSee('Customer 01')
        ->set('customerSearch', '555-0024')
        ->assertSee('Customer 24')
        ->assertDontSee('Customer 25')
        ->set('customerSearch', 'no matching customer')
        ->assertSee('No matching customers');
});

test('customer picker selects a customer and clears the selection when searching again', function () {
    Customer::factory()->count(20)->sequence(fn ($sequence): array => [
        'name' => sprintf('Customer %02d', $sequence->index + 1),
    ])->create();
    $selectedCustomer = Customer::factory()->create(['name' => 'Customer 25']);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::sales.index')
        ->set('customerType', 'existing')
        ->call('selectCustomer', $selectedCustomer->id)
        ->assertSet('customerSearch', 'Customer 25')
        ->assertSet('customerId', $selectedCustomer->id)
        ->set('customerSearch', 'no match')
        ->assertSet('customerId', null)
        ->assertDontSee('Customer 25')
        ->call('cancelEditing')
        ->assertSet('customerType', 'walk_in')
        ->assertSet('customerSearch', '')
        ->assertSet('customerId', null);
});

test('switching to walk-in removes a previously selected customer', function () {
    $customer = Customer::factory()->create(['name' => 'Local buyer']);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::sales.index')
        ->set('customerType', 'existing')
        ->call('selectCustomer', $customer->id)
        ->set('customerType', 'walk_in')
        ->assertSet('customerId', null)
        ->assertSet('customerSearch', '')
        ->assertDontSee('role="combobox"', escape: false)
        ->assertSee('No customer details needed for a walk-in sale.');
});

test('existing-customer sales require a customer selection', function () {
    $grade = EggGrade::factory()->create();
    EggGrading::factory()->for($grade)->create(['quantity' => 20]);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::sales.index')
        ->set('customerType', 'existing')
        ->set('saleDate', '2026-09-21')
        ->set('eggGradeId', $grade->id)
        ->set('unit', 'egg')
        ->set('quantity', 1)
        ->set('unitPrice', '0.50')
        ->call('saveSale')
        ->assertHasErrors(['customerId']);

    expect(Sale::query()->count())->toBe(0);
});

test('sales reject an unknown customer type', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::sales.index')
        ->set('customerType', 'unknown')
        ->call('saveSale')
        ->assertHasErrors(['customerType']);

    expect(Sale::query()->count())->toBe(0);
});

test('editing a customer sale opens the customer picker', function () {
    $customer = Customer::factory()->create(['name' => 'Local buyer']);
    $sale = Sale::factory()->create(['customer_id' => $customer->id]);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::sales.index')
        ->call('editSale', $sale->id)
        ->assertSet('customerType', 'existing')
        ->assertSet('customerId', $customer->id)
        ->assertSet('customerSearch', 'Local buyer')
        ->assertSee('role="combobox"', escape: false);
});

test('customer selection requires authentication', function () {
    $customer = Customer::factory()->create();

    Livewire::test('pages::sales.index')
        ->call('selectCustomer', $customer->id)
        ->assertForbidden();
});

test('sales cannot exceed available grade stock', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->create();
    EggGrading::factory()->for($grade)->create(['quantity' => 10]);

    Livewire::actingAs($user)
        ->test('pages::sales.index')
        ->set('saleDate', '2026-09-21')
        ->set('eggGradeId', $grade->id)
        ->set('quantity', 11)
        ->set('unitPrice', '0.50')
        ->call('saveSale')
        ->assertHasErrors(['quantity']);

    expect(Sale::query()->count())->toBe(0)
        ->and($grade->stockQuantity())->toBe(10);
});

test('new sales cannot use inactive grades', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->inactive()->create();
    EggGrading::factory()->for($grade)->create(['quantity' => 10]);

    Livewire::actingAs($user)
        ->test('pages::sales.index')
        ->set('saleDate', '2026-09-21')
        ->set('eggGradeId', $grade->id)
        ->set('quantity', 5)
        ->set('unitPrice', '0.50')
        ->call('saveSale')
        ->assertHasErrors(['eggGradeId']);

    expect(Sale::query()->count())->toBe(0);
});

test('sales can be edited using their original stock allowance', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->create();
    EggGrading::factory()->for($grade)->create(['quantity' => 100]);
    $sale = Sale::factory()->for($grade)->create([
        'unit' => 'egg',
        'quantity' => 40,
        'unit_price' => '0.50',
        'total_amount' => '20.00',
        'normalized_egg_quantity' => 40,
    ]);

    Livewire::actingAs($user)
        ->test('pages::sales.index')
        ->call('editSale', $sale->id)
        ->set('quantity', 80)
        ->set('unitPrice', '0.55')
        ->call('saveSale')
        ->assertHasNoErrors();

    expect($sale->refresh())
        ->quantity->toBe(80)
        ->total_amount->toBe('44.00')
        ->normalized_egg_quantity->toBe(80);
    expect($grade->stockQuantity())->toBe(20);
});

test('moving a sale restores its original grade stock', function () {
    $user = User::factory()->create();
    $originalGrade = EggGrade::factory()->create();
    $newGrade = EggGrade::factory()->create();
    EggGrading::factory()->for($originalGrade)->create(['quantity' => 100]);
    EggGrading::factory()->for($newGrade)->create(['quantity' => 50]);
    $sale = Sale::factory()->for($originalGrade)->create([
        'quantity' => 30,
        'normalized_egg_quantity' => 30,
    ]);

    Livewire::actingAs($user)
        ->test('pages::sales.index')
        ->call('editSale', $sale->id)
        ->set('eggGradeId', $newGrade->id)
        ->set('quantity', 20)
        ->call('saveSale')
        ->assertHasNoErrors();

    expect($sale->refresh()->egg_grade_id)->toBe($newGrade->id)
        ->and($originalGrade->stockQuantity())->toBe(100)
        ->and($newGrade->stockQuantity())->toBe(30);
});

test('deleting a sale restores stock', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->create();
    EggGrading::factory()->for($grade)->create(['quantity' => 100]);
    $sale = Sale::factory()->for($grade)->create([
        'quantity' => 30,
        'normalized_egg_quantity' => 30,
    ]);

    Livewire::actingAs($user)
        ->test('pages::sales.index')
        ->call('confirmSaleDeletion', $sale->id)
        ->call('deleteSale');

    expect($sale->fresh())->toBeNull()
        ->and($grade->stockQuantity())->toBe(100);
});

test('unit prices accept at most two decimal places', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->create();
    EggGrading::factory()->for($grade)->create(['quantity' => 10]);

    Livewire::actingAs($user)
        ->test('pages::sales.index')
        ->set('saleDate', '2026-09-21')
        ->set('eggGradeId', $grade->id)
        ->set('quantity', 1)
        ->set('unitPrice', '0.555')
        ->call('saveSale')
        ->assertHasErrors(['unitPrice' => ['decimal']]);

    expect(Sale::query()->count())->toBe(0);
});

test('sale notes are escaped when rendered', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->create();
    Sale::factory()->for($grade)->create([
        'notes' => '<script>alert("sale")</script>',
    ]);

    $this->actingAs($user)
        ->get(route('sales.index'))
        ->assertOk()
        ->assertSee('&lt;script&gt;', escape: false)
        ->assertDontSee('<script>alert("sale")</script>', escape: false);
});

test('sale write actions require authentication', function () {
    $grade = EggGrade::factory()->create();
    EggGrading::factory()->for($grade)->create(['quantity' => 10]);

    Livewire::test('pages::sales.index')
        ->set('saleDate', '2026-09-21')
        ->set('eggGradeId', $grade->id)
        ->set('quantity', 5)
        ->set('unitPrice', '0.50')
        ->call('saveSale')
        ->assertForbidden();

    expect(Sale::query()->count())->toBe(0);
});
