<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleUnit;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
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

    /** A sale unit: price 10, cost 4, pack_size 1, on a fresh product. */
    private function saleUnit(string $barcode = 'X-1', float $price = 10, float $cost = 4, int $pack = 1): SaleUnit
    {
        $product = Product::create(['name' => "Product {$barcode}", 'tax_rate' => 0]);
        $variant = $product->variants()->create(['label' => 'std', 'stock_qty' => 1000]);

        return $variant->saleUnits()->create([
            'label' => 'unit',
            'barcode' => $barcode,
            'pack_size' => $pack,
            'price' => $price,
            'cost' => $cost,
        ]);
    }

    /**
     * Create a completed sale of `qty` of one sale unit, with a header that
     * matches the line (no tax, no discount for simplicity unless given).
     */
    private function sale(
        User $cashier,
        SaleUnit $unit,
        int $qty = 1,
        string $method = 'cash',
        string $status = 'completed',
        float $discount = 0,
        float $tax = 0,
    ): Sale {
        $gross = (float) $unit->price * $qty;
        $lineTotal = $gross - $discount;
        $grand = $lineTotal + $tax;

        $sale = Sale::create([
            'cashier_id' => $cashier->id,
            'subtotal' => $gross,
            'tax_total' => $tax,
            'discount_total' => $discount,
            'grand_total' => $grand,
            'payment_method' => $method,
            'status' => $status,
        ]);

        $sale->items()->create([
            'sale_unit_id' => $unit->id,
            'quantity' => $qty,
            'unit_price' => $unit->price,
            'discount' => $discount,
            'line_total' => $lineTotal,
        ]);

        return $sale;
    }

    // ----- Gating ---------------------------------------------------------

    public function test_cashier_cannot_view_reports(): void
    {
        $this->actingAs($this->cashier())->get('/reports')->assertForbidden();
    }

    public function test_admin_can_view_reports(): void
    {
        $this->actingAs($this->admin())->get('/reports')->assertOk();
    }

    // ----- Summary --------------------------------------------------------

    public function test_summary_totals_revenue_and_transactions(): void
    {
        $cashier = $this->cashier();
        $unit = $this->saleUnit(price: 10, cost: 4);

        $this->sale($cashier, $unit, qty: 2); // grand 20
        $this->sale($cashier, $unit, qty: 3); // grand 30

        $props = $this->actingAs($this->admin())
            ->get('/reports?range=today')
            ->viewData('page')['props'];

        $summary = $props['summary'];
        $this->assertEqualsWithDelta(50.0, $summary['revenue'], 0.001);
        $this->assertSame(2, $summary['transactions']);
        $this->assertSame(5, $summary['items_sold']);
        $this->assertEqualsWithDelta(25.0, $summary['average_sale'], 0.001);
    }

    public function test_summary_computes_gross_profit(): void
    {
        $cashier = $this->cashier();
        $unit = $this->saleUnit(price: 10, cost: 4);

        // 5 units sold: revenue 50, cost 20 → profit 30.
        $this->sale($cashier, $unit, qty: 5);

        $summary = $this->actingAs($this->admin())
            ->get('/reports?range=today')
            ->viewData('page')['props']['summary'];

        $this->assertEqualsWithDelta(50.0, $summary['revenue'], 0.001);
        $this->assertEqualsWithDelta(20.0, $summary['cost'], 0.001);
        $this->assertEqualsWithDelta(30.0, $summary['gross_profit'], 0.001);
        $this->assertEqualsWithDelta(60.0, $summary['margin_pct'], 0.1);
    }

    public function test_summary_breaks_down_by_payment_method(): void
    {
        $cashier = $this->cashier();
        $unit = $this->saleUnit(price: 10, cost: 4);

        $this->sale($cashier, $unit, qty: 1, method: 'cash'); // 10
        $this->sale($cashier, $unit, qty: 3, method: 'qr');   // 30

        $methods = $this->actingAs($this->admin())
            ->get('/reports?range=today')
            ->viewData('page')['props']['summary']['methods'];

        $this->assertEqualsWithDelta(10.0, $methods['cash']['total'], 0.001);
        $this->assertEqualsWithDelta(30.0, $methods['qr']['total'], 0.001);
        $this->assertSame(1, $methods['cash']['count']);
    }

    public function test_voided_sales_are_excluded_from_revenue_but_counted_in_void_total(): void
    {
        $cashier = $this->cashier();
        $unit = $this->saleUnit(price: 10, cost: 4);

        $this->sale($cashier, $unit, qty: 1);                       // completed 10
        $this->sale($cashier, $unit, qty: 5, status: 'voided');     // voided 50

        $summary = $this->actingAs($this->admin())
            ->get('/reports?range=today')
            ->viewData('page')['props']['summary'];

        $this->assertEqualsWithDelta(10.0, $summary['revenue'], 0.001);
        $this->assertSame(1, $summary['transactions']);
        $this->assertSame(1, $summary['void_count']);
        $this->assertEqualsWithDelta(50.0, $summary['void_total'], 0.001);
    }

    // ----- Best sellers ---------------------------------------------------

    public function test_best_sellers_aggregate_by_variant_in_base_units(): void
    {
        $cashier = $this->cashier();
        // pack_size 6, so 2 sales of qty 1 each = 12 base units.
        $sixpack = $this->saleUnit('SIX', price: 30, cost: 18, pack: 6);
        $single = $this->saleUnit('ONE', price: 10, cost: 4, pack: 1);

        $this->sale($cashier, $sixpack, qty: 2); // 12 base units, rev 60
        $this->sale($cashier, $single, qty: 3);  // 3 base units, rev 30

        $best = $this->actingAs($this->admin())
            ->get('/reports?range=today')
            ->viewData('page')['props']['bestSellers'];

        $this->assertCount(2, $best);
        // Ordered by revenue desc → sixpack first.
        $this->assertSame(12, $best[0]['base_units']);
        $this->assertEqualsWithDelta(60.0, $best[0]['revenue'], 0.001);
        // profit = 60 − 2*18 = 24
        $this->assertEqualsWithDelta(24.0, $best[0]['profit'], 0.001);
    }

    public function test_best_sellers_excludes_voided_sales(): void
    {
        $cashier = $this->cashier();
        $unit = $this->saleUnit('ONE', price: 10, cost: 4);

        $this->sale($cashier, $unit, qty: 5, status: 'voided');

        $best = $this->actingAs($this->admin())
            ->get('/reports?range=today')
            ->viewData('page')['props']['bestSellers'];

        $this->assertCount(0, $best);
    }

    // ----- Void log -------------------------------------------------------

    public function test_void_log_lists_voided_sales(): void
    {
        $cashier = $this->cashier();
        $unit = $this->saleUnit('ONE', price: 10, cost: 4);

        $sale = $this->sale($cashier, $unit, qty: 2, status: 'voided');
        $sale->update(['voided_reason' => 'wrong item']);

        $voids = $this->actingAs($this->admin())
            ->get('/reports?range=today')
            ->viewData('page')['props']['voids'];

        $this->assertCount(1, $voids);
        $this->assertSame($sale->id, $voids[0]['id']);
        $this->assertSame('wrong item', $voids[0]['reason']);
    }

    // ----- Date filtering -------------------------------------------------

    public function test_date_range_excludes_sales_outside_the_window(): void
    {
        $cashier = $this->cashier();
        $unit = $this->saleUnit(price: 10, cost: 4);

        $today = $this->sale($cashier, $unit, qty: 1); // 10 today

        $old = $this->sale($cashier, $unit, qty: 9);   // 90, but move to last month
        $old->forceFill(['created_at' => now()->subMonth()])->save();

        $summary = $this->actingAs($this->admin())
            ->get('/reports?range=today')
            ->viewData('page')['props']['summary'];

        // Only today's sale counts.
        $this->assertEqualsWithDelta(10.0, $summary['revenue'], 0.001);
        $this->assertSame(1, $summary['transactions']);
    }
}
