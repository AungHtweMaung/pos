<?php

namespace Database\Seeders;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DemoSalesSeeder extends Seeder
{
    /** Restocks stop this many days before today so some items end up low. */
    private const NO_RESTOCK_LAST_DAYS = 10;

    /** [open time, close time, min sales, max sales] per daily shift. */
    private const SHIFTS = [
        ['08:00', '15:00', 18, 30],
        ['15:00', '21:30', 22, 36],
    ];

    private const VOID_REASONS = [
        'Customer changed mind',
        'Wrong item scanned',
        'Wrong strength dispensed',
        'Duplicate transaction',
        'Prescription not valid',
        'Customer paid by other method',
    ];

    private const QR_WALLETS = ['KBZPay', 'KBZPay', 'KBZPay', 'WavePay', 'AYA Pay', 'CB Pay'];

    private int $adminId;

    /** @var array<int, int> variant id => stock on hand (base units) */
    private array $stock = [];

    /** @var array<int, int|null> */
    private array $thresholds = [];

    /** @var array<int, int> variant id => size of its primary sale unit */
    private array $primaryPack = [];

    /** Weighted pick table: parallel arrays of sale units and cumulative weights. */
    private array $units = [];
    private array $cumulative = [];
    private int $totalWeight = 0;

    private array $adjustments = [];

    /**
     * Ninety days of pharmacy trading: two shifts a day rotated across three
     * cashiers, weighted towards fast-moving lines, with voids, supplier
     * restocks, and write-offs (expired / damaged). Stock is tracked so
     * variant stock = sum(adjustments) − units sold, and each closed shift's
     * expected cash matches Shift::expectedCash(). Today's current shift is
     * left open. No-ops if sales already exist.
     */
    public function run(): void
    {
        if (Sale::query()->exists()) {
            return;
        }

        $this->adminId = (int) User::where('username', 'admin')->value('id');
        $cashierIds = User::whereIn('username', ['cashier', 'thida', 'kyawzin'])
            ->orderBy('id')->pluck('id')->all();

        if (!$this->adminId || empty($cashierIds) || !$this->loadCatalogue()) {
            return; // Users and catalogue seeders must run first.
        }

        mt_srand(9090);
        $now = now();
        $days = PharmacyCatalogSeeder::HISTORY_DAYS;

        for ($daysAgo = $days - 1; $daysAgo >= 0; $daysAgo--) {
            $day = Carbon::today()->subDays($daysAgo);
            $canRestock = $daysAgo >= self::NO_RESTOCK_LAST_DAYS;

            DB::transaction(function () use ($day, $daysAgo, $days, $canRestock, $cashierIds, $now) {
                if ($canRestock) {
                    $this->restockLowItems($day->copy()->setTime(7, 30));
                }
                $this->randomWriteOffs($day);

                // Business grows gently over the period; weekends are busier.
                $trend = 0.85 + 0.3 * (($days - $daysAgo) / $days);
                $weekend = $day->isWeekend() ? 1.2 : 1.0;

                foreach (self::SHIFTS as $s => [$open, $close, $min, $max]) {
                    $openedAt = $day->copy()->setTimeFromTimeString($open);
                    $closedAt = $day->copy()->setTimeFromTimeString($close);
                    if ($openedAt > $now) {
                        continue;
                    }

                    $cashierId = $cashierIds[($daysAgo + $s) % count($cashierIds)];
                    $isOpen = $closedAt > $now;
                    $until = $isOpen ? $now : $closedAt;

                    $count = (int) round(mt_rand($min, $max) * $trend * $weekend);
                    $times = [];
                    for ($i = 0; $i < $count; $i++) {
                        $t = mt_rand($openedAt->timestamp + 120, $closedAt->timestamp - 60);
                        if ($t <= $until->timestamp) {
                            $times[] = $t;
                        }
                    }
                    sort($times);

                    $cashTaken = 0.0;
                    foreach ($times as $t) {
                        $cashTaken += $this->makeSale($cashierId, Carbon::createFromTimestamp($t), $canRestock);
                    }

                    $float = $s === 0 ? 100000 : 50000;
                    $shift = [
                        'cashier_id' => $cashierId,
                        'opened_at' => $openedAt,
                        'closed_at' => null,
                        'opening_float' => $float,
                        'expected_cash' => null,
                        'counted_cash' => null,
                        'difference' => null,
                        'created_at' => $openedAt,
                        'updated_at' => $openedAt,
                    ];

                    if (!$isOpen) {
                        $expected = round($float + $cashTaken, 2);
                        $variance = mt_rand(1, 100) <= 80 ? 0 : [-2000, -1000, -500, 500, 1000][mt_rand(0, 4)];
                        $shift = array_merge($shift, [
                            'closed_at' => $closedAt,
                            'expected_cash' => $expected,
                            'counted_cash' => $expected + $variance,
                            'difference' => $variance,
                            'updated_at' => $closedAt,
                        ]);
                    }

                    DB::table('shifts')->insert($shift);
                }

                foreach (array_chunk($this->adjustments, 500) as $chunk) {
                    DB::table('stock_adjustments')->insert($chunk);
                }
                $this->adjustments = [];
            });
        }

        $this->persistStock();
    }

