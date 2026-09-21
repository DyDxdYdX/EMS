<?php

use App\Enums\ReportExport;
use App\Models\Customer;
use App\Models\EggGrade;
use App\Models\EggGrading;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Production;
use App\Models\Sale;
use App\Models\StockAdjustment;
use App\Models\User;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;

test('guests are redirected from the reports page', function () {
    $this->get(route('reports.index'))->assertRedirectToRoute('login');
});

test('guests are redirected from report csv exports', function () {
    $this->get(route('reports.exports', [
        'export' => ReportExport::Sales,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
    ]))->assertRedirectToRoute('login');
});

test('authenticated users can visit the reports page', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertSee('Reports')
        ->assertSee('Profit and loss')
        ->assertSee('Sales by egg grade')
        ->assertSee('Expenses by category')
        ->assertSee('Production totals')
        ->assertSee('Current stock by egg grade')
        ->assertSee('Download PDF')
        ->assertSee('Export sales');
});

test('reports calculate exact profit and loss without floating point arithmetic', function () {
    $this->travelTo('2026-09-21 12:00:00');

    $user = User::factory()->create();
    $grade = EggGrade::factory()->create(['name' => 'Premium']);
    $category = ExpenseCategory::factory()->create(['name' => 'Feed']);

    Sale::factory()->for($grade, 'eggGrade')->create([
        'sale_date' => '2026-09-05',
        'total_amount' => '10.10',
        'unit_price' => '10.10',
        'quantity' => 1,
        'normalized_egg_quantity' => 10,
    ]);
    Sale::factory()->for($grade, 'eggGrade')->create([
        'sale_date' => '2026-09-20',
        'total_amount' => '20.20',
        'unit_price' => '20.20',
        'quantity' => 1,
        'normalized_egg_quantity' => 20,
    ]);
    Expense::factory()->for($category, 'expenseCategory')->create([
        'expense_date' => '2026-09-10',
        'amount' => '1.13',
    ]);
    Expense::factory()->for($category, 'expenseCategory')->create([
        'expense_date' => '2026-09-18',
        'amount' => '2.17',
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::reports.index');

    expect($component->instance()->summary)
        ->revenue->toBe('30.30')
        ->expenses->toBe('3.30')
        ->net_profit->toBe('27.00')
        ->net_profit_cents->toBe(2700)
        ->eggs_sold->toBe(30);
});

test('reports include period sales by grade, expenses by category, and production totals', function () {
    $this->travelTo('2026-09-21 12:00:00');

    $user = User::factory()->create();
    $premiumGrade = EggGrade::factory()->create(['name' => 'Premium']);
    $standardGrade = EggGrade::factory()->create(['name' => 'Standard']);
    $feedCategory = ExpenseCategory::factory()->create(['name' => 'Feed']);
    $utilitiesCategory = ExpenseCategory::factory()->create(['name' => 'Utilities']);

    Sale::factory()->for($premiumGrade, 'eggGrade')->create([
        'sale_date' => '2026-09-05',
        'total_amount' => '100.10',
        'normalized_egg_quantity' => 100,
    ]);
    Sale::factory()->for($standardGrade, 'eggGrade')->create([
        'sale_date' => '2026-09-20',
        'total_amount' => '50.25',
        'normalized_egg_quantity' => 60,
    ]);
    Expense::factory()->for($feedCategory, 'expenseCategory')->create([
        'expense_date' => '2026-09-10',
        'amount' => '30.05',
    ]);
    Expense::factory()->for($utilitiesCategory, 'expenseCategory')->create([
        'expense_date' => '2026-09-18',
        'amount' => '20.10',
    ]);
    Production::factory()->create([
        'production_date' => '2026-09-01',
        'total_eggs' => 120,
        'damaged_eggs' => 8,
    ]);
    Production::factory()->create([
        'production_date' => '2026-09-15',
        'total_eggs' => 80,
        'damaged_eggs' => 5,
    ]);

    $instance = Livewire::actingAs($user)
        ->test('pages::reports.index')
        ->instance();

    expect($instance->salesByGrade->pluck('revenue', 'name')->all())->toBe([
        'Premium' => '100.10',
        'Standard' => '50.25',
    ]);
    expect($instance->expensesByCategory->pluck('expenses', 'name')->all())->toBe([
        'Feed' => '30.05',
        'Utilities' => '20.10',
    ]);
    expect($instance->productionTotals)->toBe([
        'record_count' => 2,
        'total_eggs' => 200,
        'damaged_eggs' => 13,
        'gradable_eggs' => 187,
    ]);
});

test('report date filters include the start and end dates and exclude records outside the range', function () {
    $this->travelTo('2026-09-21 12:00:00');

    $user = User::factory()->create();
    $grade = EggGrade::factory()->create();
    $category = ExpenseCategory::factory()->create();

    Sale::factory()->for($grade, 'eggGrade')->create([
        'sale_date' => '2026-08-31',
        'total_amount' => '9.00',
        'normalized_egg_quantity' => 9,
    ]);
    Sale::factory()->for($grade, 'eggGrade')->create([
        'sale_date' => '2026-09-01',
        'total_amount' => '10.00',
        'normalized_egg_quantity' => 10,
    ]);
    Sale::factory()->for($grade, 'eggGrade')->create([
        'sale_date' => '2026-09-30',
        'total_amount' => '11.00',
        'normalized_egg_quantity' => 11,
    ]);
    Sale::factory()->for($grade, 'eggGrade')->create([
        'sale_date' => '2026-10-01',
        'total_amount' => '12.00',
        'normalized_egg_quantity' => 12,
    ]);
    Expense::factory()->for($category, 'expenseCategory')->create([
        'expense_date' => '2026-08-31',
        'amount' => '1.00',
    ]);
    Expense::factory()->for($category, 'expenseCategory')->create([
        'expense_date' => '2026-09-01',
        'amount' => '2.00',
    ]);
    Expense::factory()->for($category, 'expenseCategory')->create([
        'expense_date' => '2026-09-30',
        'amount' => '3.00',
    ]);
    Expense::factory()->for($category, 'expenseCategory')->create([
        'expense_date' => '2026-10-01',
        'amount' => '4.00',
    ]);
    Production::factory()->create([
        'production_date' => '2026-08-31',
        'total_eggs' => 50,
        'damaged_eggs' => 5,
    ]);
    Production::factory()->create([
        'production_date' => '2026-09-01',
        'total_eggs' => 70,
        'damaged_eggs' => 2,
    ]);
    Production::factory()->create([
        'production_date' => '2026-09-30',
        'total_eggs' => 30,
        'damaged_eggs' => 1,
    ]);
    Production::factory()->create([
        'production_date' => '2026-10-01',
        'total_eggs' => 90,
        'damaged_eggs' => 9,
    ]);

    $instance = Livewire::actingAs($user)
        ->test('pages::reports.index')
        ->set('startDate', '2026-09-01')
        ->set('endDate', '2026-09-30')
        ->call('applyPeriod')
        ->assertHasNoErrors()
        ->assertSet('appliedStartDate', '2026-09-01')
        ->assertSet('appliedEndDate', '2026-09-30')
        ->instance();

    expect($instance->summary)
        ->revenue->toBe('21.00')
        ->expenses->toBe('5.00')
        ->net_profit->toBe('16.00')
        ->eggs_sold->toBe(21);
    expect($instance->productionTotals)->toBe([
        'record_count' => 2,
        'total_eggs' => 100,
        'damaged_eggs' => 3,
        'gradable_eggs' => 97,
    ]);
});

test('current stock includes activity outside the selected reporting period', function () {
    $this->travelTo('2026-09-21 12:00:00');

    $user = User::factory()->create();
    $grade = EggGrade::factory()->create(['name' => 'Large', 'sort_order' => 1]);

    EggGrading::factory()->for($grade)->create([
        'grading_date' => '2026-08-01',
        'quantity' => 100,
    ]);
    Sale::factory()->for($grade, 'eggGrade')->create([
        'sale_date' => '2026-08-15',
        'normalized_egg_quantity' => 20,
        'quantity' => 20,
        'total_amount' => '8.00',
    ]);
    StockAdjustment::factory()->for($grade, 'eggGrade')->create([
        'adjustment_date' => '2026-08-20',
        'type' => 'add',
        'quantity' => 5,
    ]);
    StockAdjustment::factory()->for($grade, 'eggGrade')->remove()->create([
        'adjustment_date' => '2026-08-21',
        'quantity' => 3,
    ]);

    $instance = Livewire::actingAs($user)
        ->test('pages::reports.index')
        ->instance();

    expect($instance->currentStock->firstWhere('id', $grade->id)['quantity'])->toBe(82)
        ->and($grade->stockQuantity())->toBe(82);
});

test('reports reject a reporting period whose end precedes its start', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::reports.index')
        ->set('startDate', '2026-09-30')
        ->set('endDate', '2026-09-01')
        ->call('applyPeriod')
        ->assertHasErrors(['startDate', 'endDate']);
});

test('report filter actions require authentication', function () {
    Livewire::test('pages::reports.index')
        ->set('startDate', '2026-09-01')
        ->set('endDate', '2026-09-30')
        ->call('applyPeriod')
        ->assertForbidden();

    Livewire::test('pages::reports.index')
        ->call('resetPeriod')
        ->assertForbidden();
});

test('reports escape user-provided names in the rendered html', function () {
    $this->travelTo('2026-09-21 12:00:00');

    $user = User::factory()->create();
    $grade = EggGrade::factory()->create(['name' => '<script>alert(1)</script>']);
    $category = ExpenseCategory::factory()->create(['name' => '<img src=x onerror=alert(1)>']);

    Sale::factory()->for($grade, 'eggGrade')->create([
        'sale_date' => '2026-09-10',
        'total_amount' => '5.00',
        'normalized_egg_quantity' => 5,
    ]);
    Expense::factory()->for($category, 'expenseCategory')->create([
        'expense_date' => '2026-09-10',
        'amount' => '1.00',
    ]);

    Livewire::actingAs($user)
        ->test('pages::reports.index')
        ->assertSee('<script>alert(1)</script>')
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('<img src=x onerror=alert(1)>')
        ->assertDontSee('<img src=x onerror=alert(1)>', false);
});

test('csv exports stream records only for the selected date range', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['name' => 'Market stall']);
    $grade = EggGrade::factory()->create(['name' => 'Jumbo']);
    $category = ExpenseCategory::factory()->create(['name' => 'Feed']);

    Sale::factory()->for($customer)->for($grade, 'eggGrade')->create([
        'sale_date' => '2026-09-10',
        'unit' => 'egg',
        'quantity' => 4,
        'unit_price' => '1.25',
        'total_amount' => '5.00',
        'normalized_egg_quantity' => 4,
        'notes' => 'In range',
    ]);
    Sale::factory()->for($grade, 'eggGrade')->create([
        'sale_date' => '2026-08-31',
        'total_amount' => '99.00',
        'notes' => 'Out of range',
    ]);
    Expense::factory()->for($category, 'expenseCategory')->create([
        'expense_date' => '2026-09-12',
        'title' => 'Layer mash',
        'amount' => '15.50',
        'description' => 'September feed',
    ]);
    Expense::factory()->for($category, 'expenseCategory')->create([
        'expense_date' => '2026-10-01',
        'title' => 'Later feed',
        'amount' => '40.00',
    ]);
    Production::factory()->create([
        'production_date' => '2026-09-08',
        'total_eggs' => 80,
        'damaged_eggs' => 3,
        'notes' => 'Morning collection',
    ]);
    Production::factory()->create([
        'production_date' => '2026-08-01',
        'total_eggs' => 10,
        'damaged_eggs' => 1,
        'notes' => 'Ignored production',
    ]);
    StockAdjustment::factory()->for($grade, 'eggGrade')->create([
        'adjustment_date' => '2026-09-09',
        'type' => 'add',
        'quantity' => 6,
        'reason' => 'Found crate',
    ]);
    StockAdjustment::factory()->for($grade, 'eggGrade')->remove()->create([
        'adjustment_date' => '2026-10-02',
        'quantity' => 2,
        'reason' => 'Ignored adjustment',
    ]);

    $sales = $this->actingAs($user)->get(route('reports.exports', [
        'export' => ReportExport::Sales,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
    ]));
    $expenses = $this->actingAs($user)->get(route('reports.exports', [
        'export' => ReportExport::Expenses,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
    ]));
    $production = $this->actingAs($user)->get(route('reports.exports', [
        'export' => ReportExport::Production,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
    ]));
    $adjustments = $this->actingAs($user)->get(route('reports.exports', [
        'export' => ReportExport::StockAdjustments,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
    ]));

    $sales->assertOk()->assertDownload('sales-2026-09-01-to-2026-09-30.csv');
    $expenses->assertOk()->assertDownload('expenses-2026-09-01-to-2026-09-30.csv');
    $production->assertOk()->assertDownload('production-2026-09-01-to-2026-09-30.csv');
    $adjustments->assertOk()->assertDownload('stock-adjustments-2026-09-01-to-2026-09-30.csv');

    expect($sales->baseResponse)->toBeInstanceOf(StreamedResponse::class);

    $saleRows = csvRows($sales->streamedContent());
    $expenseRows = csvRows($expenses->streamedContent());
    $productionRows = csvRows($production->streamedContent());
    $adjustmentRows = csvRows($adjustments->streamedContent());

    expect($saleRows)->toHaveCount(2)
        ->and($saleRows[1])->toBe([
            '2026-09-10',
            'Market stall',
            'Jumbo',
            'egg',
            '4',
            '1.25',
            '5.00',
            '4',
            'In range',
        ])
        ->and(collect($saleRows)->flatten()->all())->not->toContain('Out of range')
        ->and($expenseRows)->toHaveCount(2)
        ->and($expenseRows[1][3])->toBe('15.50')
        ->and(collect($expenseRows)->flatten()->all())->not->toContain('Later feed')
        ->and($productionRows)->toHaveCount(2)
        ->and($productionRows[1])->toBe(['2026-09-08', '80', '3', 'Morning collection'])
        ->and(collect($productionRows)->flatten()->all())->not->toContain('Ignored production')
        ->and($adjustmentRows)->toHaveCount(2)
        ->and($adjustmentRows[1])->toBe(['2026-09-09', 'Jumbo', 'add', '6', 'Found crate'])
        ->and(collect($adjustmentRows)->flatten()->all())->not->toContain('Ignored adjustment');
});

