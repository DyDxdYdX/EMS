<?php

namespace App\Models;

use Database\Factories\StockAdjustmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['adjustment_date', 'egg_grade_id', 'type', 'quantity', 'reason'])]
class StockAdjustment extends Model
{
    /** @use HasFactory<StockAdjustmentFactory> */
    use HasFactory;

    /** @return BelongsTo<EggGrade, $this> */
    public function eggGrade(): BelongsTo
    {
        return $this->belongsTo(EggGrade::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'adjustment_date' => 'date',
        ];
    }
}
