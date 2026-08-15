<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    public const STATUS_COMPLETED = 'completed';
    public const STATUS_VOIDED = 'voided';
    public const STATUS_REFUNDED = 'refunded';

    public const PAYMENT_CASH = 'cash';
    public const PAYMENT_QR = 'qr';

    protected $fillable = [
        'cashier_id',
        'subtotal',
        'tax_total',
        'discount_total',
        'grand_total',
        'payment_method',
        'cash_tendered',
        'change_due',
        'qr_reference_note',
        'status',
        'voided_by',
        'voided_reason',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'cash_tendered' => 'decimal:2',
            'change_due' => 'decimal:2',
        ];
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED
            || $this->status === self::STATUS_REFUNDED;
    }
}
