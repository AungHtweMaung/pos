<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Spec §6: the sellable/scannable unit. `pack_size` is the number of
        // base units consumed per sale (1 for a single bottle, 6 for a 6-pack).
        Schema::create('sale_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('label');
            $table->string('barcode')->unique();
            $table->integer('pack_size')->default(1);
            $table->decimal('price', 10, 2);
            $table->decimal('cost', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_units');
    }
};
