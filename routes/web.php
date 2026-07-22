<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
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
});