test('csv exports neutralize formula injection prefixes', function (string $prefix) {
    $user = User::factory()->create();
    $payload = $prefix.'SUM(1,1)';
    $customer = Customer::factory()->create(['name' => $payload]);
    $grade = EggGrade::factory()->create(['name' => $payload]);
    $category = ExpenseCategory::factory()->create(['name' => $payload]);

    Sale::factory()->for($customer)->for($grade, 'eggGrade')->create([
        'sale_date' => '2026-09-10',
        'notes' => $payload,
        'total_amount' => '5.00',
    ]);
    Expense::factory()->for($category, 'expenseCategory')->create([
        'expense_date' => '2026-09-10',
        'title' => $payload,
        'description' => $payload,
        'amount' => '1.00',
    ]);
    Production::factory()->create([
        'production_date' => '2026-09-10',
        'total_eggs' => 10,
        'damaged_eggs' => 0,
        'notes' => $payload,
    ]);
    StockAdjustment::factory()->for($grade, 'eggGrade')->create([
        'adjustment_date' => '2026-09-10',
        'reason' => $payload,
    ]);

    $query = [
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
    ];

    foreach ([
        ReportExport::Sales,
        ReportExport::Expenses,
        ReportExport::Production,
        ReportExport::StockAdjustments,
    ] as $export) {
        $rows = csvRows($this->actingAs($user)->get(route('reports.exports', [
            'export' => $export,
            ...$query,
        ]))->streamedContent());

        $values = collect($rows)->flatten();

        expect($values->contains($payload))->toBeFalse()
            ->and($values->contains("'".$payload))->toBeTrue();
    }
})->with([
    'equals' => '=',
    'plus' => '+',
    'minus' => '-',
    'at' => '@',
]);

