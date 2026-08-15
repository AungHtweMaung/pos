<?php

namespace App\Http\Controllers\Users;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\ResetPasswordRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * List all users. Admin-only per spec §5 ("Manage cashier accounts CRUD").
     */
    public function index(Request $request): Response
    {
        $q = trim((string) $request->input('q', ''));
        $role = $request->input('role');
        $status = $request->input('status');

        $users = User::query()
            ->when($q !== '', function ($qry) use ($q) {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
                $qry->where(fn ($x) => $x
                    ->where('name', 'like', $like)
                    ->orWhere('username', 'like', $like));
            })
            ->when($role, fn ($qry) => $qry->where('role', $role))
            ->when($status === 'active', fn ($qry) => $qry->where('is_active', true))
            ->when($status === 'inactive', fn ($qry) => $qry->where('is_active', false))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Cashiers/Index', [
            'users' => $users,
            'filters' => ['q' => $q, 'role' => $role, 'status' => $status],
            'roles' => [UserRole::Admin->value, UserRole::Cashier->value],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Cashiers/Create', [
            'roles' => [UserRole::Admin->value, UserRole::Cashier->value],
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->input('name'),
            'username' => $request->input('username'),
            'password' => $request->input('password'),
            'role' => $request->input('role'),
            'is_active' => true,
        ]);

        return redirect()
            ->route('cashiers.edit', $user)
            ->with('success', "Account created for {$user->name}.");
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Cashiers/Edit', [
            'user' => $user->only(['id', 'name', 'username', 'role', 'is_active']),
            'roles' => [UserRole::Admin->value, UserRole::Cashier->value],
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        // Guard against admin locking themselves out by demoting their own
        // account. Deactivation of self is handled separately below.
        if ($user->id === $request->user()->id
            && $request->input('role') !== UserRole::Admin->value
        ) {
            return back()->with('error', "You can't remove your own admin role.");
        }

        $user->update($request->validated());

        return back()->with('success', 'Account updated.');
    }

    /**
     * Reset a user's password. Separate endpoint keeps the update form free
     * of empty-password ambiguity (blank means "don't change").
     */
    public function resetPassword(ResetPasswordRequest $request, User $user): RedirectResponse
    {
        $user->update(['password' => $request->input('password')]);

        return back()->with('success', "Password reset for {$user->name}.");
    }

    /**
     * Deactivate an account. Login checks is_active (see LoginRequest), so the
     * user is signed out on next attempt. Spec §4 recommends deactivate over
     * hard-delete so past sales still resolve their cashier.
     */
    public function deactivate(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', "You can't deactivate your own account.");
        }

        // Prevent removing the last active admin so the shop can't lock
        // itself out.
        if ($user->role === UserRole::Admin && $user->is_active) {
            $activeAdmins = User::where('role', UserRole::Admin)
                ->where('is_active', true)
                ->count();
            if ($activeAdmins <= 1) {
                return back()->with('error', 'At least one active admin is required.');
            }
        }

        $user->update(['is_active' => false]);

        return back()->with('success', "{$user->name} deactivated.");
    }

    public function activate(User $user): RedirectResponse
    {
        $user->update(['is_active' => true]);
        return back()->with('success', "{$user->name} reactivated.");
    }
}
