<?php

namespace App\Actions\StockAdjustment;

use App\Models\EggGrade;
use App\Models\EggGrading;
use App\Models\Production;
use App\Models\Sale;
use App\Models\StockAdjustment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteStockAdjustment
{
    public function handle(int $adjustmentId): void
    {
        DB::transaction(function () use ($adjustmentId): void {
            $this->lockInventoryLedger();

            $adjustment = StockAdjustment::query()->findOrFail($adjustmentId);
            $grade = EggGrade::query()->findOrFail($adjustment->egg_grade_id);
            $stockAfterDeletion = $grade->stockQuantity() - $this->stockImpact($adjustment->type, $adjustment->quantity);

            if ($stockAfterDeletion < 0) {
                throw ValidationException::withMessages([
                    'deleteAdjustment' => __('This stock addition cannot be deleted because some of its stock has already been used.'),
                ]);
            }

            $adjustment->delete();
        }, attempts: 3);
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