test('csv exports reject an inverted or missing date range', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('reports.index'))
        ->get(route('reports.exports', [
            'export' => ReportExport::Sales,
            'start_date' => '2026-09-30',
            'end_date' => '2026-09-01',
        ]))
        ->assertRedirectToRoute('reports.index')
        ->assertSessionHasErrors(['start_date', 'end_date']);

    $this->actingAs($user)
        ->from(route('reports.index'))
        ->get(route('reports.exports', ['export' => ReportExport::Sales]))
        ->assertRedirectToRoute('reports.index')
        ->assertSessionHasErrors(['start_date', 'end_date']);
});

test('guests are redirected from the farm report pdf', function () {
    $this->get(route('reports.pdf', [
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
    ]))->assertRedirectToRoute('login');
});

test('the farm report pdf includes exact period totals and current stock', function () {
    $this->travelTo('2026-09-21 12:00:00');

    $user = User::factory()->create();
    $grade = EggGrade::factory()->create(['name' => 'Premium']);
    $category = ExpenseCategory::factory()->create(['name' => 'Feed']);

    EggGrading::factory()->for($grade)->create([
        'grading_date' => '2026-08-01',
        'quantity' => 40,
    ]);
    Sale::factory()->for($grade, 'eggGrade')->create([
        'sale_date' => '2026-09-05',
        'total_amount' => '10.10',
        'unit_price' => '10.10',
        'quantity' => 1,
        'normalized_egg_quantity' => 10,
    ]);
    Sale::factory()->for($grade, 'eggGrade')->create([
        'sale_date' => '2026-08-31',
        'total_amount' => '99.00',
        'normalized_egg_quantity' => 5,
    ]);
    Expense::factory()->for($category, 'expenseCategory')->create([
        'expense_date' => '2026-09-10',
        'amount' => '1.13',
    ]);
    Expense::factory()->for($category, 'expenseCategory')->create([
        'expense_date' => '2026-10-01',
        'amount' => '40.00',
    ]);
    Production::factory()->create([
        'production_date' => '2026-09-08',
        'total_eggs' => 80,
        'damaged_eggs' => 3,
    ]);
    Production::factory()->create([
        'production_date' => '2026-08-01',
        'total_eggs' => 10,
        'damaged_eggs' => 1,
    ]);

    $response = $this->actingAs($user)->get(route('reports.pdf', [
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
    ]));

    $response->assertOk()->assertDownload('farm-report-2026-09-01-to-2026-09-30.pdf');

    expect($response->baseResponse)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->get('content-type'))->toContain('application/pdf');

    $text = pdfText($response->streamedContent());

    expect($text)
        ->toContain('Farm report')
        ->toContain('Period: 2026-09-01 to 2026-09-30')
        ->toContain('10.10')
        ->toContain('1.13')
        ->toContain('8.97')
        ->toContain('Premium')
        ->toContain('Feed')
        ->toContain('80')
        ->toContain('25')
        ->not->toContain('99.00')
        ->not->toContain('40.00');
});

