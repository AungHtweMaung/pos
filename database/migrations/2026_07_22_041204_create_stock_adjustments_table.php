<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Spec §6: an audit log of every manual stock change (restock, damage,
        // correction). Restrict deletion of the variant / user so history
        // stays intact.
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')
                ->constrained()
                ->restrictOnDelete();
            $table->integer('change_qty');
            $table->string('reason');
            $table->foreignId('adjusted_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();

            $table->index(['variant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
