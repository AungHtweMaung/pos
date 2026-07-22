<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'variant_id',
        'label',
        'barcode',
        'pack_size',
        'price',
        'cost',
    ];

    protected function casts(): array
    {
        return [
            'pack_size' => 'integer',
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }
}
