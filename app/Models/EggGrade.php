<?php

namespace App\Models;

use Database\Factories\EggGradeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'is_active', 'weight_range', 'sort_order'])]
class EggGrade extends Model
{
    /** @use HasFactory<EggGradeFactory> */
    use HasFactory;

    /** @return HasMany<EggGrading, $this> */
    public function gradings(): HasMany
    {
        return $this->hasMany(EggGrading::class);
    }

    /** @return HasMany<Sale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /** @return HasMany<StockAdjustment, $this> */
    public function stockAdjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class);
    }

    public function hasHistory(): bool
    {
        return $this->gradings()->exists()
            || $this->sales()->exists()
            || $this->stockAdjustments()->exists();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
