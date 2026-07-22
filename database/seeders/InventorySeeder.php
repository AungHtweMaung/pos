<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\SaleUnit;
use App\Models\Variant;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    /**
     * A tiny slice of a grocery catalog to exercise the inventory UI end to
     * end (multi-variant product, multi-sale-unit variant, and a variant that
     * is already at its low-stock threshold).
     */
    public function run(): void
    {
        $coke = Product::create([
            'name' => 'Coca-Cola',
            'category' => 'Beverages',
            'tax_rate' => 7.00,
        ]);

        $coke250 = $coke->variants()->create([
            'label' => '250ml',
            'stock_qty' => 48,
            'low_stock_threshold' => 12,
        ]);
        $coke250->saleUnits()->createMany([
            ['label' => 'Single', 'barcode' => 'COKE250-1', 'pack_size' => 1, 'price' => 1.20, 'cost' => 0.60],
            ['label' => '6-Pack', 'barcode' => 'COKE250-6', 'pack_size' => 6, 'price' => 6.50, 'cost' => 3.60],
        ]);

        $coke500 = $coke->variants()->create([
            'label' => '500ml',
            'stock_qty' => 5,
            'low_stock_threshold' => 10,
        ]);
        $coke500->saleUnits()->create([
            'label' => 'Single', 'barcode' => 'COKE500-1', 'pack_size' => 1, 'price' => 2.00, 'cost' => 1.00,
        ]);

        $rice = Product::create([
            'name' => 'Jasmine Rice',
            'category' => 'Pantry',
            'tax_rate' => 0.00,
        ]);

        $rice5kg = $rice->variants()->create([
            'label' => '5kg bag',
            'stock_qty' => 20,
            'low_stock_threshold' => null,
        ]);
        $rice5kg->saleUnits()->create([
            'label' => 'Bag', 'barcode' => 'RICE-5KG', 'pack_size' => 1, 'price' => 15.00, 'cost' => 10.00,
        ]);
    }
}
