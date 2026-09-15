<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleUnit;
use App\Models\Shift;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesTest extends TestCase
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

    /**
     * Build a Coke product with a 250ml variant + Single + 6-Pack sale units.
     * Stock defaults to 100 individual bottles.
     */
    private function seedCoke(int $stock = 100, float $taxRate = 0, float $singlePrice = 1.00, float $sixPrice = 5.00): array
    {
        $product = Product::create(['name' => 'Coca-Cola', 'tax_rate' => $taxRate]);
        $variant = $product->variants()->create(['label' => '250ml', 'stock_qty' => $stock]);

        $single = $variant->saleUnits()->create([
            'label' => 'Single', 'barcode' => 'CC-250-1', 'pack_size' => 1,
            'price' => $singlePrice, 'cost' => 0.5,
        ]);
        $sixpack = $variant->saleUnits()->create([
            'label' => '6-Pack', 'barcode' => 'CC-250-6', 'pack_size' => 6,
            'price' => $sixPrice, 'cost' => 3.0,
        ]);

        return compact('product', 'variant', 'single', 'sixpack');
    }

    // ----- Cart access ----------------------------------------------------

    public function test_guest_cannot_open_cart(): void
    {
        $this->get('/pos')->assertRedirect('/login');
    }

    public function test_cashier_and_admin_can_open_cart(): void
    {
        $this->actingAs($this->cashier())->get('/pos')->assertOk();
        $this->actingAs($this->admin())->get('/pos')->assertOk();
    }

    // ----- Lookup ---------------------------------------------------------

    public function test_lookup_matches_exact_barcode(): void
    {
        $seed = $this->seedCoke();
        $this->actingAs($this->cashier())
            ->getJson('/pos/lookup?q=CC-250-1')
            ->assertOk()
            ->assertJson(['matched_barcode' => true])
            ->assertJsonPath('results.0.sale_unit_id', $seed['single']->id);
    }

    public function test_lookup_falls_back_to_name(): void
    {
        $this->seedCoke();
        $this->actingAs($this->cashier())
            ->getJson('/pos/lookup?q=Coca')
            ->assertOk()
            ->assertJson(['matched_barcode' => false])
            ->assertJsonCount(2, 'results');
    }

    // ----- Cash sale ------------------------------------------------------

    public function test_cashier_can_complete_a_cash_sale_and_stock_decrements(): void
    {
        $seed = $this->seedCoke(stock: 100, singlePrice: 2.00);

        $cashier = $this->cashier();
        $this->actingAs($cashier)
            ->post('/pos', [
                'items' => [
                    ['sale_unit_id' => $seed['single']->id, 'quantity' => 3, 'discount' => 0],
                ],
                'payment_method' => 'cash',
                'cash_tendered' => 10,
            ])
            ->assertRedirect();

        $sale = Sale::firstOrFail();
        $this->assertSame($cashier->id, $sale->cashier_id);
        $this->assertSame('cash', $sale->payment_method);
        $this->assertSame('completed', $sale->status);
        $this->assertEquals(6.00, (float) $sale->grand_total);
        $this->assertEquals(10.00, (float) $sale->cash_tendered);
        $this->assertEquals(4.00, (float) $sale->change_due);

        $this->assertSame(97, $seed['variant']->fresh()->stock_qty);
    }

    public function test_six_pack_deducts_pack_size_times_qty(): void
    {
        $seed = $this->seedCoke(stock: 100);

        $this->actingAs($this->cashier())
            ->post('/pos', [
                'items' => [
                    ['sale_unit_id' => $seed['sixpack']->id, 'quantity' => 2, 'discount' => 0],
                ],
                'payment_method' => 'cash',
                'cash_tendered' => 100,
            ])
            ->assertRedirect();

        // 2 × 6 = 12 bottles deducted.
        $this->assertSame(88, $seed['variant']->fresh()->stock_qty);
    }

    public function test_insufficient_stock_rejects_the_sale(): void
    {
        $seed = $this->seedCoke(stock: 2);

        $this->actingAs($this->cashier())
            ->from('/pos')
            ->post('/pos', [
                'items' => [
                    ['sale_unit_id' => $seed['sixpack']->id, 'quantity' => 1, 'discount' => 0],
                ],
                'payment_method' => 'cash',
                'cash_tendered' => 20,
            ])
            ->assertRedirect('/pos')
            ->assertSessionHasErrors('items');

        $this->assertSame(2, $seed['variant']->fresh()->stock_qty);
        $this->assertSame(0, Sale::count());
    }

    public function test_cash_tendered_must_cover_grand_total(): void
    {
        $seed = $this->seedCoke(singlePrice: 5);

        $this->actingAs($this->cashier())
            ->from('/pos')
            ->post('/pos', [
                'items' => [
                    ['sale_unit_id' => $seed['single']->id, 'quantity' => 2, 'discount' => 0],
                ],
                'payment_method' => 'cash',
                'cash_tendered' => 5,
            ])
            ->assertSessionHasErrors('cash_tendered');

        $this->assertSame(0, Sale::count());
    }

    // ----- Discounts + tax ------------------------------------------------

    public function test_per_line_discount_and_tax_are_applied(): void
    {
        $seed = $this->seedCoke(taxRate: 10, singlePrice: 10);
        // 2 × 10 = 20 gross, discount 4 → net 16, tax 10% on 16 = 1.60
        // grand = 20 − 4 + 1.60 = 17.60

        $this->actingAs($this->cashier())
            ->post('/pos', [
                'items' => [
                    ['sale_unit_id' => $seed['single']->id, 'quantity' => 2, 'discount' => 4],
                ],
                'payment_method' => 'cash',
                'cash_tendered' => 20,
            ])
            ->assertRedirect();

        $sale = Sale::firstOrFail();
        $this->assertEquals(20.00, (float) $sale->subtotal);
        $this->assertEquals(4.00, (float) $sale->discount_total);
        $this->assertEquals(1.60, (float) $sale->tax_total);
        $this->assertEquals(17.60, (float) $sale->grand_total);
    }

    public function test_discount_larger_than_line_is_rejected(): void
    {
        $seed = $this->seedCoke(singlePrice: 2);

        $this->actingAs($this->cashier())
            ->from('/pos')
            ->post('/pos', [
                'items' => [
                    ['sale_unit_id' => $seed['single']->id, 'quantity' => 1, 'discount' => 5],
                ],
                'payment_method' => 'cash',
                'cash_tendered' => 20,
            ])
            ->assertSessionHasErrors('items');
    }

    // ----- QR + card ------------------------------------------------------

    public function test_qr_sale_requires_a_reference_note(): void
    {
        $seed = $this->seedCoke();
        $this->actingAs($this->cashier())
            ->from('/pos')
            ->post('/pos', [
                'items' => [
                    ['sale_unit_id' => $seed['single']->id, 'quantity' => 1, 'discount' => 0],
                ],
                'payment_method' => 'qr',
            ])
            ->assertSessionHasErrors('qr_reference_note');
    }

    public function test_qr_sale_captures_reference_note(): void
    {
        $seed = $this->seedCoke();
        $this->actingAs($this->cashier())
            ->post('/pos', [
                'items' => [
                    ['sale_unit_id' => $seed['single']->id, 'quantity' => 1, 'discount' => 0],
                ],
                'payment_method' => 'qr',
                'qr_reference_note' => 'POS-A1B2',
            ])
            ->assertRedirect();

        $sale = Sale::firstOrFail();
        $this->assertSame('qr', $sale->payment_method);
        $this->assertSame('POS-A1B2', $sale->qr_reference_note);
        $this->assertNull($sale->cash_tendered);
    }

    public function test_card_payment_method_is_rejected(): void
    {
        // Only cash and QR are supported (no card reader).
        $seed = $this->seedCoke();
        $this->actingAs($this->cashier())
            ->from('/pos')
            ->post('/pos', [
                'items' => [
                    ['sale_unit_id' => $seed['single']->id, 'quantity' => 1, 'discount' => 0],
                ],
                'payment_method' => 'card',
            ])
            ->assertSessionHasErrors('payment_method');

        $this->assertSame(0, Sale::count());
    }

    // ----- Validation -----------------------------------------------------

    public function test_empty_cart_is_rejected(): void
    {
        $this->actingAs($this->cashier())
            ->from('/pos')
            ->post('/pos', [
                'items' => [],
                'payment_method' => 'cash',
                'cash_tendered' => 0,
            ])
            ->assertSessionHasErrors('items');
    }

    // ----- Void / refund --------------------------------------------------

    public function test_cashier_cannot_void_another_cashiers_sale(): void
    {
        $seed = $this->seedCoke();
        $owner = $this->cashier();
        $sale = $this->rungUpSale($seed['single'], as: $owner);

        // A different cashier, even with their own open shift, can't void it.
        $intruder = $this->cashier();
        Shift::create([
            'cashier_id' => $intruder->id,
            'opened_at' => now()->subMinute(),
            'opening_float' => 0,
        ]);

        $this->actingAs($intruder)
            ->post("/sales/{$sale->id}/void", ['reason' => 'wrong item'])
            ->assertForbidden();

        $this->assertSame('completed', $sale->fresh()->status);
    }

    public function test_cashier_can_void_own_sale_during_open_shift(): void
    {
        $seed = $this->seedCoke(stock: 100);
        $cashier = $this->cashier();

        Shift::create([
            'cashier_id' => $cashier->id,
            'opened_at' => now()->subMinutes(5),
            'opening_float' => 0,
        ]);

        $sale = $this->rungUpSale($seed['single'], quantity: 4, as: $cashier);
        $this->assertSame(96, $seed['variant']->fresh()->stock_qty);

        $this->actingAs($cashier)
            ->post("/sales/{$sale->id}/void", ['reason' => 'wrong item scanned'])
            ->assertRedirect();

        $sale->refresh();
        $this->assertSame('voided', $sale->status);
        $this->assertSame($cashier->id, $sale->voided_by);
        $this->assertSame(100, $seed['variant']->fresh()->stock_qty);
    }

    public function test_cashier_cannot_void_own_sale_without_an_open_shift(): void
    {
        $seed = $this->seedCoke();
        $cashier = $this->cashier();
        $sale = $this->rungUpSale($seed['single'], as: $cashier);

        // No open shift → cannot self-void.
        $this->actingAs($cashier)
            ->post("/sales/{$sale->id}/void", ['reason' => 'oops'])
            ->assertForbidden();

        $this->assertSame('completed', $sale->fresh()->status);
    }

    public function test_cashier_cannot_void_sale_rung_before_current_shift(): void
    {
        $seed = $this->seedCoke();
        $cashier = $this->cashier();

        $sale = $this->rungUpSale($seed['single'], as: $cashier);
        // The sale happened an hour ago, in an earlier (now closed) shift.
        $sale->forceFill(['created_at' => now()->subHour()])->save();

        // The current shift only opened just now.
        Shift::create([
            'cashier_id' => $cashier->id,
            'opened_at' => now(),
            'opening_float' => 0,
        ]);

        $this->actingAs($cashier)
            ->post("/sales/{$sale->id}/void", ['reason' => 'oops'])
            ->assertForbidden();

        $this->assertSame('completed', $sale->fresh()->status);
    }

    public function test_my_sales_lists_only_the_current_cashiers_sales(): void
    {
        $seed = $this->seedCoke();
        $me = $this->cashier();
        $other = $this->cashier();

        $mine = $this->rungUpSale($seed['single'], as: $me);
        $theirs = $this->rungUpSale($seed['single'], as: $other);

        $data = $this->actingAs($me)->get('/my-sales')
            ->viewData('page')['props']['sales']['data'];
        $ids = collect($data)->pluck('id')->all();

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    public function test_admin_can_void_a_sale_and_stock_is_restored(): void
    {
        $seed = $this->seedCoke(stock: 100);
        $sale = $this->rungUpSale($seed['single'], quantity: 4);

        // Stock is now 96 after the sale.
        $this->assertSame(96, $seed['variant']->fresh()->stock_qty);

        $admin = $this->admin();
        $this->actingAs($admin)
            ->post("/sales/{$sale->id}/void", ['reason' => 'customer changed mind'])
            ->assertRedirect();

        $sale->refresh();
        $this->assertSame('voided', $sale->status);
        $this->assertSame($admin->id, $sale->voided_by);
        $this->assertSame('customer changed mind', $sale->voided_reason);
        $this->assertSame(100, $seed['variant']->fresh()->stock_qty);
    }

    public function test_void_requires_a_reason(): void
    {
        $seed = $this->seedCoke();
        $sale = $this->rungUpSale($seed['single']);

        $this->actingAs($this->admin())
            ->from("/sales/{$sale->id}")
            ->post("/sales/{$sale->id}/void", [])
            ->assertSessionHasErrors('reason');
    }

    public function test_voiding_an_already_voided_sale_is_a_noop(): void
    {
        $seed = $this->seedCoke();
        $sale = $this->rungUpSale($seed['single']);

        $admin = $this->admin();
        $this->actingAs($admin)
            ->post("/sales/{$sale->id}/void", ['reason' => 'first']);

        $stockAfterFirstVoid = $seed['variant']->fresh()->stock_qty;

        $this->actingAs($admin)
            ->post("/sales/{$sale->id}/void", ['reason' => 'second'])
            ->assertSessionHas('error');

        $this->assertSame($stockAfterFirstVoid, $seed['variant']->fresh()->stock_qty);
    }

    // ----- History gating -------------------------------------------------

    public function test_cashier_cannot_view_sales_history(): void
    {
        $this->actingAs($this->cashier())->get('/sales')->assertForbidden();
    }

    public function test_admin_can_view_sales_history(): void
    {
        $this->actingAs($this->admin())->get('/sales')->assertOk();
    }

    public function test_cashier_can_view_own_sale_but_not_others(): void
    {
        $seed = $this->seedCoke();
        $cashierA = $this->cashier();
        $cashierB = $this->cashier();

        $saleA = $this->rungUpSale($seed['single'], as: $cashierA);

        $this->actingAs($cashierA)->get("/sales/{$saleA->id}")->assertOk();
        $this->actingAs($cashierB)->get("/sales/{$saleA->id}")->assertForbidden();
        $this->actingAs($this->admin())->get("/sales/{$saleA->id}")->assertOk();
    }

    public function test_receipt_page_renders(): void
    {
        $seed = $this->seedCoke();
        $cashier = $this->cashier();
        $sale = $this->rungUpSale($seed['single'], as: $cashier);
        $this->actingAs($cashier)
            ->get("/sales/{$sale->id}/receipt")
            ->assertOk();
    }

    // ----- Helpers --------------------------------------------------------

    private function rungUpSale(SaleUnit $saleUnit, int $quantity = 1, ?User $as = null): Sale
    {
        $user = $as ?? $this->cashier();
        $this->actingAs($user)->post('/pos', [
            'items' => [
                ['sale_unit_id' => $saleUnit->id, 'quantity' => $quantity, 'discount' => 0],
            ],
            'payment_method' => 'cash',
            'cash_tendered' => 1000,
        ]);
        return Sale::latest('id')->firstOrFail();
    }
}
