<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Force-logout any authenticated user whose account has been deactivated
     * (§4 — deactivate instead of hard-delete). Guards every request after
     * login so an admin flipping is_active takes effect on the next click.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'username' => 'Your account has been deactivated.',
            ]);
        }

        return $next($request);
    }
}
