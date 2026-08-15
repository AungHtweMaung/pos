<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StockAdjustmentRequest;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockAdjustmentController extends Controller
{
    /**
     * Apply a manual stock adjustment (spec §8.2). The audit row and the
     * variants.stock_qty change are wrapped in a transaction so a crash can
     * never leave the audit and the balance out of sync.
     */
    public function store(
        StockAdjustmentRequest $request,
        Product $product,
        Variant $variant
    ): RedirectResponse {
        abort_if($variant->product_id !== $product->id, 404);

        $delta = (int) $request->input('change_qty');

        DB::transaction(function () use ($variant, $request, $delta) {
            $fresh = Variant::whereKey($variant->id)->lockForUpdate()->first();
            $new = $fresh->stock_qty + $delta;

            if ($new < 0) {
                throw ValidationException::withMessages([
                    'change_qty' => "Adjustment would drop stock below zero (current: {$fresh->stock_qty}).",
                ]);
            }

            $fresh->update(['stock_qty' => $new]);

            $fresh->stockAdjustments()->create([
                'change_qty' => $delta,
                'reason' => $request->input('reason'),
                'adjusted_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'Stock adjusted.');
    }
}
