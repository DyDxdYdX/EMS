<?php

use App\Support\PdfDocument;

test('pdf documents escape parentheses and backslashes in text', function () {
    $pdf = new PdfDocument;
    $pdf->line('Grade) Tj (hack');
    $pdf->line('Path\\to\\file');
    $rendered = $pdf->render();

    expect($rendered)->toStartWith('%PDF-1.4')
        ->toContain('%%EOF')
        ->toContain('\\)')
        ->toContain('\\\\')
        ->not->toContain(') Tj (hack');
});
