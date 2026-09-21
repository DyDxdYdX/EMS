<?php

namespace App\Http\Controllers;

use App\Actions\Reports\ExportReportPdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportPdfController extends Controller
{
    public function __invoke(Request $request, ExportReportPdf $exporter): StreamedResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date', 'before_or_equal:end_date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        return $exporter->handle($validated['start_date'], $validated['end_date']);
    }
}
