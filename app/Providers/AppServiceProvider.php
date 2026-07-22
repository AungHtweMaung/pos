<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerGates();
        $this->registerRateLimiters();
    }

    /**
     * Coarse per-IP limiter for the login endpoint (§4). This is a network
     * backstop; the precise per-username+IP counting lives in LoginRequest.
     */
    protected function registerRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });
    }

    /**
     * Admin-only abilities from the §5 permission matrix. Cashier abilities
     * (ring up a sale, apply discounts, print receipts, own reconciliation)
     * are available to every authenticated user and so aren't gated here.
     * These gates back the backend checks; the frontend mirrors them by
     * reading the shared `auth.user.is_admin` prop.
     */
    protected function registerGates(): void
    {
        $adminOnly = [
            'void-sale',        // void or refund a completed sale
            'manage-products',  // create/edit/delete products & variants
            'edit-prices',      // edit prices
            'adjust-stock',     // manual stock adjustment
            'manage-users',     // manage cashier/admin accounts
            'view-reports',     // view sales/reports
            'view-all-shifts',  // shift history across all cashiers
        ];

        foreach ($adminOnly as $ability) {
            Gate::define($ability, fn (User $user) => $user->isAdmin());
        }
    }
}
