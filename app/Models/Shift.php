<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'cashier_id',
        'opened_at',
        'closed_at',
        'opening_float',
        'expected_cash',
        'counted_cash',
        'difference',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_float' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /** Currently-open shift for a given cashier, if any. */
    public function scopeOpenFor(Builder $query, int $cashierId): Builder
    {
        return $query->where('cashier_id', $cashierId)->whereNull('closed_at');
    }

    /**
     * Expected cash currently in the drawer for this shift (spec §8.4):
     * opening float + net cash sales, counted from when the shift opened up
     * to now (or its close time).
     *
     * A cash sale that is later voided/refunded flips its status away from
     * "completed", so it drops out of this sum automatically — money in then
     * money back out nets to zero. That's why we only sum completed cash
     * sales rather than doing a separate "sales − refunds" subtraction (which
     * would double-count the reversal). Cross-shift voids (a sale rung up in
     * an earlier shift, voided during this one) are out of MVP scope.
     */
    public function expectedCash(): float
    {
        $until = $this->closed_at ?? now();

        $cashSales = (float) Sale::query()
            ->where('cashier_id', $this->cashier_id)
            ->where('payment_method', Sale::PAYMENT_CASH)
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('created_at', [$this->opened_at, $until])
            ->sum('grand_total');

        return round((float) $this->opening_float + $cashSales, 2);
    }
}
