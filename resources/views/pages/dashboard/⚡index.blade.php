<?php

use App\Models\Expense;
use App\Models\EggGrade;
use App\Models\EggGrading;
use App\Models\Production;
use App\Models\Sale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    public string $startDate = '';
    public string $endDate = '';

    #[Locked]
    public string $appliedStartDate = '';

    #[Locked]
    public string $appliedEndDate = '';

    public function mount(): void
    {
        $this->setCurrentMonth();
    }

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
    #[Computed]
    public function summary(): array
    {
        $sales = Sale::query()
            ->whereDate('sale_date', '>=', $this->appliedStartDate)
            ->whereDate('sale_date', '<=', $this->appliedEndDate)
            ->toBase()
            ->selectRaw('COUNT(*) as sale_count')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as revenue')
            ->selectRaw('COALESCE(SUM(normalized_egg_quantity), 0) as eggs_sold')
            ->first();

        $expenses = Expense::query()
            ->whereDate('expense_date', '>=', $this->appliedStartDate)
            ->whereDate('expense_date', '<=', $this->appliedEndDate)
            ->toBase()
            ->selectRaw('COUNT(*) as expense_count')
            ->selectRaw('COALESCE(SUM(amount), 0) as expenses')
            ->first();

        $revenueCents = $this->amountToCents((string) ($sales?->revenue ?? '0'));
        $expenseCents = $this->amountToCents((string) ($expenses?->expenses ?? '0'));
        $netProfitCents = $revenueCents - $expenseCents;

        return [
            'revenue' => $this->formatCents($revenueCents),
            'expenses' => $this->formatCents($expenseCents),
            'net_profit' => $this->formatCents($netProfitCents),
            'net_profit_cents' => $netProfitCents,
            'sale_count' => (int) ($sales?->sale_count ?? 0),
            'expense_count' => (int) ($expenses?->expense_count ?? 0),
            'eggs_sold' => (int) ($sales?->eggs_sold ?? 0),
        ];
    }

    /** @return array{collected: int, damaged: int, ready_to_grade: int, graded: int, sales: int} */
    #[Computed]
    public function today(): array
    {
        $production = Production::query()
            ->whereDate('production_date', today())
            ->toBase()
            ->selectRaw('COALESCE(SUM(total_eggs), 0) as collected')
            ->selectRaw('COALESCE(SUM(damaged_eggs), 0) as damaged')
            ->first();

        return [
            'collected' => (int) ($production?->collected ?? 0),
            'damaged' => (int) ($production?->damaged ?? 0),
            'ready_to_grade' => max(0, EggGrading::availableEggQuantity()),
            'graded' => (int) EggGrading::query()->whereDate('grading_date', today())->sum('quantity'),
            'sales' => Sale::query()->whereDate('sale_date', today())->count(),
        ];
    }

    /** @return Collection<int, array{name: string, quantity: int, is_low: bool}> */
    #[Computed]
    public function stockLevels(): Collection
    {
        return EggGrade::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (EggGrade $grade): array {
                $quantity = max(0, $grade->stockQuantity());

                return [
                    'name' => $grade->name,
                    'quantity' => $quantity,
                    'is_low' => $quantity <= 30,
                ];
            });
    }

    /** @return Collection<int, array{name: string, revenue: string, eggs_sold: int, percentage: int}> */
    #[Computed]
    public function salesByGrade(): Collection
    {
        $rows = DB::table('sales')
            ->join('egg_grades', 'egg_grades.id', '=', 'sales.egg_grade_id')
            ->whereDate('sales.sale_date', '>=', $this->appliedStartDate)
            ->whereDate('sales.sale_date', '<=', $this->appliedEndDate)
            ->groupBy('egg_grades.id', 'egg_grades.name')
            ->select('egg_grades.name')
            ->selectRaw('SUM(sales.total_amount) as revenue')
            ->selectRaw('SUM(sales.normalized_egg_quantity) as eggs_sold')
            ->orderByDesc('revenue')
            ->orderBy('egg_grades.name')
            ->get()
            ->map(fn (object $row): array => [
                'name' => (string) $row->name,
                'revenue_cents' => $this->amountToCents((string) $row->revenue),
                'eggs_sold' => (int) $row->eggs_sold,
            ]);

        $largestRevenue = (int) $rows->max('revenue_cents');

        return $rows->map(fn (array $row): array => [
            'name' => $row['name'],
            'revenue' => $this->formatCents($row['revenue_cents']),
            'eggs_sold' => $row['eggs_sold'],
            'percentage' => $this->percentage($row['revenue_cents'], $largestRevenue),
        ]);
    }

    /** @return Collection<int, array{name: string, expenses: string, expense_count: int, percentage: int}> */
    #[Computed]
    public function expensesByCategory(): Collection
    {
        $rows = DB::table('expenses')
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->whereDate('expenses.expense_date', '>=', $this->appliedStartDate)
            ->whereDate('expenses.expense_date', '<=', $this->appliedEndDate)
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->select('expense_categories.name')
            ->selectRaw('SUM(expenses.amount) as expenses')
            ->selectRaw('COUNT(*) as expense_count')
            ->orderByDesc('expenses')
            ->orderBy('expense_categories.name')
            ->get()
            ->map(fn (object $row): array => [
                'name' => (string) $row->name,
                'expense_cents' => $this->amountToCents((string) $row->expenses),
                'expense_count' => (int) $row->expense_count,
            ]);

        $largestExpense = (int) $rows->max('expense_cents');

        return $rows->map(fn (array $row): array => [
            'name' => $row['name'],
            'expenses' => $this->formatCents($row['expense_cents']),
            'expense_count' => $row['expense_count'],
            'percentage' => $this->percentage($row['expense_cents'], $largestExpense),
        ]);
    }

    public function applyPeriod(): void
    {
        $this->ensureAuthenticated();

        $validated = $this->validate([
            'startDate' => ['required', 'date', 'before_or_equal:endDate'],
            'endDate' => ['required', 'date', 'after_or_equal:startDate'],
        ]);

        $this->appliedStartDate = $validated['startDate'];
        $this->appliedEndDate = $validated['endDate'];
        $this->clearReport();
    }

    public function resetPeriod(): void
    {
        $this->ensureAuthenticated();

        $this->setCurrentMonth();
        $this->resetValidation();
        $this->clearReport();
    }

    private function setCurrentMonth(): void
    {
        $this->startDate = now()->startOfMonth()->toDateString();
        $this->endDate = now()->toDateString();
        $this->appliedStartDate = $this->startDate;
        $this->appliedEndDate = $this->endDate;
    }

    private function amountToCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        return ((int) $whole * 100) + (int) $fraction;
    }

    private function formatCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absoluteCents = abs($cents);

        return $sign.number_format(intdiv($absoluteCents, 100)).'.'.str_pad((string) ($absoluteCents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function percentage(int $amount, int $largestAmount): int
    {
        if ($largestAmount === 0) {
            return 0;
        }

        return (int) round(($amount / $largestAmount) * 100);
    }

    private function clearReport(): void
    {
        unset($this->summary, $this->salesByGrade, $this->expensesByCategory);
    }

    private function ensureAuthenticated(): void
    {
        abort_unless(Auth::check(), 403);
    }
};
?>

<section class="page-shell">
    <div class="page-header">
        <div>
            <flux:heading size="xl" level="1">{{ __('Farm overview') }}</flux:heading>
            <flux:subheading>{{ __('Today’s production, grading progress, stock, and business performance.') }}</flux:subheading>
        </div>

        <div class="flex flex-wrap gap-2">
            <flux:button icon="clipboard-document-list" :href="route('productions.index')" wire:navigate>{{ __('Record production') }}</flux:button>
            <flux:button icon="adjustments-horizontal" :href="route('gradings.index')" wire:navigate>{{ __('Grade eggs') }}</flux:button>
            <flux:button icon="shopping-cart" variant="primary" :href="route('sales.index')" wire:navigate>{{ __('Record sale') }}</flux:button>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <flux:card class="metric-card space-y-3">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:text>{{ __('Collected today') }}</flux:text>
                    <flux:heading size="xl">{{ number_format($this->today['collected']) }}</flux:heading>
                </div>
                <div class="rounded-xl bg-emerald-100 p-2 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                    <flux:icon name="clipboard-document-list" class="size-5" />
                </div>
            </div>
            <flux:text class="text-xs">{{ trans_choice(':count damaged egg|:count damaged eggs', $this->today['damaged'], ['count' => number_format($this->today['damaged'])]) }}</flux:text>
        </flux:card>

        <flux:card class="metric-card space-y-3">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:text>{{ __('Waiting for grading') }}</flux:text>
                    <flux:heading size="xl">{{ number_format($this->today['ready_to_grade']) }}</flux:heading>
                </div>
                <div class="rounded-xl bg-amber-100 p-2 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                    <flux:icon name="adjustments-horizontal" class="size-5" />
                </div>
            </div>
            <flux:text class="text-xs">{{ number_format($this->today['graded']) }} {{ __('graded today') }}</flux:text>
        </flux:card>

        <flux:card class="metric-card space-y-3">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:text>{{ __('Sales today') }}</flux:text>
                    <flux:heading size="xl">{{ number_format($this->today['sales']) }}</flux:heading>
                </div>
                <div class="rounded-xl bg-sky-100 p-2 text-sky-700 dark:bg-sky-950 dark:text-sky-300">
                    <flux:icon name="shopping-cart" class="size-5" />
                </div>
            </div>
            <flux:text class="text-xs">{{ number_format($this->summary['eggs_sold']) }} {{ __('eggs sold this period') }}</flux:text>
        </flux:card>

        <flux:card class="metric-card space-y-3">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:text>{{ __('Net profit') }}</flux:text>
                    <flux:heading size="xl" :class="$this->summary['net_profit_cents'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                        {{ __('RM :amount', ['amount' => $this->summary['net_profit']]) }}
                    </flux:heading>
                </div>
                <flux:badge :color="$this->summary['net_profit_cents'] >= 0 ? 'green' : 'red'" size="sm">
                    {{ $this->summary['net_profit_cents'] >= 0 ? __('Profit') : __('Loss') }}
                </flux:badge>
            </div>
            <flux:text class="text-xs">{{ __('Current reporting period') }}</flux:text>
        </flux:card>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(19rem,.75fr)]">
        <flux:card class="data-panel space-y-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">{{ __('Stock by egg grade') }}</flux:heading>
                    <flux:subheading>{{ __('Live on-hand inventory after grading, sales, and adjustments.') }}</flux:subheading>
                </div>
                <flux:button size="sm" variant="ghost" :href="route('stock-adjustments.index')" wire:navigate>{{ __('Manage stock') }}</flux:button>
            </div>

            @if ($this->stockLevels->isEmpty())
                <div class="empty-state">
                    <flux:heading>{{ __('No egg grades configured') }}</flux:heading>
                    <flux:subheading>{{ __('Create an egg grade before recording graded stock.') }}</flux:subheading>
                    <flux:button class="mt-4" size="sm" :href="route('egg-grades.index')" wire:navigate>{{ __('Configure egg grades') }}</flux:button>
                </div>
            @else
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($this->stockLevels as $stock)
                        <div class="flex items-center justify-between gap-4 rounded-xl border border-zinc-200 px-4 py-3 dark:border-zinc-700" wire:key="stock-{{ $stock['name'] }}">
                            <div>
                                <flux:heading size="sm">{{ $stock['name'] }}</flux:heading>
                                <flux:text class="text-xs">{{ __('Available to sell') }}</flux:text>
                            </div>
                            <div class="text-end">
                                <flux:heading size="lg">{{ number_format($stock['quantity']) }}</flux:heading>
                                @if ($stock['is_low'])
                                    <flux:badge color="amber" size="sm">{{ __('Low stock') }}</flux:badge>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </flux:card>

        <flux:card class="data-panel space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Needs attention') }}</flux:heading>
                <flux:subheading>{{ __('A quick check before you continue.') }}</flux:subheading>
            </div>

            <div class="space-y-3">
                @if ($this->today['collected'] === 0)
                    <div class="rounded-xl bg-amber-50 p-4 dark:bg-amber-950/40">
                        <flux:heading size="sm">{{ __('No production recorded today') }}</flux:heading>
                        <flux:text class="mt-1 text-sm">{{ __('Record today’s collection to keep grading availability accurate.') }}</flux:text>
                        <flux:button class="mt-3" size="sm" variant="ghost" :href="route('productions.index')" wire:navigate>{{ __('Record production') }}</flux:button>
                    </div>
                @endif

                @if ($this->today['ready_to_grade'] > 0)
                    <div class="rounded-xl bg-sky-50 p-4 dark:bg-sky-950/40">
                        <flux:heading size="sm">{{ trans_choice(':count egg is waiting for grading|:count eggs are waiting for grading', $this->today['ready_to_grade'], ['count' => number_format($this->today['ready_to_grade'])]) }}</flux:heading>
                        <flux:button class="mt-3" size="sm" variant="ghost" :href="route('gradings.index')" wire:navigate>{{ __('Grade now') }}</flux:button>
                    </div>
                @endif

                @if ($this->stockLevels->where('is_low', true)->isNotEmpty())
                    <div class="rounded-xl bg-red-50 p-4 dark:bg-red-950/30">
                        <flux:heading size="sm">{{ trans_choice(':count grade has low stock|:count grades have low stock', $this->stockLevels->where('is_low', true)->count(), ['count' => $this->stockLevels->where('is_low', true)->count()]) }}</flux:heading>
                        <flux:text class="mt-1 text-sm">{{ $this->stockLevels->where('is_low', true)->pluck('name')->join(', ') }}</flux:text>
                    </div>
                @endif

                @if ($this->today['collected'] > 0 && $this->today['ready_to_grade'] === 0 && $this->stockLevels->where('is_low', true)->isEmpty())
                    <div class="empty-state py-7">
                        <flux:icon name="check-circle" class="mx-auto mb-2 size-6 text-emerald-600" />
                        <flux:heading>{{ __('Everything looks up to date') }}</flux:heading>
                        <flux:subheading>{{ __('There are no operational alerts right now.') }}</flux:subheading>
                    </div>
                @endif
            </div>
        </flux:card>
    </div>

    <flux:card class="data-panel space-y-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <flux:heading size="lg">{{ __('Profit and loss') }}</flux:heading>
                <flux:subheading>{{ __('Review revenue and operating costs for a selected period.') }}</flux:subheading>
            </div>

            <form wire:submit="applyPeriod" class="grid gap-3 sm:grid-cols-[minmax(0,10rem)_minmax(0,10rem)_auto_auto] sm:items-end">
                <flux:input wire:model="startDate" :label="__('From')" type="date" required />
                <flux:input wire:model="endDate" :label="__('To')" type="date" required />
                <flux:button class="action-button" type="submit" variant="primary">{{ __('Apply') }}</flux:button>
                <flux:button class="action-button" type="button" variant="ghost" wire:click="resetPeriod">{{ __('This month') }}</flux:button>
            </form>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800">
                <flux:text>{{ __('Sales revenue') }}</flux:text>
                <flux:heading size="lg">{{ __('RM :amount', ['amount' => $this->summary['revenue']]) }}</flux:heading>
                <flux:text class="text-xs">{{ trans_choice(':count sale|:count sales', $this->summary['sale_count'], ['count' => number_format($this->summary['sale_count'])]) }}</flux:text>
            </div>
            <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800">
                <flux:text>{{ __('Operating expenses') }}</flux:text>
                <flux:heading size="lg">{{ __('RM :amount', ['amount' => $this->summary['expenses']]) }}</flux:heading>
                <flux:text class="text-xs">{{ trans_choice(':count entry|:count entries', $this->summary['expense_count'], ['count' => number_format($this->summary['expense_count'])]) }}</flux:text>
            </div>
            <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800">
                <flux:text>{{ __('Net profit') }}</flux:text>
                <flux:heading size="lg" :class="$this->summary['net_profit_cents'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">{{ __('RM :amount', ['amount' => $this->summary['net_profit']]) }}</flux:heading>
                <flux:text class="text-xs">{{ number_format($this->summary['eggs_sold']) }} {{ __('eggs sold') }}</flux:text>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div>
                    <flux:heading>{{ __('Revenue by egg grade') }}</flux:heading>
                    <flux:subheading>{{ __('Sales performance within the selected period.') }}</flux:subheading>
                </div>
                @if ($this->salesByGrade->isEmpty())
                    <div class="empty-state py-6">
                        <flux:heading>{{ __('No sales in this period') }}</flux:heading>
                        <flux:subheading>{{ __('Change the dates or record a sale to see revenue.') }}</flux:subheading>
                        <flux:button class="mt-3" size="sm" :href="route('sales.index')" wire:navigate>{{ __('Record a sale') }}</flux:button>
                    </div>
                @else
                    @foreach ($this->salesByGrade as $grade)
                        <div class="space-y-2" wire:key="sales-grade-{{ $grade['name'] }}">
                            <div class="flex justify-between gap-4">
                                <flux:text>{{ $grade['name'] }}</flux:text>
                                <flux:text class="font-medium">{{ __('RM :amount', ['amount' => $grade['revenue']]) }}</flux:text>
                            </div>
                            <flux:progress :value="$grade['percentage']" />
                        </div>
                    @endforeach
                @endif
            </div>

            <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div>
                    <flux:heading>{{ __('Expenses by category') }}</flux:heading>
                    <flux:subheading>{{ __('Where operating costs were spent.') }}</flux:subheading>
                </div>
                @if ($this->expensesByCategory->isEmpty())
                    <div class="empty-state py-6">
                        <flux:heading>{{ __('No expenses in this period') }}</flux:heading>
                        <flux:subheading>{{ __('Change the dates or record an expense to see costs.') }}</flux:subheading>
                        <flux:button class="mt-3" size="sm" :href="route('expenses.index')" wire:navigate>{{ __('Record an expense') }}</flux:button>
                    </div>
                @else
                    @foreach ($this->expensesByCategory as $category)
                        <div class="space-y-2" wire:key="expense-category-{{ $category['name'] }}">
                            <div class="flex justify-between gap-4">
                                <flux:text>{{ $category['name'] }}</flux:text>
                                <flux:text class="font-medium">{{ __('RM :amount', ['amount' => $category['expenses']]) }}</flux:text>
                            </div>
                            <flux:progress :value="$category['percentage']" />
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </flux:card>
</section>
