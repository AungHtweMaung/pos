<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftTest extends TestCase
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

    /** Create a completed cash sale attributed to a cashier. */
    private function cashSale(User $cashier, float $total, ?string $method = 'cash', string $status = 'completed'): Sale
    {
        return Sale::create([
            'cashier_id' => $cashier->id,
            'subtotal' => $total,
            'tax_total' => 0,
            'discount_total' => 0,
            'grand_total' => $total,
            'payment_method' => $method,
            'status' => $status,
        ]);
    }

    // ----- Access ---------------------------------------------------------

    public function test_guest_is_redirected(): void
    {
        $this->get('/shift')->assertRedirect('/login');
    }

    public function test_cashier_can_open_the_shift_page(): void
    {
        $this->actingAs($this->cashier())->get('/shift')->assertOk();
    }

    // ----- Open -----------------------------------------------------------

    public function test_cashier_can_open_a_shift(): void
    {
        $cashier = $this->cashier();

        $this->actingAs($cashier)
            ->post('/shift/open', ['opening_float' => 50])
            ->assertRedirect();

        $this->assertDatabaseHas('shifts', [
            'cashier_id' => $cashier->id,
            'opening_float' => 50,
            'closed_at' => null,
        ]);
    }

    public function test_cannot_open_two_shifts_at_once(): void
    {
        $cashier = $this->cashier();
        Shift::create([
            'cashier_id' => $cashier->id,
            'opened_at' => now(),
            'opening_float' => 10,
        ]);

        $this->actingAs($cashier)
            ->post('/shift/open', ['opening_float' => 20])
            ->assertSessionHas('error');

        $this->assertSame(1, Shift::where('cashier_id', $cashier->id)->count());
    }

    public function test_opening_float_is_required(): void
    {
        $this->actingAs($this->cashier())
            ->from('/shift')
            ->post('/shift/open', [])
            ->assertSessionHasErrors('opening_float');
    }

    // ----- Expected cash --------------------------------------------------

    public function test_expected_cash_sums_opening_float_plus_cash_sales(): void
    {
        $cashier = $this->cashier();
        $shift = Shift::create([
            'cashier_id' => $cashier->id,
            'opened_at' => now()->subHour(),
            'opening_float' => 100,
        ]);

        $this->cashSale($cashier, 30);
        $this->cashSale($cashier, 20);
        // Card sale — must NOT count toward drawer.
        $this->cashSale($cashier, 999, method: 'card');
        // QR sale — must NOT count.
        $this->cashSale($cashier, 500, method: 'qr');

        // 100 float + 30 + 20 = 150
        $this->assertEqualsWithDelta(150.0, $shift->fresh()->expectedCash(), 0.001);
    }

    public function test_voided_cash_sale_drops_out_of_expected(): void
    {
        $cashier = $this->cashier();
        $shift = Shift::create([
            'cashier_id' => $cashier->id,
            'opened_at' => now()->subHour(),
            'opening_float' => 0,
        ]);

        $this->cashSale($cashier, 40);
        $this->cashSale($cashier, 10, status: 'voided');

        // Only the completed 40 counts.
        $this->assertEqualsWithDelta(40.0, $shift->fresh()->expectedCash(), 0.001);
    }

    public function test_expected_cash_ignores_other_cashiers(): void
    {
        $me = $this->cashier();
        $other = $this->cashier();
        $shift = Shift::create([
            'cashier_id' => $me->id,
            'opened_at' => now()->subHour(),
            'opening_float' => 0,
        ]);

        $this->cashSale($me, 25);
        $this->cashSale($other, 999);

        $this->assertEqualsWithDelta(25.0, $shift->fresh()->expectedCash(), 0.001);
    }

    public function test_sales_before_shift_open_are_excluded(): void
    {
        $cashier = $this->cashier();

        $old = $this->cashSale($cashier, 70);
        $old->forceFill(['created_at' => now()->subDay()])->save();

        $shift = Shift::create([
            'cashier_id' => $cashier->id,
            'opened_at' => now()->subMinutes(5),
            'opening_float' => 0,
        ]);

        $this->cashSale($cashier, 15);

        // Only the sale made after opening counts.
        $this->assertEqualsWithDelta(15.0, $shift->fresh()->expectedCash(), 0.001);
    }

    // ----- Close ----------------------------------------------------------

    public function test_closing_records_expected_counted_and_difference(): void
    {
        $cashier = $this->cashier();
        $shift = Shift::create([
            'cashier_id' => $cashier->id,
            'opened_at' => now()->subHour(),
            'opening_float' => 100,
        ]);
        $this->cashSale($cashier, 50); // expected = 150

        $this->actingAs($cashier)
            ->post("/shift/{$shift->id}/close", ['counted_cash' => 148])
            ->assertRedirect();

        $shift->refresh();
        $this->assertEqualsWithDelta(150.0, (float) $shift->expected_cash, 0.001);
        $this->assertEqualsWithDelta(148.0, (float) $shift->counted_cash, 0.001);
        $this->assertEqualsWithDelta(-2.0, (float) $shift->difference, 0.001);
        $this->assertNotNull($shift->closed_at);
    }

    public function test_counted_cash_is_required_to_close(): void
    {
        $cashier = $this->cashier();
        $shift = Shift::create([
            'cashier_id' => $cashier->id,
            'opened_at' => now(),
            'opening_float' => 0,
        ]);

        $this->actingAs($cashier)
            ->from('/shift')
            ->post("/shift/{$shift->id}/close", [])
            ->assertSessionHasErrors('counted_cash');
    }

    public function test_cashier_cannot_close_another_cashiers_shift(): void
    {
        $owner = $this->cashier();
        $intruder = $this->cashier();
        $shift = Shift::create([
            'cashier_id' => $owner->id,
            'opened_at' => now(),
            'opening_float' => 0,
        ]);

        $this->actingAs($intruder)
            ->post("/shift/{$shift->id}/close", ['counted_cash' => 10])
            ->assertForbidden();

        $this->assertNull($shift->fresh()->closed_at);
    }

    public function test_admin_can_close_any_shift(): void
    {
        $cashier = $this->cashier();
        $shift = Shift::create([
            'cashier_id' => $cashier->id,
            'opened_at' => now(),
            'opening_float' => 0,
        ]);

        $this->actingAs($this->admin())
            ->post("/shift/{$shift->id}/close", ['counted_cash' => 0])
            ->assertRedirect();

        $this->assertNotNull($shift->fresh()->closed_at);
    }

    public function test_closing_an_already_closed_shift_is_a_noop(): void
    {
        $cashier = $this->cashier();
        $shift = Shift::create([
            'cashier_id' => $cashier->id,
            'opened_at' => now()->subHour(),
            'closed_at' => now(),
            'opening_float' => 0,
            'expected_cash' => 0,
            'counted_cash' => 0,
            'difference' => 0,
        ]);

        $this->actingAs($cashier)
            ->post("/shift/{$shift->id}/close", ['counted_cash' => 999])
            ->assertSessionHas('error');

        $this->assertEqualsWithDelta(0.0, (float) $shift->fresh()->counted_cash, 0.001);
    }

    // ----- History gating -------------------------------------------------

    public function test_cashier_cannot_view_all_shifts_history(): void
    {
        $this->actingAs($this->cashier())->get('/shifts')->assertForbidden();
    }

    public function test_admin_can_view_all_shifts_history(): void
    {
        $this->actingAs($this->admin())->get('/shifts')->assertOk();
    }
}
