<?php

namespace App\Actions\Reports;

use App\Enums\ReportExport;
use App\Models\Expense;
use App\Models\Production;
use App\Models\Sale;
use App\Models\StockAdjustment;
use App\Support\Csv;
use App\Support\Money;
use Carbon\CarbonInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportReportCsv
{
    public function handle(ReportExport $export, string $startDate, string $endDate): StreamedResponse
    {
        return match ($export) {
            ReportExport::Sales => $this->sales($startDate, $endDate),
            ReportExport::Expenses => $this->expenses($startDate, $endDate),
            ReportExport::Production => $this->production($startDate, $endDate),
            ReportExport::StockAdjustments => $this->stockAdjustments($startDate, $endDate),
        };
    }

    private function sales(string $startDate, string $endDate): StreamedResponse
    {
        return Csv::download(
            ReportExport::Sales->filename($startDate, $endDate),
            ['Date', 'Customer', 'Egg grade', 'Unit', 'Quantity', 'Unit price', 'Total amount', 'Eggs', 'Notes'],
            fn () => $this->saleRows($startDate, $endDate),
        );
    }

    private function expenses(string $startDate, string $endDate): StreamedResponse
    {
        return Csv::download(
            ReportExport::Expenses->filename($startDate, $endDate),
            ['Date', 'Title', 'Category', 'Amount', 'Description'],
            fn () => $this->expenseRows($startDate, $endDate),
        );
    }

    private function production(string $startDate, string $endDate): StreamedResponse
    {
        return Csv::download(
            ReportExport::Production->filename($startDate, $endDate),
            ['Date', 'Total eggs', 'Damaged eggs', 'Notes'],
            fn () => $this->productionRows($startDate, $endDate),
        );
    }

    private function stockAdjustments(string $startDate, string $endDate): StreamedResponse
    {
        return Csv::download(
            ReportExport::StockAdjustments->filename($startDate, $endDate),
            ['Date', 'Egg grade', 'Type', 'Quantity', 'Reason'],
            fn () => $this->stockAdjustmentRows($startDate, $endDate),
        );
    }

    /** @return iterable<int, list<string>> */
    private function saleRows(string $startDate, string $endDate): iterable
    {
        $sales = Sale::query()
            ->with(['customer:id,name', 'eggGrade:id,name'])
            ->whereDate('sale_date', '>=', $startDate)
            ->whereDate('sale_date', '<=', $endDate)
            ->orderBy('sale_date')
            ->orderBy('id')
            ->lazy(200);

        foreach ($sales as $sale) {
            yield [
                $this->dateString($sale->sale_date),
                (string) (data_get($sale, 'customer.name') ?? __('Walk-in')),
                $sale->eggGrade->name,
                $sale->unit,
                (string) $sale->quantity,
                Money::fromCents(Money::toCents((string) $sale->unit_price)),
                Money::fromCents(Money::toCents((string) $sale->total_amount)),
                (string) $sale->normalized_egg_quantity,
                (string) ($sale->notes ?? ''),
            ];
        }
    }

    /** @return iterable<int, list<string>> */
    private function expenseRows(string $startDate, string $endDate): iterable
    {
        $expenses = Expense::query()
            ->with('expenseCategory:id,name')
            ->whereDate('expense_date', '>=', $startDate)
            ->whereDate('expense_date', '<=', $endDate)
            ->orderBy('expense_date')
            ->orderBy('id')
            ->lazy(200);

        foreach ($expenses as $expense) {
            yield [
                $this->dateString($expense->expense_date),
                $expense->title,
                $expense->expenseCategory->name,
                Money::fromCents(Money::toCents((string) $expense->amount)),
                (string) ($expense->description ?? ''),
            ];
        }
    }

    /** @return iterable<int, list<string>> */
    private function productionRows(string $startDate, string $endDate): iterable
    {
        $productions = Production::query()
            ->whereDate('production_date', '>=', $startDate)
            ->whereDate('production_date', '<=', $endDate)
            ->orderBy('production_date')
            ->orderBy('id')
            ->lazy(200);

        foreach ($productions as $production) {
            yield [
                $this->dateString($production->production_date),
                (string) $production->total_eggs,
                (string) $production->damaged_eggs,
                (string) ($production->notes ?? ''),
            ];
        }
    }

    /** @return iterable<int, list<string>> */
    private function stockAdjustmentRows(string $startDate, string $endDate): iterable
    {
        $adjustments = StockAdjustment::query()
            ->with('eggGrade:id,name')
            ->whereDate('adjustment_date', '>=', $startDate)
            ->whereDate('adjustment_date', '<=', $endDate)
            ->orderBy('adjustment_date')
            ->orderBy('id')
            ->lazy(200);

        foreach ($adjustments as $adjustment) {
            yield [
                $this->dateString($adjustment->adjustment_date),
                $adjustment->eggGrade->name,
                $adjustment->type,
                (string) $adjustment->quantity,
                $adjustment->reason,
            ];
        }
    }

    private function dateString(CarbonInterface|string $date): string
    {
        return $date instanceof CarbonInterface ? $date->toDateString() : $date;
    }
}
