<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Inventory\LowStockController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\SaleUnitController;
use App\Http\Controllers\Inventory\StockAdjustmentController;
use App\Http\Controllers\Inventory\VariantController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Sales\LookupController;
use App\Http\Controllers\Sales\SaleController;
use App\Http\Controllers\Shifts\ShiftController;
use App\Http\Controllers\Users\UserController;
use Illuminate\Support\Facades\Route;

// Guests: login page + submit. The `throttle` here is a coarse network-level
// guard; per-username+IP brute-force throttling lives in LoginRequest (§4).
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login');
});

// Authenticated users: shared dashboard + logout.
Route::middleware('auth')->group(function () {
    Route::get('/', fn () => redirect()->route('dashboard'));

    Route::get('dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    // Inventory (§8.2) — admin only. The `manage-products` gate covers CRUD;
    // stock adjustments carry their own `adjust-stock` gate for symmetry with
    // the §5 permission matrix even though both currently equal "admin".
    Route::middleware('can:manage-products')
        ->prefix('inventory')
        ->name('inventory.')
        ->group(function () {
            Route::get('low-stock', [LowStockController::class, 'index'])->name('low-stock');

            Route::resource('products', ProductController::class);

            Route::prefix('products/{product}')->group(function () {
                Route::post('variants', [VariantController::class, 'store'])
                    ->name('products.variants.store');
                Route::put('variants/{variant}', [VariantController::class, 'update'])
                    ->name('products.variants.update');
                Route::delete('variants/{variant}', [VariantController::class, 'destroy'])
                    ->name('products.variants.destroy');

                Route::post('variants/{variant}/sale-units', [SaleUnitController::class, 'store'])
                    ->name('products.variants.sale-units.store');
                Route::put('variants/{variant}/sale-units/{saleUnit}', [SaleUnitController::class, 'update'])
                    ->name('products.variants.sale-units.update');
                Route::delete('variants/{variant}/sale-units/{saleUnit}', [SaleUnitController::class, 'destroy'])
                    ->name('products.variants.sale-units.destroy');

                Route::post('variants/{variant}/stock-adjustments', [StockAdjustmentController::class, 'store'])
                    ->middleware('can:adjust-stock')
                    ->name('products.variants.stock-adjustments.store');
            });
        });

    // Sales / POS (§8.1). Cart + checkout are open to any signed-in user
    // (cashier + admin); history + void are admin-only.
    Route::prefix('pos')->name('pos.')->group(function () {
        Route::get('/', [SaleController::class, 'create'])->name('cart');
        Route::post('/', [SaleController::class, 'store'])->name('store');
        Route::get('lookup', LookupController::class)->name('lookup');
    });

    Route::prefix('sales')->name('sales.')->group(function () {
        // Receipt + detail: cashier can view own, admin can view any (the
        // controller enforces this).
        Route::get('{sale}/receipt', [SaleController::class, 'receipt'])->name('receipt');
        Route::get('{sale}', [SaleController::class, 'show'])->name('show');

        // History + void are admin-only.
        Route::middleware('can:view-reports')
            ->get('/', [SaleController::class, 'index'])
            ->name('index');

        Route::middleware('can:void-sale')
            ->post('{sale}/void', [SaleController::class, 'void'])
            ->name('void');
    });

    // Cashier / account management (§8.3) — admin only. Deactivate-not-delete
    // per spec §4 so past sales still resolve their cashier.
    Route::middleware('can:manage-users')
        ->prefix('cashiers')
        ->name('cashiers.')
        ->group(function () {
            Route::get('/', [UserController::class, 'index'])->name('index');
            Route::get('create', [UserController::class, 'create'])->name('create');
            Route::post('/', [UserController::class, 'store'])->name('store');
            Route::get('{user}/edit', [UserController::class, 'edit'])->name('edit');
            Route::put('{user}', [UserController::class, 'update'])->name('update');
            Route::put('{user}/password', [UserController::class, 'resetPassword'])
                ->name('reset-password');
            Route::post('{user}/deactivate', [UserController::class, 'deactivate'])
                ->name('deactivate');
            Route::post('{user}/activate', [UserController::class, 'activate'])
                ->name('activate');
        });

    // End of Shift (§8.4). Cashier + admin run their own drawer; the history
    // view across all cashiers is admin-only (view-all-shifts gate).
    Route::get('shift', [ShiftController::class, 'index'])->name('shift.index');
    Route::post('shift/open', [ShiftController::class, 'open'])->name('shift.open');
    Route::post('shift/{shift}/close', [ShiftController::class, 'close'])->name('shift.close');

    Route::middleware('can:view-all-shifts')
        ->get('shifts', [ShiftController::class, 'history'])
        ->name('shifts.history');

    // Reporting (§8.5) — admin only. Daily summary, best-sellers, void log.
    Route::middleware('can:view-reports')
        ->get('reports', [ReportController::class, 'index'])
        ->name('reports.index');
});
