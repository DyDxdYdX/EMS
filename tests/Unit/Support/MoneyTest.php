<?php

use App\Support\Money;

test('money converts amounts to integer cents without floating point arithmetic', function () {
    expect(Money::toCents('10.10') + Money::toCents('20.20'))->toBe(3030)
        ->and(Money::fromCents(3030))->toBe('30.30')
        ->and(Money::fromCents(Money::toCents('0.10') + Money::toCents('0.20')))->toBe('0.30');
});

test('money preserves exact two decimal places for negative amounts', function () {
    expect(Money::toCents('-150.25'))->toBe(-15025)
        ->and(Money::fromCents(-15025))->toBe('-150.25')
        ->and(Money::format(-15025))->toBe('-150.25');
});
