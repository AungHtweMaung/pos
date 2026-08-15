<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CashierManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function cashier(): User
    {
        return User::factory()->create(['role' => UserRole::Cashier]);
    }

    // ----- Role gating ----------------------------------------------------

    public function test_cashier_cannot_access_cashier_management(): void
    {
        $this->actingAs($this->cashier())
            ->get('/cashiers')
            ->assertForbidden();
    }

    public function test_cashier_cannot_create_users(): void
    {
        $this->actingAs($this->cashier())
            ->post('/cashiers', [
                'name' => 'Malicious',
                'username' => 'x',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'admin',
            ])
            ->assertForbidden();
    }

    public function test_admin_sees_the_list(): void
    {
        $this->actingAs($this->admin())
            ->get('/cashiers')
            ->assertOk();
    }

    // ----- Create ---------------------------------------------------------

    public function test_admin_can_create_a_cashier(): void
    {
        $this->actingAs($this->admin())
            ->post('/cashiers', [
                'name' => 'Jane Cashier',
                'username' => 'jane',
                'password' => 'strong-password',
                'password_confirmation' => 'strong-password',
                'role' => 'cashier',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'username' => 'jane',
            'name' => 'Jane Cashier',
            'role' => 'cashier',
            'is_active' => true,
        ]);
    }

    public function test_password_must_be_confirmed(): void
    {
        $this->actingAs($this->admin())
            ->from('/cashiers/create')
            ->post('/cashiers', [
                'name' => 'x',
                'username' => 'x',
                'password' => 'strong-password',
                'password_confirmation' => 'nope',
                'role' => 'cashier',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_username_must_be_unique(): void
    {
        User::factory()->create(['username' => 'jane']);

        $this->actingAs($this->admin())
            ->from('/cashiers/create')
            ->post('/cashiers', [
                'name' => 'x',
                'username' => 'jane',
                'password' => 'strong-password',
                'password_confirmation' => 'strong-password',
                'role' => 'cashier',
            ])
            ->assertSessionHasErrors('username');
    }

    // ----- Update ---------------------------------------------------------

    public function test_admin_can_rename_a_user(): void
    {
        $user = User::factory()->create(['username' => 'oldname']);

        $this->actingAs($this->admin())
            ->put("/cashiers/{$user->id}", [
                'name' => 'Renamed',
                'username' => 'oldname',
                'role' => 'cashier',
            ])
            ->assertRedirect();

        $this->assertSame('Renamed', $user->fresh()->name);
    }

    public function test_username_uniqueness_ignores_self_on_update(): void
    {
        $user = User::factory()->create(['username' => 'jane']);

        $this->actingAs($this->admin())
            ->put("/cashiers/{$user->id}", [
                'name' => 'Jane',
                'username' => 'jane',
                'role' => 'cashier',
            ])
            ->assertRedirect();
    }

    public function test_admin_cannot_demote_themselves(): void
    {
        $admin = User::factory()->admin()->create([
            'username' => 'boss',
            'name' => 'Boss',
        ]);

        $this->actingAs($admin)
            ->from("/cashiers/{$admin->id}/edit")
            ->put("/cashiers/{$admin->id}", [
                'name' => 'Boss',
                'username' => 'boss',
                'role' => 'cashier',
            ])
            ->assertSessionHas('error');

        $this->assertSame(UserRole::Admin, $admin->fresh()->role);
    }

    // ----- Password reset -------------------------------------------------

    public function test_admin_can_reset_a_users_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->actingAs($this->admin())
            ->put("/cashiers/{$user->id}/password", [
                'password' => 'new-strong-password',
                'password_confirmation' => 'new-strong-password',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('new-strong-password', $user->fresh()->password));
    }

    // ----- Deactivate / activate ------------------------------------------

    public function test_admin_can_deactivate_a_cashier(): void
    {
        $user = $this->cashier();

        $this->actingAs($this->admin())
            ->post("/cashiers/{$user->id}/deactivate")
            ->assertRedirect();

        $this->assertFalse((bool) $user->fresh()->is_active);
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        User::factory()->create([
            'username' => 'offshift',
            'password' => Hash::make('password'),
            'is_active' => false,
        ]);

        $this->post('/login', [
            'username' => 'offshift',
            'password' => 'password',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_admin_cannot_deactivate_themselves(): void
    {
        $admin = $this->admin();
        // Make sure another admin exists so the "last admin" guard doesn't
        // trigger first — we want to prove the self-guard.
        $this->admin();

        $this->actingAs($admin)
            ->post("/cashiers/{$admin->id}/deactivate")
            ->assertSessionHas('error');

        $this->assertTrue((bool) $admin->fresh()->is_active);
    }

    // Note: the "last active admin" guard in the controller is defensive
    // dead code in practice — the self-guard fires first for any admin who
    // could reach this endpoint, and inactive users are blocked by the
    // EnsureUserIsActive middleware before the controller runs. Not tested
    // via HTTP.

    public function test_admin_can_reactivate_a_user(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($this->admin())
            ->post("/cashiers/{$user->id}/activate")
            ->assertRedirect();

        $this->assertTrue((bool) $user->fresh()->is_active);
    }
}
