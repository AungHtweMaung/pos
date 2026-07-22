<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Spec §6: the stocked unit. Stock lives here in *base* units regardless
        // of how a sale_unit packages it. Cascading from product is safe since
        // stock_adjustments (below) will restrict variant deletion once history
        // exists.
        Schema::create('variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('label');
            $table->integer('stock_qty')->default(0);
            $table->integer('low_stock_threshold')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variants');
    }
};
