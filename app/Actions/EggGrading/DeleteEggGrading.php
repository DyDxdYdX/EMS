<?php

namespace App\Actions\EggGrading;

use App\Models\EggGrade;
use App\Models\EggGrading;
use App\Models\Production;
use App\Models\Sale;
use App\Models\StockAdjustment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteEggGrading
{
    public function handle(int $gradingId): void
    {
        DB::transaction(function () use ($gradingId): void {
            $this->lockInventoryLedger();

            $grading = EggGrading::query()->findOrFail($gradingId);
            $grade = EggGrade::query()->findOrFail($grading->egg_grade_id);

            if ($grade->stockQuantity() - $grading->quantity < 0) {
                throw ValidationException::withMessages([
                    'deleteGrading' => __('This grading record cannot be deleted because some of its stock has already been used.'),
                ]);
            }

            $grading->delete();
        }, attempts: 3);
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
