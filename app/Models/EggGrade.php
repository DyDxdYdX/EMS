<?php

namespace App\Models;

use Database\Factories\EggGradeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'is_active', 'weight_range', 'sort_order', 'price_per_egg', 'price_per_tray'])]
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

    public function stockQuantity(): int
    {
        $gradedEggs = (int) $this->gradings()->sum('quantity');
        $soldEggs = (int) $this->sales()->sum('normalized_egg_quantity');
        $addedEggs = (int) $this->stockAdjustments()->where('type', 'add')->sum('quantity');
        $removedEggs = (int) $this->stockAdjustments()->where('type', 'remove')->sum('quantity');

        return $gradedEggs + $addedEggs - $soldEggs - $removedEggs;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'price_per_egg' => 'decimal:2',
            'price_per_tray' => 'decimal:2',
        ];
    }
}
