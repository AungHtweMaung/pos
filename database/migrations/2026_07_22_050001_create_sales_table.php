<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Spec §6: header row for every completed / voided / refunded sale.
        // cashier_id + voided_by both use restrictOnDelete so a user with
        // sales history can only be deactivated (spec §4), never hard-deleted.
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashier_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_total', 12, 2);
            $table->decimal('discount_total', 12, 2);
            $table->decimal('grand_total', 12, 2);
            $table->enum('payment_method', ['cash', 'qr']);
            $table->decimal('cash_tendered', 12, 2)->nullable();
            $table->decimal('change_due', 12, 2)->nullable();
            $table->string('qr_reference_note')->nullable();
            $table->enum('status', ['completed', 'voided', 'refunded'])->default('completed');
            $table->foreignId('voided_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('voided_reason')->nullable();
            $table->timestamps();

            $table->index(['cashier_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
