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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'grading_date' => 'date',
        ];
    }
}
