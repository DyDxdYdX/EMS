<?php

namespace App\Actions\EggGrading;

use App\Models\EggGrade;
use App\Models\EggGrading;
use App\Models\Production;
use App\Models\Sale;
use App\Models\StockAdjustment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveEggGrading
{
    /**
     * @param  array{grading_date: string, egg_grade_id: int, quantity: int, notes: string|null}  $attributes
     */
    public function handle(?int $gradingId, array $attributes): EggGrading
    {
        return DB::transaction(function () use ($gradingId, $attributes): EggGrading {
            $this->lockInventoryLedger();

            $grading = $gradingId === null
                ? new EggGrading
                : EggGrading::query()->findOrFail($gradingId);
            $grade = EggGrade::query()->findOrFail($attributes['egg_grade_id']);

            if (! $grade->is_active && $grading->egg_grade_id !== $grade->id) {
                throw ValidationException::withMessages([
                    'eggGradeId' => __('The selected egg grade is inactive.'),
                ]);
            }

            if ($attributes['quantity'] > EggGrading::availableEggQuantity($grading->exists ? $grading : null)) {
                throw ValidationException::withMessages([
                    'quantity' => __('The quantity exceeds the eggs available for grading.'),
                ]);
            }

            $this->ensureStockRemainsAvailable($grading, $grade, $attributes['quantity']);

            $grading->fill($attributes);
            $grading->save();

            return $grading;
        }, attempts: 3);
    }

    private function ensureStockRemainsAvailable(EggGrading $grading, EggGrade $newGrade, int $newQuantity): void
    {
        if (! $grading->exists) {
            return;
        }

        $originalGrade = EggGrade::query()->findOrFail($grading->egg_grade_id);
        $remainingOriginalStock = $originalGrade->stockQuantity() - $grading->quantity;

        if ($originalGrade->is($newGrade)) {
            $remainingOriginalStock += $newQuantity;
        }

        if ($remainingOriginalStock < 0) {
            throw ValidationException::withMessages([
                'quantity' => __('This change would reduce :grade stock below zero.', ['grade' => $originalGrade->name]),
            ]);
        }
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
