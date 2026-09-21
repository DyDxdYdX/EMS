<?php

namespace App\Actions\Sales;

use App\Models\Customer;
use App\Models\EggGrade;
use App\Models\EggGrading;
use App\Models\FarmSetting;
use App\Models\Production;
use App\Models\Sale;
use App\Models\StockAdjustment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveSale
{
    private const int MAX_UNSIGNED_INTEGER = 4_294_967_295;

    private const int MAX_MONEY_CENTS = 9_999_999_999;

    /**
     * @param  array{sale_date: string, customer_id: int|null, egg_grade_id: int, unit: string, quantity: int, unit_price: string, notes: string|null}  $attributes
     */
    public function handle(?int $saleId, array $attributes): Sale
    {
        return DB::transaction(function () use ($saleId, $attributes): Sale {
            $this->lockInventoryLedger();

            $sale = $saleId === null
                ? new Sale
                : Sale::query()->findOrFail($saleId);
            $grade = EggGrade::query()->findOrFail($attributes['egg_grade_id']);

            if ($attributes['customer_id'] !== null) {
                Customer::query()->findOrFail($attributes['customer_id']);
            }

            if (! in_array($attributes['unit'], ['egg', 'tray'], true)) {
                throw ValidationException::withMessages([
                    'unit' => __('The selected sale unit is invalid.'),
                ]);
            }

            if (! $grade->is_active && $sale->egg_grade_id !== $grade->id) {
                throw ValidationException::withMessages([
                    'eggGradeId' => __('The selected egg grade is inactive.'),
                ]);
            }

            $eggsPerTray = FarmSetting::query()->lockForUpdate()->value('eggs_per_tray') ?? 30;
            $normalizedEggQuantity = Sale::normalizedEggQuantity(
                $attributes['unit'],
                $attributes['quantity'],
                (int) $eggsPerTray,
            );

            if ($normalizedEggQuantity > self::MAX_UNSIGNED_INTEGER) {
                throw ValidationException::withMessages([
                    'quantity' => __('The normalized egg quantity is too large.'),
                ]);
            }

            $availableStock = $grade->stockQuantity();

            if ($sale->exists && $sale->egg_grade_id === $grade->id) {
                $availableStock += $sale->normalized_egg_quantity;
            }

            if ($normalizedEggQuantity > $availableStock) {
                throw ValidationException::withMessages([
                    'quantity' => __('The sale quantity exceeds the available stock for :grade.', ['grade' => $grade->name]),
                ]);
            }

            $unitPriceCents = $this->moneyToCents($attributes['unit_price']);
            $totalAmountCents = $unitPriceCents * $attributes['quantity'];

            if ($totalAmountCents > self::MAX_MONEY_CENTS) {
                throw ValidationException::withMessages([
                    'unitPrice' => __('The sale total exceeds the supported amount.'),
                ]);
            }

            $sale->fill([
                'sale_date' => $attributes['sale_date'],
                'customer_id' => $attributes['customer_id'],
                'egg_grade_id' => $grade->id,
                'unit' => $attributes['unit'],
                'quantity' => $attributes['quantity'],
                'unit_price' => $this->centsToMoney($unitPriceCents),
                'total_amount' => $this->centsToMoney($totalAmountCents),
                'normalized_egg_quantity' => $normalizedEggQuantity,
                'notes' => $attributes['notes'],
            ]);
            $sale->save();

            return $sale;
        }, attempts: 3);
    }

    private function moneyToCents(string $amount): int
    {
        if (preg_match('/^\d+(?:\.\d{1,2})?$/D', $amount) !== 1) {
            throw ValidationException::withMessages([
                'unitPrice' => __('The unit price must be a valid amount with up to two decimal places.'),
            ]);
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function centsToMoney(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function lockInventoryLedger(): void
    {
        EggGrade::query()->lockForUpdate()->get(['id']);
        Production::query()->lockForUpdate()->get(['id']);
        EggGrading::query()->lockForUpdate()->get(['id']);
        Sale::query()->lockForUpdate()->get(['id']);
        StockAdjustment::query()->lockForUpdate()->get(['id']);
    }
}
