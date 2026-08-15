<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\SaleUnit;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function cashier(): User
    {
        return User::factory()->create(['role' => UserRole::Cashier]);
    }

    // ----- Role gating ----------------------------------------------------

    public function test_cashier_cannot_see_inventory(): void
    {
        $this->actingAs($this->cashier())
            ->get('/inventory/products')
            ->assertForbidden();
    }

    public function test_cashier_cannot_create_products(): void
    {
        $this->actingAs($this->cashier())
            ->post('/inventory/products', ['name' => 'X', 'tax_rate' => 0])
            ->assertForbidden();
    }

    public function test_cashier_cannot_adjust_stock(): void
    {
        $product = Product::create(['name' => 'p', 'tax_rate' => 0]);
        $variant = $product->variants()->create(['label' => 'v', 'stock_qty' => 10]);

        $this->actingAs($this->cashier())
            ->post("/inventory/products/{$product->id}/variants/{$variant->id}/stock-adjustments", [
                'change_qty' => 5,
                'reason' => 'Restock',
            ])
            ->assertForbidden();
    }

    // ----- Product CRUD ---------------------------------------------------

    public function test_admin_can_create_a_product(): void
    {
        $this->actingAs($this->admin())
            ->post('/inventory/products', [
                'name' => 'Rice',
                'category' => 'Pantry',
                'tax_rate' => 5,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('products', ['name' => 'Rice', 'category' => 'Pantry']);
    }

    public function test_admin_can_update_a_product(): void
    {
        $product = Product::create(['name' => 'Old', 'tax_rate' => 0]);

        $this->actingAs($this->admin())
            ->put("/inventory/products/{$product->id}", [
                'name' => 'New',
                'category' => 'Snacks',
                'tax_rate' => 7,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'New', 'category' => 'Snacks']);
    }

    public function test_admin_can_delete_a_product_with_no_history(): void
    {
        $product = Product::create(['name' => 'x', 'tax_rate' => 0]);

        $this->actingAs($this->admin())
            ->delete("/inventory/products/{$product->id}")
            ->assertRedirect('/inventory/products');

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    // ----- Variant & sale unit CRUD --------------------------------------

    public function test_admin_can_add_variant_and_sale_unit(): void
    {
        $product = Product::create(['name' => 'Coke', 'tax_rate' => 7]);

        $this->actingAs($this->admin())
            ->post("/inventory/products/{$product->id}/variants", [
                'label' => '250ml',
                'stock_qty' => 24,
                'low_stock_threshold' => 6,
            ])
            ->assertRedirect();

        $variant = $product->variants()->firstOrFail();

        $this->actingAs($this->admin())
            ->post("/inventory/products/{$product->id}/variants/{$variant->id}/sale-units", [
                'label' => 'Single',
                'barcode' => 'ABC-1',
                'pack_size' => 1,
                'price' => 1.5,
                'cost' => 0.7,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sale_units', ['barcode' => 'ABC-1', 'variant_id' => $variant->id]);
    }

    public function test_sale_unit_barcode_must_be_unique(): void
    {
        $product = Product::create(['name' => 'x', 'tax_rate' => 0]);
        $v = $product->variants()->create(['label' => 'v', 'stock_qty' => 0]);
        $v->saleUnits()->create([
            'label' => 'one', 'barcode' => 'DUP', 'pack_size' => 1, 'price' => 1, 'cost' => 0,
        ]);

        $this->actingAs($this->admin())
            ->post("/inventory/products/{$product->id}/variants/{$v->id}/sale-units", [
                'label' => 'two',
                'barcode' => 'DUP',
                'pack_size' => 1,
                'price' => 2,
                'cost' => 0,
            ])
            ->assertSessionHasErrors('barcode');
    }

    public function test_variant_stock_cannot_be_edited_directly(): void
    {
        $product = Product::create(['name' => 'x', 'tax_rate' => 0]);
        $v = $product->variants()->create(['label' => 'v', 'stock_qty' => 10]);

        $this->actingAs($this->admin())
            ->put("/inventory/products/{$product->id}/variants/{$v->id}", [
                'label' => 'v2',
                'stock_qty' => 999, // prohibited on update
            ])
            ->assertSessionHasErrors('stock_qty');

        $this->assertSame(10, $v->fresh()->stock_qty);
    }

    // ----- Stock adjustments ----------------------------------------------

    public function test_admin_can_apply_positive_stock_adjustment(): void
    {
        $admin = $this->admin();
        $product = Product::create(['name' => 'x', 'tax_rate' => 0]);
        $v = $product->variants()->create(['label' => 'v', 'stock_qty' => 10]);

        $this->actingAs($admin)
            ->post("/inventory/products/{$product->id}/variants/{$v->id}/stock-adjustments", [
                'change_qty' => 15,
                'reason' => 'Restock',
            ])
            ->assertRedirect();

        $this->assertSame(25, $v->fresh()->stock_qty);
        $this->assertDatabaseHas('stock_adjustments', [
            'variant_id' => $v->id,
            'change_qty' => 15,
            'adjusted_by' => $admin->id,
        ]);
    }

    public function test_admin_can_apply_negative_stock_adjustment(): void
    {
        $product = Product::create(['name' => 'x', 'tax_rate' => 0]);
        $v = $product->variants()->create(['label' => 'v', 'stock_qty' => 10]);

        $this->actingAs($this->admin())
            ->post("/inventory/products/{$product->id}/variants/{$v->id}/stock-adjustments", [
                'change_qty' => -3,
                'reason' => 'Damage',
            ])
            ->assertRedirect();

        $this->assertSame(7, $v->fresh()->stock_qty);
    }

    public function test_zero_stock_adjustment_is_rejected(): void
    {
        $product = Product::create(['name' => 'x', 'tax_rate' => 0]);
        $v = $product->variants()->create(['label' => 'v', 'stock_qty' => 5]);

        $this->actingAs($this->admin())
            ->post("/inventory/products/{$product->id}/variants/{$v->id}/stock-adjustments", [
                'change_qty' => 0,
                'reason' => 'noop',
            ])
            ->assertSessionHasErrors('change_qty');
    }

    public function test_negative_adjustment_cannot_drive_stock_below_zero(): void
    {
        $product = Product::create(['name' => 'x', 'tax_rate' => 0]);
        $v = $product->variants()->create(['label' => 'v', 'stock_qty' => 2]);

        $this->actingAs($this->admin())
            ->post("/inventory/products/{$product->id}/variants/{$v->id}/stock-adjustments", [
                'change_qty' => -10,
                'reason' => 'Damage',
            ])
            ->assertSessionHasErrors('change_qty');

        $this->assertSame(2, $v->fresh()->stock_qty);
        $this->assertDatabaseMissing('stock_adjustments', ['variant_id' => $v->id]);
    }

    public function test_deleting_variant_with_adjustment_history_is_prevented(): void
    {
        $admin = $this->admin();
        $product = Product::create(['name' => 'x', 'tax_rate' => 0]);
        $v = $product->variants()->create(['label' => 'v', 'stock_qty' => 5]);

        StockAdjustment::create([
            'variant_id' => $v->id,
            'change_qty' => 1,
            'reason' => 'r',
            'adjusted_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete("/inventory/products/{$product->id}/variants/{$v->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('variants', ['id' => $v->id]);
    }

    // ----- Low stock listing ---------------------------------------------

    public function test_low_stock_list_only_includes_opted_in_variants_at_or_below_threshold(): void
    {
        $p = Product::create(['name' => 'x', 'tax_rate' => 0]);
        // low (at threshold)
        $low = $p->variants()->create(['label' => 'low', 'stock_qty' => 3, 'low_stock_threshold' => 3]);
        // healthy (above threshold)
        $ok = $p->variants()->create(['label' => 'ok', 'stock_qty' => 20, 'low_stock_threshold' => 5]);
        // opted-out (null threshold)
        $unwatched = $p->variants()->create(['label' => 'noalert', 'stock_qty' => 0, 'low_stock_threshold' => null]);

        $response = $this->actingAs($this->admin())->get('/inventory/low-stock');
        $response->assertOk();

        $ids = collect($response->viewData('page')['props']['variants'])->pluck('id')->all();
        $this->assertContains($low->id, $ids);
        $this->assertNotContains($ok->id, $ids);
        $this->assertNotContains($unwatched->id, $ids);
    }
}
