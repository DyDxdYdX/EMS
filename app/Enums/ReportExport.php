<?php

namespace App\Enums;

enum ReportExport: string
{
    case Sales = 'sales';
    case Expenses = 'expenses';
    case Production = 'production';
    case StockAdjustments = 'stock-adjustments';

    public function filename(string $startDate, string $endDate): string
    {
        return "{$this->value}-{$startDate}-to-{$endDate}.csv";
    }
}
