<?php

namespace Database\Seeders;

use App\Models\Sale;
use App\Models\SaleUnit;
use App\Models\Shift;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoSalesSeeder extends Seeder
{
    /**
     * Generate a week of realistic sales, a couple of voids, and one closed
     * shift per day so the Sales history, Reports, and Shift history screens
     * have something to show. Idempotent-ish: it no-ops if sales already
     * exist, so re-running db:seed won't stack duplicate demo data.
     *
     * Stock is decremented per sale and never driven below zero — lines that
     * wouldn't fit the remaining stock are skipped, which also preserves the
     * intentionally-low variant from InventorySeeder for the low-stock demo.
     */
    public function run(): void
    {
        if (Sale::query()->exists()) {
            return;
        }

        $cashier = User::where('username', 'cashier')->first();
        $admin = User::where('username', 'admin')->first();

        if (!$cashier || !$admin) {
            return; // Users seeder must run first.
        }

        // Load sellable units with their variant + product tax rate, and track
        // remaining stock in memory as we spend it.
        $units = SaleUnit::with('variant.product')->get();
        if ($units->isEmpty()) {
            return; // Inventory seeder must run first.
        }

        $stock = [];
        foreach (Variant::all() as $v) {
            $stock[$v->id] = (int) $v->stock_qty;
        }

        // Seven days ending today.
        for ($daysAgo = 6; $daysAgo >= 0; $daysAgo--) {
            $day = Carbon::today()->subDays($daysAgo);
            $cashSalesToday = 0.0;
            $salesMade = 0;

            $count = random_int(3, 7);
            for ($i = 0; $i < $count; $i++) {
                $when = $day->copy()
                    ->setTime(random_int(9, 19), random_int(0, 59), random_int(0, 59));

                $sale = $this->makeSale($units, $stock, $cashier, $when);
                if ($sale === null) {
                    continue;
                }

                $salesMade++;
                if ($sale->payment_method === Sale::PAYMENT_CASH) {
                    $cashSalesToday += (float) $sale->grand_total;
                }
            }

            // One closed shift per day for the cashier. Expected = float + the
            // day's cash sales; counted wobbles by a small amount some days.
            // Amounts are whole kyat (MMK).
            $float = 50000;
            $expected = round($float + $cashSalesToday, 2);
            $variance = [0, 0, -500, 1000, 0, -1000, 500][$daysAgo] ?? 0;
            $counted = round($expected + $variance, 2);

            Shift::create([
                'cashier_id' => $cashier->id,
                'opened_at' => $day->copy()->setTime(8, 30),
                'closed_at' => $day->copy()->setTime(20, 0),
                'opening_float' => $float,
                'expected_cash' => $expected,
                'counted_cash' => $counted,
                'difference' => round($counted - $expected, 2),
            ]);
        }

        // Persist the spent-down stock.
        foreach ($stock as $variantId => $qty) {
            Variant::whereKey($variantId)->update(['stock_qty' => $qty]);
        }

        // Void a couple of recent cash sales to populate the void log. Voiding
        // restores stock, matching the app's own void behaviour.
        $toVoid = Sale::where('status', Sale::STATUS_COMPLETED)
            ->where('payment_method', Sale::PAYMENT_CASH)
            ->latest('id')
            ->take(2)
            ->get();

        foreach ($toVoid as $idx => $sale) {
            $sale->load('items.saleUnit');
            foreach ($sale->items as $item) {
                Variant::whereKey($item->saleUnit->variant_id)
                    ->increment('stock_qty', $item->quantity * (int) $item->saleUnit->pack_size);
            }
            $sale->update([
                'status' => Sale::STATUS_VOIDED,
                'voided_by' => $admin->id,
                'voided_reason' => $idx === 0 ? 'Customer changed mind' : 'Wrong item scanned',
            ]);
        }
    }

    /**
     * Build one completed sale of 1–3 lines. Returns null if nothing could be
     * added (e.g. everything picked was out of stock).
     */
    private function makeSale($units, array &$stock, User $cashier, Carbon $when): ?Sale
    {
        $lineCount = random_int(1, 3);
        $picks = $units->random(min($lineCount, $units->count()));
        if (!is_iterable($picks)) {
            $picks = [$picks];
        }

        $lines = [];
        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;

        foreach ($picks as $unit) {
            $variantId = $unit->variant->id;
            $packSize = (int) $unit->pack_size;
            $qty = random_int(1, 3);
            $need = $qty * $packSize;

            if (($stock[$variantId] ?? 0) < $need) {
                continue; // not enough stock for this line
            }

            $unitPrice = (float) $unit->price;
            $gross = round($unitPrice * $qty, 2);

            // ~1 in 5 lines gets a small discount, rounded to whole kyat.
            $discount = random_int(1, 5) === 1
                ? (float) min((int) round($gross * 0.1 / 100) * 100, $gross)
                : 0.0;

            $lineTotal = round($gross - $discount, 2);
            $taxRate = (float) $unit->variant->product->tax_rate;
            $lineTax = round($lineTotal * $taxRate / 100, 2);

            $subtotal += $gross;
            $discountTotal += $discount;
            $taxTotal += $lineTax;

            $stock[$variantId] -= $need;

            $lines[] = [
                'sale_unit_id' => $unit->id,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'discount' => $discount,
                'line_total' => $lineTotal,
            ];
        }

        if (empty($lines)) {
            return null;
        }

        $subtotal = round($subtotal, 2);
        $discountTotal = round($discountTotal, 2);
        $taxTotal = round($taxTotal, 2);
        $grandTotal = round($subtotal - $discountTotal + $taxTotal, 2);

        // ~70% cash, ~30% QR.
        $isCash = random_int(1, 10) <= 7;

        $sale = new Sale([
            'cashier_id' => $cashier->id,
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'discount_total' => $discountTotal,
            'grand_total' => $grandTotal,
            'payment_method' => $isCash ? Sale::PAYMENT_CASH : Sale::PAYMENT_QR,
            'status' => Sale::STATUS_COMPLETED,
        ]);

        if ($isCash) {
            // Round the tender up to the next 500 Ks — a realistic note/coin.
            $tender = (float) (ceil($grandTotal / 500) * 500);
            $sale->cash_tendered = $tender;
            $sale->change_due = round($tender - $grandTotal, 2);
        } else {
            $sale->qr_reference_note = 'POS-' . strtoupper(substr(md5((string) mt_rand()), 0, 5));
        }

        // Timestamps are set explicitly so the data spreads across the week.
        $sale->created_at = $when;
        $sale->updated_at = $when;
        $sale->save();

        foreach ($lines as $line) {
            $sale->items()->create($line);
        }

        return $sale;
    }
}
