<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_root_redirects_guests_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_users_can_authenticate_with_username_and_password(): void
    {
        $user = User::factory()->admin()->create([
            'username' => 'store_admin',
            'password' => Hash::make('secret-pass'),
        ]);

        $response = $this->post('/login', [
            'username' => 'store_admin',
            'password' => 'secret-pass',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        User::factory()->create([
            'username' => 'cashier_a',
            'password' => Hash::make('correct-pass'),
        ]);

        $this->post('/login', [
            'username' => 'cashier_a',
            'password' => 'wrong-pass',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_deactivated_users_cannot_authenticate(): void
    {
        User::factory()->inactive()->create([
            'username' => 'ex_staff',
            'password' => Hash::make('secret-pass'),
        ]);

        $this->post('/login', [
            'username' => 'ex_staff',
            'password' => 'secret-pass',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_guests_cannot_visit_the_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_users_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }
}
