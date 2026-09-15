<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    /**
     * A slice of a Myanmar grocery catalogue to exercise the inventory and
     * sales UI end to end. Prices are in Myanmar Kyat (MMK), stored as whole
     * kyat. Includes a multi-variant product, multi-sale-unit variants (packs),
     * a tablet product stocked in individual tablets, and one variant left at
     * its low-stock threshold for the low-stock demo.
     *
     * Commercial tax in Myanmar is commonly 5% on many goods; staples like
     * rice and medicine are set to 0 here.
     */
    public function run(): void
    {
        // --- Coca-Cola: multi-variant, packs, one low-stock variant ---------
        $coke = Product::create([
            'name' => 'Coca-Cola',
            'category' => 'Beverages',
            'tax_rate' => 5.00,
        ]);

        $coke->variants()->create([
            'label' => '330ml Can',
            'stock_qty' => 240,
            'low_stock_threshold' => 48,
        ])->saleUnits()->createMany([
            ['label' => 'Single', 'barcode' => 'COKE-330-1', 'pack_size' => 1, 'price' => 900, 'cost' => 600],
            ['label' => 'Case of 24', 'barcode' => 'COKE-330-24', 'pack_size' => 24, 'price' => 20000, 'cost' => 14400],
        ]);

        $coke->variants()->create([
            'label' => '1.5L Bottle',
            'stock_qty' => 6,               // intentionally low
            'low_stock_threshold' => 12,
        ])->saleUnits()->create([
            'label' => 'Single', 'barcode' => 'COKE-1500-1', 'pack_size' => 1, 'price' => 2500, 'cost' => 1700,
        ]);

        // --- Paracetamol 500mg: stocked in individual tablets ---------------
        $para = Product::create([
            'name' => 'Paracetamol 500mg Tablet',
            'category' => 'Medicine',
            'tax_rate' => 0.00,
        ]);

        $para->variants()->create([
            'label' => 'Tablet',
            'stock_qty' => 5000,            // individual tablets
            'low_stock_threshold' => 500,
        ])->saleUnits()->createMany([
            ['label' => 'Strip of 10', 'barcode' => 'PARA-500-10', 'pack_size' => 10, 'price' => 500, 'cost' => 300],
            ['label' => 'Box of 100', 'barcode' => 'PARA-500-100', 'pack_size' => 100, 'price' => 4500, 'cost' => 3000],
        ]);

        // --- Paw San Rice: bagged staple, no tax ----------------------------
        $rice = Product::create([
            'name' => 'Paw San Rice',
            'category' => 'Rice & Grains',
            'tax_rate' => 0.00,
        ]);

        $rice->variants()->create([
            'label' => '5kg Bag',
            'stock_qty' => 40,
            'low_stock_threshold' => 8,
        ])->saleUnits()->create([
            'label' => 'Bag', 'barcode' => 'RICE-PS-5KG', 'pack_size' => 1, 'price' => 13000, 'cost' => 10500,
        ]);

        // --- Premier 3-in-1 Coffee: sachet + box ----------------------------
        $coffee = Product::create([
            'name' => 'Premier 3-in-1 Coffee',
            'category' => 'Beverages',
            'tax_rate' => 5.00,
        ]);

        $coffee->variants()->create([
            'label' => 'Sachet',
            'stock_qty' => 600,
            'low_stock_threshold' => 60,
        ])->saleUnits()->createMany([
            ['label' => 'Single Sachet', 'barcode' => 'PREM-3IN1-1', 'pack_size' => 1, 'price' => 300, 'cost' => 190],
            ['label' => 'Box of 30', 'barcode' => 'PREM-3IN1-30', 'pack_size' => 30, 'price' => 8000, 'cost' => 5700],
        ]);

        // --- Palm Oil: bottled, taxed ---------------------------------------
        $oil = Product::create([
            'name' => 'Palm Oil',
            'category' => 'Cooking',
            'tax_rate' => 5.00,
        ]);

        $oil->variants()->create([
            'label' => '1L Bottle',
            'stock_qty' => 50,
            'low_stock_threshold' => 10,
        ])->saleUnits()->create([
            'label' => 'Bottle', 'barcode' => 'OIL-PALM-1L', 'pack_size' => 1, 'price' => 4800, 'cost' => 3900,
        ]);

        // --- Chicken Eggs: loose + tray (different base units = variants) ----
        $eggs = Product::create([
            'name' => 'Chicken Eggs',
            'category' => 'Fresh',
            'tax_rate' => 0.00,
        ]);

        $eggs->variants()->create([
            'label' => 'Loose',
            'stock_qty' => 300,             // individual eggs
            'low_stock_threshold' => 60,
        ])->saleUnits()->create([
            'label' => 'Single Egg', 'barcode' => 'EGG-1', 'pack_size' => 1, 'price' => 350, 'cost' => 250,
        ]);

        $eggs->variants()->create([
            'label' => 'Tray of 30',
            'stock_qty' => 20,              // trays
            'low_stock_threshold' => 5,
        ])->saleUnits()->create([
            'label' => 'Tray', 'barcode' => 'EGG-TRAY-30', 'pack_size' => 1, 'price' => 9500, 'cost' => 7200,
        ]);
    }
}
