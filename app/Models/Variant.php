<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Variant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'label',
        'stock_qty',
        'low_stock_threshold',
    ];

    protected function casts(): array
    {
        return [
            'stock_qty' => 'integer',
            'low_stock_threshold' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function saleUnits(): HasMany
    {
        return $this->hasMany(SaleUnit::class);
    }

    public function stockAdjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class);
    }

    /**
     * A variant is low-stock only if it opted in with a threshold — a null
     * threshold means "don't alert on this SKU" (spec §6).
     */
    public function isLowStock(): bool
    {
        return $this->low_stock_threshold !== null
            && $this->stock_qty <= $this->low_stock_threshold;
    }
}
