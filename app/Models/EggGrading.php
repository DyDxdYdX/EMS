<?php

namespace App\Models;

use Database\Factories\EggGradingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['grading_date', 'egg_grade_id', 'quantity', 'notes'])]
class EggGrading extends Model
{
    /** @use HasFactory<EggGradingFactory> */
    use HasFactory;

    /** @return BelongsTo<EggGrade, $this> */
    public function eggGrade(): BelongsTo
    {
        return $this->belongsTo(EggGrade::class);
    }

    public static function availableEggQuantity(?self $excluding = null): int
    {
        $producedEggs = (int) Production::query()->sum('total_eggs');
        $damagedEggs = (int) Production::query()->sum('damaged_eggs');
        $gradedEggs = (int) self::query()->sum('quantity');

        $excludedQuantity = $excluding === null ? 0 : $excluding->quantity;

        return $producedEggs - $damagedEggs - $gradedEggs + $excludedQuantity;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'grading_date' => 'date',
        ];
    }
}
