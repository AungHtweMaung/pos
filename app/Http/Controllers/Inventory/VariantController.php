<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\VariantRequest;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Http\RedirectResponse;

class VariantController extends Controller
{
    public function store(VariantRequest $request, Product $product): RedirectResponse
    {
        $product->variants()->create($request->validated());

        return back()->with('success', 'Variant added.');
    }

    public function update(VariantRequest $request, Product $product, Variant $variant): RedirectResponse
    {
        $this->assertVariantOwnedBy($product, $variant);

        // stock_qty is prohibited on PUT/PATCH by the request rules — updates
        // to stock must go through the audited stock-adjustment endpoint.
        $variant->update($request->validated());

        return back()->with('success', 'Variant updated.');
    }

    public function destroy(Product $product, Variant $variant): RedirectResponse
    {
        $this->assertVariantOwnedBy($product, $variant);

        try {
            $variant->delete();
        } catch (\Throwable $e) {
            return back()->with(
                'error',
                'Cannot delete: this variant has stock adjustment history.'
            );
        }

        return back()->with('success', 'Variant deleted.');
    }

    /**
     * Guard against a malicious PUT/DELETE with a mismatched {product}/{variant}
     * pair in the URL. Route-model-binding validates each individually.
     */
    protected function assertVariantOwnedBy(Product $product, Variant $variant): void
    {
        abort_if($variant->product_id !== $product->id, 404);
    }
}
