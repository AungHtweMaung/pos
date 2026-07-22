<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ProductRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('q', ''));

        $products = Product::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhereHas('variants.saleUnits', function ($su) use ($search) {
                            $su->where('barcode', 'like', "%{$search}%");
                        });
                });
            })
            ->withCount('variants')
            ->with(['variants' => function ($q) {
                $q->select('id', 'product_id', 'stock_qty', 'low_stock_threshold');
            }])
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        // Flatten a small summary per row so the frontend doesn't have to
        // re-derive stock/low-stock from the nested collections.
        $products->getCollection()->transform(function (Product $p) {
            $totalStock = $p->variants->sum('stock_qty');
            $lowStock = $p->variants->contains(fn ($v) => $v->isLowStock());

            return [
                'id' => $p->id,
                'name' => $p->name,
                'category' => $p->category,
                'tax_rate' => (float) $p->tax_rate,
                'variants_count' => $p->variants_count,
                'total_stock' => $totalStock,
                'has_low_stock' => $lowStock,
            ];
        });

        return Inertia::render('Inventory/Products/Index', [
            'products' => $products,
            'filters' => ['q' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Inventory/Products/Create');
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = Product::create($request->validated());

        return redirect()
            ->route('inventory.products.edit', $product)
            ->with('success', "Product “{$product->name}” created.");
    }

    public function edit(Product $product): Response
    {
        $product->load([
            'variants' => fn ($q) => $q->orderBy('label'),
            'variants.saleUnits' => fn ($q) => $q->orderBy('label'),
            'variants.stockAdjustments' => fn ($q) => $q->latest()->limit(20),
            'variants.stockAdjustments.adjuster:id,name',
        ]);

        return Inertia::render('Inventory/Products/Edit', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category,
                'tax_rate' => (float) $product->tax_rate,
                'variants' => $product->variants->map(fn ($v) => [
                    'id' => $v->id,
                    'label' => $v->label,
                    'stock_qty' => $v->stock_qty,
                    'low_stock_threshold' => $v->low_stock_threshold,
                    'is_low_stock' => $v->isLowStock(),
                    'sale_units' => $v->saleUnits->map(fn ($u) => [
                        'id' => $u->id,
                        'label' => $u->label,
                        'barcode' => $u->barcode,
                        'pack_size' => $u->pack_size,
                        'price' => (float) $u->price,
                        'cost' => (float) $u->cost,
                    ]),
                    'recent_adjustments' => $v->stockAdjustments->map(fn ($a) => [
                        'id' => $a->id,
                        'change_qty' => $a->change_qty,
                        'reason' => $a->reason,
                        'by' => $a->adjuster?->name,
                        'at' => $a->created_at?->toDayDateTimeString(),
                    ]),
                ]),
            ],
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return back()->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        try {
            DB::transaction(fn () => $product->delete());
        } catch (\Throwable $e) {
            // A restrictOnDelete on stock_adjustments will surface here if any
            // variant has audit history.
            return back()->with(
                'error',
                'Cannot delete: one or more variants have stock adjustment history.'
            );
        }

        return redirect()
            ->route('inventory.products.index')
            ->with('success', "Product “{$product->name}” deleted.");
    }
}
