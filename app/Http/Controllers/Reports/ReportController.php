<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * The single admin Reports page (spec §8.5). Covers the daily sales
     * summary, best-sellers, and the void/refund log for a chosen date range.
     * Gated by view-reports in web.php.
     *
     * Note on profit: sale_items freezes unit_price but not cost, so gross
     * profit uses the sale unit's *current* cost. If a cost is edited later,
     * historical profit shifts accordingly — acceptable for the MVP.
     */
    public function index(Request $request): Response
    {
        [$from, $to, $range] = $this->resolveRange($request);

        return Inertia::render('Reports/Index', [
            'filters' => [
                'range' => $range,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'summary' => $this->summary($from, $to),
            'bestSellers' => $this->bestSellers($from, $to),
            'voids' => $this->voidLog($from, $to),
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private function resolveRange(Request $request): array
    {
        $range = $request->input('range', 'today');

        return match ($range) {
            'week' => [now()->startOfWeek(), now()->endOfWeek(), 'week'],
            'month' => [now()->startOfMonth(), now()->endOfMonth(), 'month'],
            'custom' => [
                $this->parseDate($request->input('from'), now()->startOfDay())->startOfDay(),
                $this->parseDate($request->input('to'), now()->endOfDay())->endOfDay(),
                'custom',
            ],
            default => [now()->startOfDay(), now()->endOfDay(), 'today'],
        };
    }

    private function parseDate(?string $value, Carbon $fallback): Carbon
    {
        if (!$value) {
            return $fallback->copy();
        }
        try {
            return Carbon::parse($value);
        } catch (\Exception) {
            return $fallback->copy();
        }
    }

    private function summary(Carbon $from, Carbon $to): array
    {
        $completed = Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('created_at', [$from, $to]);

        $revenue = (float) (clone $completed)->sum('grand_total');
        $transactions = (clone $completed)->count();
        $taxTotal = (float) (clone $completed)->sum('tax_total');
        $discountTotal = (float) (clone $completed)->sum('discount_total');

        // Payment method breakdown.
        $byMethod = (clone $completed)
            ->selectRaw('payment_method, COUNT(*) as count, SUM(grand_total) as total')
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        $methods = [];
        foreach ([Sale::PAYMENT_CASH, Sale::PAYMENT_QR] as $m) {
            $row = $byMethod->get($m);
            $methods[$m] = [
                'count' => $row ? (int) $row->count : 0,
                'total' => $row ? (float) $row->total : 0.0,
            ];
        }

        // Items sold + cost, over completed sales in the window.
        $itemAgg = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('sale_units', 'sale_units.id', '=', 'sale_items.sale_unit_id')
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereBetween('sales.created_at', [$from, $to])
            ->selectRaw('COALESCE(SUM(sale_items.quantity), 0) as units')
            ->selectRaw('COALESCE(SUM(sale_items.quantity * sale_units.cost), 0) as cost')
            ->selectRaw('COALESCE(SUM(sale_items.line_total), 0) as net_revenue')
            ->first();

        $itemsSold = (int) $itemAgg->units;
        $cost = (float) $itemAgg->cost;
        // Gross profit is measured on net line revenue (after per-line
        // discount, before tax) minus cost — the true trading margin.
        $netRevenue = (float) $itemAgg->net_revenue;
        $grossProfit = round($netRevenue - $cost, 2);

        // Voided / refunded in the window.
        $voided = Sale::query()
            ->whereIn('status', [Sale::STATUS_VOIDED, Sale::STATUS_REFUNDED])
            ->whereBetween('created_at', [$from, $to]);
        $voidCount = (clone $voided)->count();
        $voidTotal = (float) (clone $voided)->sum('grand_total');

        return [
            'revenue' => round($revenue, 2),
            'transactions' => $transactions,
            'items_sold' => $itemsSold,
            'average_sale' => $transactions > 0 ? round($revenue / $transactions, 2) : 0.0,
            'tax_total' => round($taxTotal, 2),
            'discount_total' => round($discountTotal, 2),
            'cost' => round($cost, 2),
            'gross_profit' => $grossProfit,
            'margin_pct' => $netRevenue > 0 ? round($grossProfit / $netRevenue * 100, 1) : 0.0,
            'methods' => $methods,
            'void_count' => $voidCount,
            'void_total' => round($voidTotal, 2),
        ];
    }

    /**
     * Best-selling variants by base units moved, with revenue and profit.
     * Grouped by variant (product + variant label); quantity is expressed in
     * base units (sale_item.quantity × pack_size) so single vs multipack sales
     * of the same variant aggregate correctly.
     */
    private function bestSellers(Carbon $from, Carbon $to): array
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('sale_units', 'sale_units.id', '=', 'sale_items.sale_unit_id')
            ->join('variants', 'variants.id', '=', 'sale_units.variant_id')
            ->join('products', 'products.id', '=', 'variants.product_id')
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereBetween('sales.created_at', [$from, $to])
            ->groupBy('variants.id', 'products.name', 'variants.label')
            ->selectRaw('variants.id as variant_id')
            ->selectRaw('products.name as product_name')
            ->selectRaw('variants.label as variant_label')
            ->selectRaw('SUM(sale_items.quantity * sale_units.pack_size) as base_units')
            ->selectRaw('SUM(sale_items.quantity) as units')
            ->selectRaw('SUM(sale_items.line_total) as revenue')
            ->selectRaw('SUM(sale_items.line_total - sale_items.quantity * sale_units.cost) as profit')
            ->orderByDesc('revenue')
            ->limit(20)
            ->get()
            ->map(fn ($r) => [
                'variant_id' => (int) $r->variant_id,
                'product_name' => $r->product_name,
                'variant_label' => $r->variant_label,
                'base_units' => (int) $r->base_units,
                'units' => (int) $r->units,
                'revenue' => round((float) $r->revenue, 2),
                'profit' => round((float) $r->profit, 2),
            ])
            ->all();
    }

    private function voidLog(Carbon $from, Carbon $to): array
    {
        return Sale::query()
            ->with(['cashier:id,name', 'voider:id,name'])
            ->whereIn('status', [Sale::STATUS_VOIDED, Sale::STATUS_REFUNDED])
            ->whereBetween('created_at', [$from, $to])
            ->latest('updated_at')
            ->limit(50)
            ->get()
            ->map(fn (Sale $s) => [
                'id' => $s->id,
                'grand_total' => (float) $s->grand_total,
                'status' => $s->status,
                'cashier' => $s->cashier?->name,
                'voided_by' => $s->voider?->name,
                'reason' => $s->voided_reason,
                'created_at' => $s->created_at,
            ])
            ->all();
    }
}
