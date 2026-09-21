<?php

use App\Support\Csv;

test('csv escape prefixes formula injection characters', function (string $value) {
    expect(Csv::escape($value))->toBe("'".$value)
        ->and(Csv::escape($value))->not->toBe($value);
})->with([
    'equals' => '=1+1',
    'plus' => '+cmd',
    'minus' => '-1+1',
    'at' => '@SUM(A1)',
]);

test('csv escape leaves ordinary values unchanged', function () {
    expect(Csv::escape('Market stall'))->toBe('Market stall')
        ->and(Csv::escape('10.10'))->toBe('10.10')
        ->and(Csv::escape('2026-09-21'))->toBe('2026-09-21');
});
