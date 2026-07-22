<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'tax_rate',
    ];

    protected function casts(): array
    {
        return [
            'tax_rate' => 'decimal:2',
        ];
    }

    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class);
    }

    /**
     * Convenience: reach every sale unit under this product without loading
     * variants explicitly. Useful for the products list where we count units.
     */
    public function saleUnits(): HasManyThrough
    {
        return $this->hasManyThrough(SaleUnit::class, Variant::class);
    }
}
