<?php

use App\Models\Expense;
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

<section class="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Profit and loss') }}</flux:heading>
            <flux:subheading>{{ __('Track sales revenue, operating expenses, and net profit for any period.') }}</flux:subheading>
        </div>

        <form wire:submit="applyPeriod" class="grid gap-3 sm:grid-cols-[minmax(0,10rem)_minmax(0,10rem)_auto_auto] sm:items-end">
            <flux:input wire:model="startDate" :label="__('From')" type="date" required />
            <flux:input wire:model="endDate" :label="__('To')" type="date" required />
            <flux:button type="submit" variant="primary">{{ __('Apply') }}</flux:button>
            <flux:button type="button" variant="ghost" wire:click="resetPeriod">{{ __('This month') }}</flux:button>
        </form>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <flux:card class="space-y-2">
            <div class="flex items-center justify-between gap-3">
                <flux:text>{{ __('Sales revenue') }}</flux:text>
                <flux:badge color="green" size="sm">{{ trans_choice(':count sale|:count sales', $this->summary['sale_count'], ['count' => number_format($this->summary['sale_count'])]) }}</flux:badge>
            </div>
            <flux:heading size="xl">{{ $this->summary['revenue'] }}</flux:heading>
        </flux:card>

        <flux:card class="space-y-2">
            <div class="flex items-center justify-between gap-3">
                <flux:text>{{ __('Operating expenses') }}</flux:text>
                <flux:badge size="sm">{{ trans_choice(':count entry|:count entries', $this->summary['expense_count'], ['count' => number_format($this->summary['expense_count'])]) }}</flux:badge>
            </div>
            <flux:heading size="xl">{{ $this->summary['expenses'] }}</flux:heading>
        </flux:card>

        <flux:card class="space-y-2">
            <div class="flex items-center justify-between gap-3">
                <flux:text>{{ __('Net profit') }}</flux:text>
                <flux:badge :color="$this->summary['net_profit_cents'] >= 0 ? 'green' : 'red'" size="sm">
                    {{ $this->summary['net_profit_cents'] >= 0 ? __('Profit') : __('Loss') }}
                </flux:badge>
            </div>
            <flux:heading size="xl" :class="$this->summary['net_profit_cents'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                {{ $this->summary['net_profit'] }}
            </flux:heading>
        </flux:card>

        <flux:card class="space-y-2">
            <flux:text>{{ __('Eggs sold') }}</flux:text>
            <flux:heading size="xl">{{ number_format($this->summary['eggs_sold']) }}</flux:heading>
            <flux:text class="text-xs">{{ __('Normalized to individual eggs') }}</flux:text>
        </flux:card>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <flux:card class="min-w-0 space-y-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">{{ __('Revenue by egg grade') }}</flux:heading>
                    <flux:subheading>{{ __('Sales performance within the selected period.') }}</flux:subheading>
                </div>
                <flux:button size="sm" variant="ghost" :href="route('sales.index')" wire:navigate>{{ __('View sales') }}</flux:button>
            </div>

            @if ($this->salesByGrade->isEmpty())
                <div class="rounded-lg border border-dashed border-zinc-300 px-4 py-8 text-center dark:border-zinc-700">
                    <flux:heading>{{ __('No sales in this period') }}</flux:heading>
                    <flux:subheading>{{ __('Change the dates or record a sale to see revenue.') }}</flux:subheading>
                </div>
            @else
                <div class="space-y-5">
                    @foreach ($this->salesByGrade as $grade)
                        <div class="space-y-2" wire:key="sales-grade-{{ $grade['name'] }}">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <flux:heading size="sm">{{ $grade['name'] }}</flux:heading>
                                    <flux:text class="text-xs">{{ trans_choice(':count egg sold|:count eggs sold', $grade['eggs_sold'], ['count' => number_format($grade['eggs_sold'])]) }}</flux:text>
                                </div>
                                <flux:text class="font-medium">{{ $grade['revenue'] }}</flux:text>
                            </div>
                            <flux:progress :value="$grade['percentage']" />
                        </div>
                    @endforeach
                </div>
            @endif
        </flux:card>

        <flux:card class="min-w-0 space-y-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">{{ __('Expenses by category') }}</flux:heading>
                    <flux:subheading>{{ __('Where operating costs were spent.') }}</flux:subheading>
                </div>
                <flux:button size="sm" variant="ghost" :href="route('expenses.index')" wire:navigate>{{ __('View expenses') }}</flux:button>
            </div>

            @if ($this->expensesByCategory->isEmpty())
                <div class="rounded-lg border border-dashed border-zinc-300 px-4 py-8 text-center dark:border-zinc-700">
                    <flux:heading>{{ __('No expenses in this period') }}</flux:heading>
                    <flux:subheading>{{ __('Change the dates or record an expense to see costs.') }}</flux:subheading>
                </div>
            @else
                <div class="space-y-5">
                    @foreach ($this->expensesByCategory as $category)
                        <div class="space-y-2" wire:key="expense-category-{{ $category['name'] }}">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <flux:heading size="sm">{{ $category['name'] }}</flux:heading>
                                    <flux:text class="text-xs">{{ trans_choice(':count entry|:count entries', $category['expense_count'], ['count' => number_format($category['expense_count'])]) }}</flux:text>
                                </div>
                                <flux:text class="font-medium">{{ $category['expenses'] }}</flux:text>
                            </div>
                            <flux:progress :value="$category['percentage']" />
                        </div>
                    @endforeach
                </div>
            @endif
        </flux:card>
    </div>
</section>
