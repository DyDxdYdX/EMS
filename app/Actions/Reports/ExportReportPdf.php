<?php

namespace App\Actions\Reports;

use App\Reports\FarmReport;
use App\Support\PdfDocument;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportReportPdf
{
    public function handle(string $startDate, string $endDate): StreamedResponse
    {
        $filename = "farm-report-{$startDate}-to-{$endDate}.pdf";
        $bytes = $this->build($startDate, $endDate);

        return response()->streamDownload(function () use ($bytes): void {
            echo $bytes;
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function build(string $startDate, string $endDate): string
    {
        $report = new FarmReport($startDate, $endDate);
        $summary = $report->profitAndLoss();
        $pdf = new PdfDocument;
        $columnWidths = [220.0, 130.0, 130.0];

        $pdf->heading((string) __('Farm report'));
        $pdf->line((string) __('Period: :start to :end', ['start' => $startDate, 'end' => $endDate]));
        $pdf->line((string) __('Generated: :date', ['date' => now()->toDateString()]));
        $pdf->spacer();

        $pdf->subheading((string) __('Profit and loss'));
        $pdf->pair((string) __('Sales revenue'), $summary['revenue']);
        $pdf->pair((string) __('Operating expenses'), $summary['expenses']);
        $pdf->pair((string) __('Net profit'), $summary['net_profit']);
        $pdf->pair((string) __('Eggs sold'), number_format($summary['eggs_sold']));
        $pdf->spacer();

        $pdf->subheading((string) __('Sales by egg grade'));
        $salesByGrade = $report->salesByGrade();

        if ($salesByGrade->isEmpty()) {
            $pdf->line((string) __('No sales in this period'));
        } else {
            $pdf->row([
                (string) __('Grade'),
                (string) __('Eggs sold'),
                (string) __('Revenue'),
            ], $columnWidths, true);

            foreach ($salesByGrade as $grade) {
                $pdf->row([
                    $grade['name'],
                    number_format($grade['eggs_sold']),
                    $grade['revenue'],
                ], $columnWidths);
            }
        }

        $pdf->spacer();
        $pdf->subheading((string) __('Expenses by category'));
        $expensesByCategory = $report->expensesByCategory();

        if ($expensesByCategory->isEmpty()) {
            $pdf->line((string) __('No expenses in this period'));
        } else {
            $pdf->row([
                (string) __('Category'),
                (string) __('Entries'),
                (string) __('Amount'),
            ], $columnWidths, true);

            foreach ($expensesByCategory as $category) {
                $pdf->row([
                    $category['name'],
                    number_format($category['expense_count']),
                    $category['expenses'],
                ], $columnWidths);
            }
        }

        $pdf->spacer();
        $production = $report->productionTotals();
        $pdf->subheading((string) __('Production totals'));
        $pdf->pair((string) __('Records'), number_format($production['record_count']));
        $pdf->pair((string) __('Collected'), number_format($production['total_eggs']));
        $pdf->pair((string) __('Damaged'), number_format($production['damaged_eggs']));
        $pdf->pair((string) __('For grading'), number_format($production['gradable_eggs']));
        $pdf->spacer();

        $pdf->subheading((string) __('Current stock by egg grade'));
        $pdf->line((string) __('On-hand eggs right now, including activity outside the selected period.'));
        $stock = $report->currentStock();

        if ($stock->isEmpty()) {
            $pdf->line((string) __('No egg grades yet'));
        } else {
            $pdf->row([
                (string) __('Grade'),
                (string) __('Status'),
                (string) __('On hand'),
            ], $columnWidths, true);

            foreach ($stock as $grade) {
                $pdf->row([
                    $grade['name'],
                    $grade['is_active'] ? (string) __('Active') : (string) __('Inactive'),
                    number_format($grade['quantity']),
                ], $columnWidths);
            }
        }

        return $pdf->render();
    }
}
