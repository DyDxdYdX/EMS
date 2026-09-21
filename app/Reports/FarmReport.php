<?php

namespace App\Reports;

use App\Models\EggGrade;
use App\Models\Expense;
use App\Models\Production;
use App\Models\Sale;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FarmReport
{
    /**
     * @var array{
     *     revenue_cents: int,
     *     expense_cents: int,
     *     sale_count: int,
     *     expense_count: int,
     *     eggs_sold: int,
     *     sales_by_grade: array<int, array{name: string, revenue_cents: int, eggs_sold: int, sale_count: int}>,
     *     expenses_by_category: array<int, array{name: string, expense_cents: int, expense_count: int}>
     * }|null
     */
    private ?array $ledger = null;

    public function __construct(
        public readonly string $startDate,
        public readonly string $endDate,
    ) {}

    /**
     * @return array{
     *     revenue: string,
     *     expenses: string,
     *     net_profit: string,
     *     net_profit_cents: int,
     *     sale_count: int,
     *     expense_count: int,
     *     eggs_sold: int
     * }
     */
    public function profitAndLoss(): array
    {
        $ledger = $this->ledger();
        $netProfitCents = $ledger['revenue_cents'] - $ledger['expense_cents'];

        return [
            'revenue' => Money::format($ledger['revenue_cents']),
            'expenses' => Money::format($ledger['expense_cents']),
            'net_profit' => Money::format($netProfitCents),
            'net_profit_cents' => $netProfitCents,
            'sale_count' => $ledger['sale_count'],
            'expense_count' => $ledger['expense_count'],
            'eggs_sold' => $ledger['eggs_sold'],
        ];
    }

    /** @return Collection<int, array{name: string, revenue: string, eggs_sold: int, sale_count: int}> */
    public function salesByGrade(): Collection
    {
        return collect($this->ledger()['sales_by_grade'])
            ->sort(fn (array $left, array $right): int => $right['revenue_cents'] <=> $left['revenue_cents'] ?: $left['name'] <=> $right['name'])
            ->values()
            ->map(fn (array $row): array => [
                'name' => $row['name'],
                'revenue' => Money::format($row['revenue_cents']),
                'eggs_sold' => $row['eggs_sold'],
                'sale_count' => $row['sale_count'],
            ]);
    }

    /** @return Collection<int, array{name: string, expenses: string, expense_count: int}> */
    public function expensesByCategory(): Collection
    {
        return collect($this->ledger()['expenses_by_category'])
            ->sort(fn (array $left, array $right): int => $right['expense_cents'] <=> $left['expense_cents'] ?: $left['name'] <=> $right['name'])
            ->values()
            ->map(fn (array $row): array => [
                'name' => $row['name'],
                'expenses' => Money::format($row['expense_cents']),
                'expense_count' => $row['expense_count'],
            ]);
    }

    /**
     * @return array{record_count: int, total_eggs: int, damaged_eggs: int, gradable_eggs: int}
     */
    public function productionTotals(): array
    {
        $production = Production::query()
            ->whereDate('production_date', '>=', $this->startDate)
            ->whereDate('production_date', '<=', $this->endDate)
            ->toBase()
            ->selectRaw('COUNT(*) as record_count')
            ->selectRaw('COALESCE(SUM(total_eggs), 0) as total_eggs')
            ->selectRaw('COALESCE(SUM(damaged_eggs), 0) as damaged_eggs')
            ->first();

        $totalEggs = (int) $production->total_eggs;
        $damagedEggs = (int) $production->damaged_eggs;

        return [
            'record_count' => (int) $production->record_count,
            'total_eggs' => $totalEggs,
            'damaged_eggs' => $damagedEggs,
            'gradable_eggs' => $totalEggs - $damagedEggs,
        ];
    }

    /** @return Collection<int, array{id: int<0, max>, name: string, is_active: bool, quantity: int}> */
    public function currentStock(): Collection
    {
        return EggGrade::query()
            ->withSum('gradings as graded_quantity', 'quantity')
            ->withSum('sales as sold_quantity', 'normalized_egg_quantity')
            ->withSum(['stockAdjustments as added_quantity' => function (Builder $query): void {
                $query->where('type', 'add');
            }], 'quantity')
            ->withSum(['stockAdjustments as removed_quantity' => function (Builder $query): void {
                $query->where('type', 'remove');
            }], 'quantity')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (EggGrade $grade): array => [
                'id' => $grade->id,
                'name' => $grade->name,
                'is_active' => $grade->is_active,
                'quantity' => (int) $grade->getAttribute('graded_quantity')
                    + (int) $grade->getAttribute('added_quantity')
                    - (int) $grade->getAttribute('sold_quantity')
                    - (int) $grade->getAttribute('removed_quantity'),
            ]);
    }

    /**
     * @return array{
     *     revenue_cents: int,
     *     expense_cents: int,
     *     sale_count: int,
     *     expense_count: int,
     *     eggs_sold: int,
     *     sales_by_grade: array<int, array{name: string, revenue_cents: int, eggs_sold: int, sale_count: int}>,
     *     expenses_by_category: array<int, array{name: string, expense_cents: int, expense_count: int}>
     * }
     */
    private function ledger(): array
    {
        return $this->ledger ??= $this->buildLedger();
    }

    /**
     * @return array{
     *     revenue_cents: int,
     *     expense_cents: int,
     *     sale_count: int,
     *     expense_count: int,
     *     eggs_sold: int,
     *     sales_by_grade: array<int, array{name: string, revenue_cents: int, eggs_sold: int, sale_count: int}>,
     *     expenses_by_category: array<int, array{name: string, expense_cents: int, expense_count: int}>
     * }
     */
    private function buildLedger(): array
    {
        $revenueCents = 0;
        $saleCount = 0;
        $eggsSold = 0;
        /** @var array<int, array{name: string, revenue_cents: int, eggs_sold: int, sale_count: int}> $salesByGrade */
        $salesByGrade = [];

        Sale::query()
            ->with('eggGrade:id,name')
            ->whereDate('sale_date', '>=', $this->startDate)
            ->whereDate('sale_date', '<=', $this->endDate)
            ->select(['id', 'egg_grade_id', 'total_amount', 'normalized_egg_quantity'])
            ->orderBy('id')
            ->lazyById(200)
            ->each(function (Sale $sale) use (&$revenueCents, &$saleCount, &$eggsSold, &$salesByGrade): void {
                $amountCents = Money::toCents((string) $sale->total_amount);
                $revenueCents += $amountCents;
                $saleCount++;
                $eggsSold += $sale->normalized_egg_quantity;

                $gradeId = $sale->egg_grade_id;
                $salesByGrade[$gradeId] ??= [
                    'name' => $sale->eggGrade->name,
                    'revenue_cents' => 0,
                    'eggs_sold' => 0,
                    'sale_count' => 0,
                ];
                $salesByGrade[$gradeId]['revenue_cents'] += $amountCents;
                $salesByGrade[$gradeId]['eggs_sold'] += $sale->normalized_egg_quantity;
                $salesByGrade[$gradeId]['sale_count']++;
            });

        $expenseCents = 0;
        $expenseCount = 0;
        /** @var array<int, array{name: string, expense_cents: int, expense_count: int}> $expensesByCategory */
        $expensesByCategory = [];

        Expense::query()
            ->with('expenseCategory:id,name')
            ->whereDate('expense_date', '>=', $this->startDate)
            ->whereDate('expense_date', '<=', $this->endDate)
            ->select(['id', 'expense_category_id', 'amount'])
            ->orderBy('id')
            ->lazyById(200)
            ->each(function (Expense $expense) use (&$expenseCents, &$expenseCount, &$expensesByCategory): void {
                $amountCents = Money::toCents((string) $expense->amount);
                $expenseCents += $amountCents;
                $expenseCount++;

                $categoryId = $expense->expense_category_id;
                $expensesByCategory[$categoryId] ??= [
                    'name' => $expense->expenseCategory->name,
                    'expense_cents' => 0,
                    'expense_count' => 0,
                ];
                $expensesByCategory[$categoryId]['expense_cents'] += $amountCents;
                $expensesByCategory[$categoryId]['expense_count']++;
            });

        return [
            'revenue_cents' => $revenueCents,
            'expense_cents' => $expenseCents,
            'sale_count' => $saleCount,
            'expense_count' => $expenseCount,
            'eggs_sold' => $eggsSold,
            'sales_by_grade' => $salesByGrade,
            'expenses_by_category' => $expensesByCategory,
        ];
    }
}
