<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Spec §6: each sale line references a sale_unit and freezes the price
        // as it was at the moment of sale. Sale unit deletion is restricted so
        // history stays valid; sale rows cascade so voids don't need to leave
        // orphan lines (deletion for reversal isn't used — status flips).
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('sale_unit_id')
                ->constrained('sale_units')
                ->restrictOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);

            $table->index('sale_id');
            $table->index('sale_unit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
