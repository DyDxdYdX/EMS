<?php

namespace App\Actions\StockAdjustment;

use App\Models\EggGrade;
use App\Models\EggGrading;
use App\Models\Production;
use App\Models\Sale;
use App\Models\StockAdjustment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveStockAdjustment
{
    /**
     * @param  array{adjustment_date: string, egg_grade_id: int, type: string, quantity: int, reason: string}  $attributes
     */
    public function handle(?int $adjustmentId, array $attributes): StockAdjustment
    {
        return DB::transaction(function () use ($adjustmentId, $attributes): StockAdjustment {
            $this->lockInventoryLedger();

            $adjustment = $adjustmentId === null
                ? new StockAdjustment
                : StockAdjustment::query()->findOrFail($adjustmentId);
            $grade = EggGrade::query()->findOrFail($attributes['egg_grade_id']);

            if (! in_array($attributes['type'], ['add', 'remove'], true)) {
                throw ValidationException::withMessages([
                    'type' => __('The selected adjustment type is invalid.'),
                ]);
            }

            if (! $grade->is_active && $adjustment->egg_grade_id !== $grade->id) {
                throw ValidationException::withMessages([
                    'eggGradeId' => __('The selected egg grade is inactive.'),
                ]);
            }

            $this->ensureStockRemainsAvailable($adjustment, $grade, $attributes['type'], $attributes['quantity']);

            $adjustment->fill($attributes);
            $adjustment->save();

            return $adjustment;
        }, attempts: 3);
    }

    private function ensureStockRemainsAvailable(
        StockAdjustment $adjustment,
        EggGrade $newGrade,
        string $newType,
        int $newQuantity,
    ): void {
        $projectedStock = [
            $newGrade->id => $newGrade->stockQuantity(),
        ];

        if ($adjustment->exists) {
            $originalGrade = EggGrade::query()->findOrFail($adjustment->egg_grade_id);
            $projectedStock[$originalGrade->id] = $originalGrade->stockQuantity();
            $projectedStock[$originalGrade->id] -= $this->stockImpact($adjustment->type, $adjustment->quantity);
        }

        $projectedStock[$newGrade->id] += $this->stockImpact($newType, $newQuantity);

        foreach ($projectedStock as $gradeId => $quantity) {
            if ($quantity >= 0) {
                continue;
            }

            $affectedGrade = EggGrade::query()->findOrFail($gradeId);

            throw ValidationException::withMessages([
                'quantity' => __('This adjustment would reduce :grade stock below zero.', ['grade' => $affectedGrade->name]),
            ]);
        }
    }

    private function stockImpact(string $type, int $quantity): int
    {
        return $type === 'add' ? $quantity : -$quantity;
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
