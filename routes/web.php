<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Inventory\LowStockController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\SaleUnitController;
use App\Http\Controllers\Inventory\StockAdjustmentController;
use App\Http\Controllers\Inventory\VariantController;
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
});