    private function loadCatalogue(): bool
    {
        $variants = DB::table('variants')->get(['id', 'stock_qty', 'low_stock_threshold']);
        foreach ($variants as $v) {
            $this->stock[$v->id] = (int) $v->stock_qty;
            $this->thresholds[$v->id] = $v->low_stock_threshold === null ? null : (int) $v->low_stock_threshold;
        }

        $rows = DB::table('sale_units')
            ->join('variants', 'variants.id', '=', 'sale_units.variant_id')
            ->join('products', 'products.id', '=', 'variants.product_id')
            ->orderBy('sale_units.id')
            ->get(['sale_units.id', 'sale_units.variant_id', 'sale_units.pack_size', 'sale_units.price', 'products.tax_rate']);

        if ($rows->isEmpty()) {
            return false;
        }

        // Popularity per variant: ~15% fast movers, ~35% steady, rest slow.
        $variantWeight = [];
        foreach (array_keys($this->stock) as $id) {
            $roll = mt_rand(1, 100);
            $variantWeight[$id] = $roll <= 15 ? 25 : ($roll <= 50 ? 5 : 1);
        }

        $seenVariant = [];
        foreach ($rows as $r) {
            $primary = !isset($seenVariant[$r->variant_id]);
            $seenVariant[$r->variant_id] = true;
            if ($primary) {
                $this->primaryPack[$r->variant_id] = (int) $r->pack_size;
            }

            // Customers mostly buy the primary unit (strip, bottle); boxes are rarer.
            $weight = $variantWeight[$r->variant_id] * ($primary ? 6 : 1);
            $this->totalWeight += $weight;
            $this->cumulative[] = $this->totalWeight;
            $this->units[] = [
                'id' => (int) $r->id,
                'variant_id' => (int) $r->variant_id,
                'pack' => (int) $r->pack_size,
                'price' => (float) $r->price,
                'tax_rate' => (float) $r->tax_rate,
            ];
        }

        return true;
    }

    /** Insert one sale and return the cash it leaves in the drawer. */
    private function makeSale(int $cashierId, Carbon $when, bool $canRestock): float
    {
        $voided = mt_rand(1, 1000) <= 15;
        $lineCount = [1, 1, 1, 2, 2, 2, 3, 3, 4, 5][mt_rand(0, 9)];

        $lines = [];
        $subtotal = $discountTotal = $taxTotal = 0.0;

        for ($i = 0; $i < $lineCount; $i++) {
            $unit = $this->pickUnit();
            if (isset($lines[$unit['id']])) {
                continue;
            }

            $qty = match (true) {
                $unit['price'] >= 30000 || $unit['pack'] >= 50 => 1,
                $unit['price'] >= 5000 => mt_rand(1, 10) <= 9 ? 1 : 2,
                default => [1, 1, 1, 1, 2, 2, 3][mt_rand(0, 6)],
            };
            $need = $qty * $unit['pack'];
            $variantId = $unit['variant_id'];

            if ($this->stock[$variantId] < $need) {
                if (!$canRestock) {
                    continue; // out of stock late in the period
                }
                $this->restock($variantId, $when->copy()->subMinutes(mt_rand(5, 60)), $need);
            }

            $gross = round($unit['price'] * $qty, 2);
            // Occasional 5–10% discount for regulars, rounded to 50 Ks.
            $discount = mt_rand(1, 100) <= 7
                ? min(floor($gross * mt_rand(5, 10) / 100 / 50) * 50, $gross)
                : 0.0;
            $lineTotal = round($gross - $discount, 2);

            $subtotal += $gross;
            $discountTotal += $discount;
            $taxTotal += round($lineTotal * $unit['tax_rate'] / 100, 2);

            if (!$voided) {
                $this->stock[$variantId] -= $need;
            }

            $lines[$unit['id']] = [
                'sale_unit_id' => $unit['id'],
                'quantity' => $qty,
                'unit_price' => $unit['price'],
                'discount' => $discount,
                'line_total' => $lineTotal,
            ];
        }

        if (empty($lines)) {
            return 0.0;
        }

        $grandTotal = round($subtotal - $discountTotal + $taxTotal, 2);
        $isCash = mt_rand(1, 100) <= 62;

        $sale = [
            'cashier_id' => $cashierId,
            'subtotal' => round($subtotal, 2),
            'tax_total' => round($taxTotal, 2),
            'discount_total' => round($discountTotal, 2),
            'grand_total' => $grandTotal,
            'payment_method' => $isCash ? Sale::PAYMENT_CASH : Sale::PAYMENT_QR,
            'cash_tendered' => null,
            'change_due' => null,
            'qr_reference_note' => null,
            'status' => $voided ? Sale::STATUS_VOIDED : Sale::STATUS_COMPLETED,
            'voided_by' => $voided ? (mt_rand(0, 1) ? $this->adminId : $cashierId) : null,
            'voided_reason' => $voided ? self::VOID_REASONS[mt_rand(0, count(self::VOID_REASONS) - 1)] : null,
            'created_at' => $when,
            'updated_at' => $voided ? $when->copy()->addMinutes(mt_rand(1, 20)) : $when,
        ];

        if ($isCash) {
            // Exact change, the next 1,000 Ks, or a larger note.
            $tender = match (mt_rand(1, 10)) {
                1, 2 => ceil($grandTotal / 100) * 100,
                3, 4, 5, 6 => ceil($grandTotal / 1000) * 1000,
                7, 8 => ceil($grandTotal / 5000) * 5000,
                default => ceil($grandTotal / 10000) * 10000,
            };
            $sale['cash_tendered'] = $tender;
            $sale['change_due'] = round($tender - $grandTotal, 2);
        } else {
            $wallet = self::QR_WALLETS[mt_rand(0, count(self::QR_WALLETS) - 1)];
            $sale['qr_reference_note'] = $wallet . ' ' . str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
        }

        $saleId = DB::table('sales')->insertGetId($sale);
        DB::table('sale_items')->insert(array_map(
            fn ($line) => $line + ['sale_id' => $saleId],
            array_values($lines)
        ));

        return ($isCash && !$voided) ? $grandTotal : 0.0;
    }