test('the farm report pdf escapes names that would break the document', function () {
    $user = User::factory()->create();
    $grade = EggGrade::factory()->create(['name' => 'Large) Tj /F1 1 Tf (hack']);
    $category = ExpenseCategory::factory()->create(['name' => '<script>alert(1)</script>']);

    Sale::factory()->for($grade, 'eggGrade')->create([
        'sale_date' => '2026-09-10',
        'total_amount' => '5.00',
        'normalized_egg_quantity' => 5,
    ]);
    Expense::factory()->for($category, 'expenseCategory')->create([
        'expense_date' => '2026-09-10',
        'amount' => '1.00',
    ]);

    $pdf = $this->actingAs($user)->get(route('reports.pdf', [
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
    ]))->streamedContent();
    $text = pdfText($pdf);

    expect($pdf)->toStartWith('%PDF-')
        ->and($text)->toContain('Large) Tj /F1 1 Tf (hack')
        ->and($text)->toContain('<script>alert(1)</script>')
        ->and($pdf)->toContain('\\)')
        ->and($pdf)->not->toContain("\n(hack)");
});

test('the farm report pdf rejects an inverted or missing date range', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('reports.index'))
        ->get(route('reports.pdf', [
            'start_date' => '2026-09-30',
            'end_date' => '2026-09-01',
        ]))
        ->assertRedirectToRoute('reports.index')
        ->assertSessionHasErrors(['start_date', 'end_date']);

    $this->actingAs($user)
        ->from(route('reports.index'))
        ->get(route('reports.pdf'))
        ->assertRedirectToRoute('reports.index')
        ->assertSessionHasErrors(['start_date', 'end_date']);
});

/**
 * @return list<list<string|null>>
 */
function csvRows(string $content): array
{
    return collect(preg_split("/\r\n|\n|\r/", trim($content)) ?: [])
        ->reject(fn (string $line): bool => $line === '')
        ->map(fn (string $line): array => str_getcsv($line, escape: ''))
        ->values()
        ->all();
}

function pdfText(string $pdf): string
{
    preg_match_all('/\((?:\\\\.|[^\\\\)])*\)/', $pdf, $matches);

    return collect($matches[0] ?? [])
        ->map(function (string $literal): string {
            $inner = substr($literal, 1, -1);

            return strtr($inner, [
                '\\\\' => '\\',
                '\\(' => '(',
                '\\)' => ')',
                '\\r' => "\r",
                '\\n' => "\n",
            ]);
        })
        ->implode("\n");
}
