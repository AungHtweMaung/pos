<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\SaleUnitRequest;
use App\Models\Product;
use App\Models\SaleUnit;
use App\Models\Variant;
use Illuminate\Http\RedirectResponse;

class SaleUnitController extends Controller
{
    public function store(SaleUnitRequest $request, Product $product, Variant $variant): RedirectResponse
    {
        abort_if($variant->product_id !== $product->id, 404);

        $variant->saleUnits()->create($request->validated());

        return back()->with('success', 'Sale unit added.');
    }

    public function update(
        SaleUnitRequest $request,
        Product $product,
        Variant $variant,
        SaleUnit $saleUnit
    ): RedirectResponse {
        $this->assertOwnership($product, $variant, $saleUnit);

        $saleUnit->update($request->validated());

        return back()->with('success', 'Sale unit updated.');
    }

    public function destroy(
        Product $product,
        Variant $variant,
        SaleUnit $saleUnit
    ): RedirectResponse {
        $this->assertOwnership($product, $variant, $saleUnit);

        $saleUnit->delete();

        return back()->with('success', 'Sale unit deleted.');
    }

    protected function assertOwnership(Product $product, Variant $variant, SaleUnit $saleUnit): void
    {
        abort_if(
            $variant->product_id !== $product->id
            || $saleUnit->variant_id !== $variant->id,
            404
        );
    }
}
