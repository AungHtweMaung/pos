<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Spec §6 + §8.4. `opening_float` is added beyond the spec table
        // because the expected-cash formula ("opening float + cash sales −
        // cash refunds") needs it stored per shift. cashier_id uses
        // restrictOnDelete so a user with shift history can only be
        // deactivated, never hard-deleted (spec §4).
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashier_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->decimal('opening_float', 12, 2)->default(0);
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('counted_cash', 12, 2)->nullable();
            $table->decimal('difference', 12, 2)->nullable();
            $table->timestamps();

            // Fast lookup of a cashier's currently-open shift (closed_at null).
            $table->index(['cashier_id', 'closed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
