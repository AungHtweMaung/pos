<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Variant;
use Inertia\Inertia;
use Inertia\Response;

class LowStockController extends Controller
{
    /**
     * Spec §8.2: low-stock list based on `low_stock_threshold`. Only variants
     * that opted in (threshold not null) count.
     */
    public function index(): Response
    {
        $variants = Variant::query()
            ->whereNotNull('low_stock_threshold')
            ->whereColumn('stock_qty', '<=', 'low_stock_threshold')
            ->with('product:id,name,category')
            ->orderBy('stock_qty')
            ->get()
            ->map(fn (Variant $v) => [
                'id' => $v->id,
                'label' => $v->label,
                'stock_qty' => $v->stock_qty,
                'low_stock_threshold' => $v->low_stock_threshold,
                'product' => [
                    'id' => $v->product->id,
                    'name' => $v->product->name,
                    'category' => $v->product->category,
                ],
            ]);

        return Inertia::render('Inventory/LowStock', [
            'variants' => $variants,
        ]);
    }
}
