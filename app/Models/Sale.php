<?php

namespace App\Models;

use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sale_date',
    'customer_id',
    'egg_grade_id',
    'unit',
    'quantity',
    'unit_price',
    'total_amount',
    'normalized_egg_quantity',
    'notes',
])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<EggGrade, $this> */
    public function eggGrade(): BelongsTo
    {
        return $this->belongsTo(EggGrade::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'unit_price' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }
}
