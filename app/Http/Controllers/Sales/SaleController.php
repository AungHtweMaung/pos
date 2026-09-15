<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSaleRequest;
use App\Http\Requests\Sales\VoidSaleRequest;
use App\Models\Sale;
use App\Models\SaleUnit;
use App\Models\Shift;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    /**
     * Sales history — admin only (spec §5: "View sales/reports"). The route
     * is gated by `view-reports` in web.php; this method assumes access.
     */
    public function index(Request $request): Response
    {
        $q = trim((string) $request->input('q', ''));
        $status = $request->input('status');

        $sales = Sale::with('cashier:id,name,username')
            ->when($status, fn ($qry) => $qry->where('status', $status))
            ->when($q !== '', function ($qry) use ($q) {
                if (ctype_digit($q)) {
                    $qry->where('id', (int) $q);
                } else {
                    $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
                    $qry->whereHas('cashier', fn ($c) => $c->where('name', 'like', $like)
                        ->orWhere('username', 'like', $like));
                }
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Sales/Index', [
            'sales' => $sales,
            'filters' => ['q' => $q, 'status' => $status],
        ]);
    }

    /**
     * Cart page (spec §8.1). Cashier + admin both use this.
     */
    public function create(): Response
    {
        return Inertia::render('Sales/Cart');
    }

    /**
     * A cashier's own sales — the list they can browse to reprint a receipt
     * or void a mistake they just made. Open to any authenticated user; each
     * row carries whether the current user may still void it.
     */
    public function mySales(Request $request): Response
    {
        $user = $request->user();
        $hasOpenShift = Shift::openFor($user->id)->exists();

        $sales = Sale::where('cashier_id', $user->id)
            ->latest('id')
            ->paginate(20)
            ->through(fn (Sale $sale) => [
                'id' => $sale->id,
                'created_at' => $sale->created_at,
                'grand_total' => (float) $sale->grand_total,
                'payment_method' => $sale->payment_method,
                'status' => $sale->status,
                'can_void' => $this->canVoid($user, $sale),
            ]);

        return Inertia::render('Sales/MySales', [
            'sales' => $sales,
            'hasOpenShift' => $hasOpenShift,
        ]);
    }

    /**
     * Finalize a sale (spec §8.1 step 6). Wrapped in a serializable-friendly
     * transaction with per-variant lockForUpdate so two cashiers can't
     * oversell the same stock at the same time. Line totals + taxes are
     * recomputed server-side from the frozen sale-unit price — never trusted
     * from the client.
     */
    public function store(StoreSaleRequest $request): RedirectResponse
    {
        $input = $request->validated();

        // Load every distinct sale-unit + variant + product involved so the
        // computation and stock checks share one snapshot.
        $unitIds = collect($input['items'])->pluck('sale_unit_id')->unique()->all();

        $sale = DB::transaction(function () use ($input, $unitIds, $request) {
            $units = SaleUnit::with('variant.product')
                ->whereIn('id', $unitIds)
                ->get()
                ->keyBy('id');

            // Aggregate quantities per variant across cart lines so a variant
            // that appears twice (e.g. loose + strip of same tablet) is
            // checked as one bucket.
            $needByVariant = [];
            $lines = [];

            $subtotal = 0.0;
            $discountTotal = 0.0;
            $taxTotal = 0.0;

            foreach ($input['items'] as $line) {
                $unit = $units->get($line['sale_unit_id']);
                abort_if(!$unit, 422, 'Unknown sale unit.');

                $qty = (int) $line['quantity'];
                $discount = round((float) ($line['discount'] ?? 0), 2);
                $unitPrice = (float) $unit->price;

                $gross = round($unitPrice * $qty, 2);
                if ($discount > $gross) {
                    throw ValidationException::withMessages([
                        'items' => "Discount exceeds line total for {$unit->variant->product->name}.",
                    ]);
                }

                $lineTotal = round($gross - $discount, 2);
                $taxRate = (float) $unit->variant->product->tax_rate;
                $lineTax = round($lineTotal * $taxRate / 100, 2);

                $subtotal += $gross;
                $discountTotal += $discount;
                $taxTotal += $lineTax;

                $variantId = $unit->variant->id;
                $needByVariant[$variantId] = ($needByVariant[$variantId] ?? 0)
                    + $qty * (int) $unit->pack_size;

                $lines[] = [
                    'sale_unit_id' => $unit->id,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'discount' => $discount,
                    'line_total' => $lineTotal,
                ];
            }

            $subtotal = round($subtotal, 2);
            $discountTotal = round($discountTotal, 2);
            $taxTotal = round($taxTotal, 2);
            $grandTotal = round($subtotal - $discountTotal + $taxTotal, 2);

            // Cash-tendered must at least cover the grand total.
            $cashTendered = null;
            $changeDue = null;
            if ($input['payment_method'] === Sale::PAYMENT_CASH) {
                $cashTendered = round((float) $input['cash_tendered'], 2);
                if ($cashTendered < $grandTotal) {
                    throw ValidationException::withMessages([
                        'cash_tendered' => 'Cash tendered is less than the grand total.',
                    ]);
                }
                $changeDue = round($cashTendered - $grandTotal, 2);
            }

            // Lock every variant we need to decrement and check stock in one
            // pass. lockForUpdate prevents two concurrent checkouts from both
            // seeing enough stock and then overselling.
            $variants = Variant::whereIn('id', array_keys($needByVariant))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($needByVariant as $variantId => $need) {
                $variant = $variants->get($variantId);
                if (!$variant || $variant->stock_qty < $need) {
                    $name = optional($variant?->product)->name ?? "variant #{$variantId}";
                    throw ValidationException::withMessages([
                        'items' => "Insufficient stock for {$name}: needed {$need}, have "
                            . ($variant?->stock_qty ?? 0) . '.',
                    ]);
                }
            }

            $sale = Sale::create([
                'cashier_id' => $request->user()->id,
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'discount_total' => $discountTotal,
                'grand_total' => $grandTotal,
                'payment_method' => $input['payment_method'],
                'cash_tendered' => $cashTendered,
                'change_due' => $changeDue,
                'qr_reference_note' => $input['payment_method'] === Sale::PAYMENT_QR
                    ? $input['qr_reference_note']
                    : null,
                'status' => Sale::STATUS_COMPLETED,
            ]);

            foreach ($lines as $line) {
                $sale->items()->create($line);
            }

            foreach ($needByVariant as $variantId => $need) {
                $variants[$variantId]->decrement('stock_qty', $need);
            }

            return $sale;
        });

        return redirect()
            ->route('sales.receipt', $sale)
            ->with('success', "Sale #{$sale->id} completed.");
    }

    /**
     * Sale detail page (admin can void from here). Cashier can also view
     * their own sale (e.g. reprint receipt).
     */
    public function show(Request $request, Sale $sale): Response
    {
        $this->authorizeView($request, $sale);

        $sale->load([
            'cashier:id,name,username',
            'voider:id,name,username',
            'items.saleUnit.variant.product',
        ]);

        return Inertia::render('Sales/Show', [
            'sale' => $sale,
            'canVoid' => $this->canVoid($request->user(), $sale),
        ]);
    }

    /**
     * Print-friendly on-screen receipt (spec §8.1 step 6).
     */
    public function receipt(Request $request, Sale $sale): Response
    {
        $this->authorizeView($request, $sale);

        $sale->load([
            'cashier:id,name,username',
            'items.saleUnit.variant.product',
        ]);

        return Inertia::render('Sales/Receipt', [
            'sale' => $sale,
        ]);
    }

    /**
     * Void a completed sale and restore its stock. An admin may void any
     * sale, anytime. A cashier may void only their own sale, and only while
     * the shift they rang it up in is still open (see canVoid). Records who
     * voided plus the reason.
     */
    public function void(VoidSaleRequest $request, Sale $sale): RedirectResponse
    {
        if ($sale->isVoided()) {
            return back()->with('error', 'Sale is already voided.');
        }

        abort_unless($this->canVoid($request->user(), $sale), 403);

        DB::transaction(function () use ($sale, $request) {
            $sale->load('items.saleUnit');

            $restoreByVariant = [];
            foreach ($sale->items as $item) {
                $variantId = $item->saleUnit->variant_id;
                $restoreByVariant[$variantId] = ($restoreByVariant[$variantId] ?? 0)
                    + $item->quantity * (int) $item->saleUnit->pack_size;
            }

            $variants = Variant::whereIn('id', array_keys($restoreByVariant))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($restoreByVariant as $variantId => $qty) {
                $variants[$variantId]->increment('stock_qty', $qty);
            }

            $sale->update([
                'status' => Sale::STATUS_VOIDED,
                'voided_by' => $request->user()->id,
                'voided_reason' => $request->input('reason'),
            ]);
        });

        return back()->with('success', "Sale #{$sale->id} voided; stock restored.");
    }

    /**
     * A cashier may view their own sales; admin may view any.
     */
    private function authorizeView(Request $request, Sale $sale): void
    {
        $user = $request->user();
        abort_if(
            !$user->isAdmin() && $sale->cashier_id !== $user->id,
            403,
        );
    }

    /**
     * Whether the given user may void the given sale right now.
     *
     * - Admin: any completed sale, anytime.
     * - Cashier: only their own completed sale, and only while the shift it
     *   was rung up in is still open (the sale's timestamp falls on/after the
     *   cashier's current open shift). This lets a cashier fix a fresh mistake
     *   from the live drawer without letting them reach back into an already
     *   closed, reconciled shift.
     */
    private function canVoid(User $user, Sale $sale): bool
    {
        if ($sale->status !== Sale::STATUS_COMPLETED) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($sale->cashier_id !== $user->id) {
            return false;
        }

        $openShift = Shift::openFor($user->id)->first();

        return $openShift !== null && $sale->created_at >= $openShift->opened_at;
    }
}
