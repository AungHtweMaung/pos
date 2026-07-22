<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\SaleUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    /**
     * Barcode/name lookup for the POS cart (spec §8.1 step 2).
     * Exact barcode match wins; otherwise fall back to a name-like search
     * across product name, variant label, and sale-unit label.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('q', ''));

        if ($query === '') {
            return response()->json(['results' => []]);
        }

        $exact = SaleUnit::with('variant.product')
            ->where('barcode', $query)
            ->first();

        if ($exact) {
            return response()->json([
                'results' => [$this->format($exact)],
                'matched_barcode' => true,
            ]);
        }

        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $query) . '%';

        $results = SaleUnit::with('variant.product')
            ->where(function ($q) use ($like) {
                $q->where('barcode', 'like', $like)
                    ->orWhere('label', 'like', $like)
                    ->orWhereHas('variant', function ($v) use ($like) {
                        $v->where('label', 'like', $like)
                            ->orWhereHas('product', fn ($p) => $p->where('name', 'like', $like));
                    });
            })
            ->limit(20)
            ->get()
            ->map(fn ($u) => $this->format($u));

        return response()->json([
            'results' => $results,
            'matched_barcode' => false,
        ]);
    }

    private function format(SaleUnit $u): array
    {
        return [
            'sale_unit_id' => $u->id,
            'sale_unit_label' => $u->label,
            'barcode' => $u->barcode,
            'pack_size' => (int) $u->pack_size,
            'price' => (float) $u->price,
            'variant_id' => $u->variant->id,
            'variant_label' => $u->variant->label,
            'stock_qty' => (int) $u->variant->stock_qty,
            'product_id' => $u->variant->product->id,
            'product_name' => $u->variant->product->name,
            'tax_rate' => (float) $u->variant->product->tax_rate,
        ];
    }
}
