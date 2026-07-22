<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the single shared dashboard. The same page renders for both roles;
     * role-specific actions are gated in the UI and by policies/gates (§5).
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard');
    }
}
