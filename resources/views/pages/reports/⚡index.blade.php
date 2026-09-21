<?php

use App\Enums\ReportExport;
use App\Reports\FarmReport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Reports')] class extends Component {
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

    #[Computed]
    public function farmReport(): FarmReport
    {
        return new FarmReport($this->appliedStartDate, $this->appliedEndDate);
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
        return $this->farmReport->profitAndLoss();
    }

    /** @return Collection<int, array{name: string, revenue: string, eggs_sold: int, sale_count: int}> */
    #[Computed]
    public function salesByGrade(): Collection
    {
        return $this->farmReport->salesByGrade();
    }

    /** @return Collection<int, array{name: string, expenses: string, expense_count: int}> */
    #[Computed]
    public function expensesByCategory(): Collection
    {
        return $this->farmReport->expensesByCategory();
    }

    /**
     * @return array{record_count: int, total_eggs: int, damaged_eggs: int, gradable_eggs: int}
     */
    #[Computed]
    public function productionTotals(): array
    {
        return $this->farmReport->productionTotals();
    }

    /** @return Collection<int, array{id: int<0, max>, name: string, is_active: bool, quantity: int}> */
    #[Computed]
    public function currentStock(): Collection
    {
        return $this->farmReport->currentStock();
    }

    public function applyPeriod(): void
    {
        $this->ensureAuthenticated();

        $validated = $this->validate($this->periodRules());

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

    public function exportUrl(string $export): string
    {
        return route('reports.exports', [
            'export' => ReportExport::from($export),
            'start_date' => $this->appliedStartDate,
            'end_date' => $this->appliedEndDate,
        ]);
    }

    public function pdfUrl(): string
    {
        return route('reports.pdf', [
            'start_date' => $this->appliedStartDate,
            'end_date' => $this->appliedEndDate,
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    private function periodRules(): array
    {
        return [
            'startDate' => ['required', 'date', 'before_or_equal:endDate'],
            'endDate' => ['required', 'date', 'after_or_equal:startDate'],
        ];
    }

    private function setCurrentMonth(): void
    {
        $this->startDate = now()->startOfMonth()->toDateString();
        $this->endDate = now()->toDateString();
        $this->appliedStartDate = $this->startDate;
        $this->appliedEndDate = $this->endDate;
    }

    private function clearReport(): void
    {
        unset(
            $this->farmReport,
            $this->summary,
            $this->salesByGrade,
            $this->expensesByCategory,
            $this->productionTotals,
            $this->currentStock,
        );
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
            <flux:heading size="xl" level="1">{{ __('Reports') }}</flux:heading>
            <flux:subheading>{{ __('Review farm performance and export records for the selected period.') }}</flux:subheading>
        </div>

        <form wire:submit="applyPeriod" class="grid gap-3 sm:grid-cols-[minmax(0,10rem)_minmax(0,10rem)_auto_auto] sm:items-end">
            <flux:input wire:model="startDate" :label="__('From')" type="date" required />
            <flux:input wire:model="endDate" :label="__('To')" type="date" required />
            <flux:button type="submit" variant="primary">{{ __('Apply') }}</flux:button>
            <flux:button type="button" variant="ghost" wire:click="resetPeriod">{{ __('This month') }}</flux:button>
        </form>
    </div>

    <flux:card class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('Exports') }}</flux:heading>
            <flux:subheading>{{ __('Downloads include records from :start through :end.', ['start' => $this->appliedStartDate, 'end' => $this->appliedEndDate]) }}</flux:subheading>
        </div>

        <div class="flex flex-wrap gap-2">
            <flux:button variant="primary" icon="document-arrow-down" :href="$this->pdfUrl()">{{ __('Download PDF') }}</flux:button>
            <flux:button icon="arrow-down-tray" :href="$this->exportUrl('sales')">{{ __('Export sales') }}</flux:button>
            <flux:button icon="arrow-down-tray" :href="$this->exportUrl('expenses')">{{ __('Export expenses') }}</flux:button>
            <flux:button icon="arrow-down-tray" :href="$this->exportUrl('production')">{{ __('Export production') }}</flux:button>
            <flux:button icon="arrow-down-tray" :href="$this->exportUrl('stock-adjustments')">{{ __('Export stock adjustments') }}</flux:button>
        </div>
    </flux:card>

    <div>
        <flux:heading size="lg" class="mb-4">{{ __('Profit and loss') }}</flux:heading>

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
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <flux:card class="min-w-0 space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Sales by egg grade') }}</flux:heading>
                <flux:subheading>{{ __('Revenue and eggs sold in the selected period.') }}</flux:subheading>
            </div>

            @if ($this->salesByGrade->isEmpty())
                <div class="rounded-lg border border-dashed border-zinc-300 px-4 py-8 text-center dark:border-zinc-700">
                    <flux:heading>{{ __('No sales in this period') }}</flux:heading>
                    <flux:subheading>{{ __('Change the dates or record a sale to see revenue.') }}</flux:subheading>
                </div>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Grade') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Eggs sold') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Revenue') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->salesByGrade as $grade)
                            <flux:table.row :key="$grade['name']">
                                <flux:table.cell variant="strong">{{ $grade['name'] }}</flux:table.cell>
                                <flux:table.cell align="end">{{ number_format($grade['eggs_sold']) }}</flux:table.cell>
                                <flux:table.cell align="end">{{ $grade['revenue'] }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>

        <flux:card class="min-w-0 space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Expenses by category') }}</flux:heading>
                <flux:subheading>{{ __('Operating costs in the selected period.') }}</flux:subheading>
            </div>

            @if ($this->expensesByCategory->isEmpty())
                <div class="rounded-lg border border-dashed border-zinc-300 px-4 py-8 text-center dark:border-zinc-700">
                    <flux:heading>{{ __('No expenses in this period') }}</flux:heading>
                    <flux:subheading>{{ __('Change the dates or record an expense to see costs.') }}</flux:subheading>
                </div>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Category') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Entries') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->expensesByCategory as $category)
                            <flux:table.row :key="$category['name']">
                                <flux:table.cell variant="strong">{{ $category['name'] }}</flux:table.cell>
                                <flux:table.cell align="end">{{ number_format($category['expense_count']) }}</flux:table.cell>
                                <flux:table.cell align="end">{{ $category['expenses'] }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <flux:card class="min-w-0 space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Production totals') }}</flux:heading>
                <flux:subheading>{{ __('Eggs collected and damaged in the selected period.') }}</flux:subheading>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-1">
                    <flux:text>{{ __('Records') }}</flux:text>
                    <flux:heading size="lg">{{ number_format($this->productionTotals['record_count']) }}</flux:heading>
                </div>
                <div class="space-y-1">
                    <flux:text>{{ __('Collected') }}</flux:text>
                    <flux:heading size="lg">{{ number_format($this->productionTotals['total_eggs']) }}</flux:heading>
                </div>
                <div class="space-y-1">
                    <flux:text>{{ __('Damaged') }}</flux:text>
                    <flux:heading size="lg">{{ number_format($this->productionTotals['damaged_eggs']) }}</flux:heading>
                </div>
                <div class="space-y-1">
                    <flux:text>{{ __('For grading') }}</flux:text>
                    <flux:heading size="lg">{{ number_format($this->productionTotals['gradable_eggs']) }}</flux:heading>
                </div>
            </div>
        </flux:card>

        <flux:card class="min-w-0 space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Current stock by egg grade') }}</flux:heading>
                <flux:subheading>{{ __('On-hand eggs right now, including activity outside the selected period.') }}</flux:subheading>
            </div>

            @if ($this->currentStock->isEmpty())
                <div class="rounded-lg border border-dashed border-zinc-300 px-4 py-8 text-center dark:border-zinc-700">
                    <flux:heading>{{ __('No egg grades yet') }}</flux:heading>
                    <flux:subheading>{{ __('Add an egg grade to track stock.') }}</flux:subheading>
                </div>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Grade') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('On hand') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->currentStock as $grade)
                            <flux:table.row :key="$grade['id']">
                                <flux:table.cell variant="strong">{{ $grade['name'] }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="$grade['is_active'] ? 'green' : null" size="sm">
                                        {{ $grade['is_active'] ? __('Active') : __('Inactive') }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell align="end">{{ number_format($grade['quantity']) }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    </div>
</section>
