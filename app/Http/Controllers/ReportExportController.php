<?php

namespace App\Http\Controllers;

use App\Actions\Reports\ExportReportCsv;
use App\Enums\ReportExport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function __invoke(Request $request, ReportExport $export, ExportReportCsv $exporter): StreamedResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date', 'before_or_equal:end_date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        return $exporter->handle($export, $validated['start_date'], $validated['end_date']);
    }
}