    private function pickUnit(): array
    {
        $target = mt_rand(1, $this->totalWeight);
        $lo = 0;
        $hi = count($this->cumulative) - 1;
        while ($lo < $hi) {
            $mid = intdiv($lo + $hi, 2);
            if ($this->cumulative[$mid] < $target) {
                $lo = $mid + 1;
            } else {
                $hi = $mid;
            }
        }

        return $this->units[$lo];
    }

    /** Morning delivery for anything at or under its low-stock threshold. */
    private function restockLowItems(Carbon $at): void
    {
        foreach ($this->stock as $variantId => $qty) {
            $threshold = $this->thresholds[$variantId];
            if ($threshold !== null && $qty <= $threshold) {
                $this->restock($variantId, $at, 0);
            }
        }
    }

    /** Receive enough stock to cover $atLeast plus a few weeks of supply. */
    private function restock(int $variantId, Carbon $at, int $atLeast): void
    {
        $pack = $this->primaryPack[$variantId] ?? 1;
        $base = max($this->thresholds[$variantId] ?? $pack * 5, $pack);
        $qty = $atLeast + $base * mt_rand(3, 6);

        $this->stock[$variantId] += $qty;
        $this->adjustments[] = [
            'variant_id' => $variantId,
            'change_qty' => $qty,
            'reason' => 'Supplier delivery — INV-' . $at->format('ymd') . '-' . mt_rand(100, 999),
            'adjusted_by' => $this->adminId,
            'created_at' => $at,
            'updated_at' => $at,
        ];
    }

    /** A few end-of-day removals and count corrections. */
    private function randomWriteOffs(Carbon $day): void
    {
        $n = [0, 0, 0, 1, 1, 2][mt_rand(0, 5)];
        $variantIds = array_keys($this->stock);

        for ($i = 0; $i < $n; $i++) {
            $variantId = $variantIds[mt_rand(0, count($variantIds) - 1)];
            $pack = $this->primaryPack[$variantId] ?? 1;
            [$reason, $change] = match (mt_rand(1, 4)) {
                1 => ['Expired — removed from shelf', -$pack * mt_rand(1, 3)],
                2 => ['Damaged packaging', -$pack],
                3 => ['Stock count correction', -mt_rand(1, max(1, $pack))],
                default => ['Stock count correction', mt_rand(1, max(1, $pack))],
            };

            $change = max($change, -$this->stock[$variantId]);
            if ($change === 0) {
                continue;
            }

            $at = $day->copy()->setTime(21, 45);
            $this->stock[$variantId] += $change;
            $this->adjustments[] = [
                'variant_id' => $variantId,
                'change_qty' => $change,
                'reason' => $reason,
                'adjusted_by' => $this->adminId,
                'created_at' => $at,
                'updated_at' => $at,
            ];
        }
    }

    private function persistStock(): void
    {
        DB::transaction(function () {
            foreach ($this->stock as $variantId => $qty) {
                DB::table('variants')->where('id', $variantId)->update(['stock_qty' => $qty]);
            }
        });
    }
}
