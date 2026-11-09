<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    protected bool $enforcePasswordChange = true;

    public function test_login_with_default_password_shows_change_dialog_until_changed(): void
    {
        $user = User::factory()->create(['password' => User::DEFAULT_PASSWORD, 'role' => User::ROLE_STAFF]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->get('/dashboard')->assertSee('defaultPasswordModal', false);

        $this->put('/password', ['current_password' => 'password', 'new_password' => 'password', 'new_password_confirmation' => 'password'])
            ->assertSessionHasErrorsIn('passwordChange', 'new_password');

        $this->put('/password', ['current_password' => 'password', 'new_password' => 'a-new-secret-1', 'new_password_confirmation' => 'a-new-secret-1'])
            ->assertSessionHasNoErrors();

        $this->get('/dashboard')->assertOk()->assertDontSee('defaultPasswordModal', false);
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->fresh()->usesDefaultPassword());
    }

    public function test_login_with_custom_password_shows_no_dialog(): void
    {
        $user = User::factory()->create(['password' => 'custom-pass-123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'custom-pass-123']);
        $this->get('/dashboard')->assertDontSee('defaultPasswordModal', false);
    }

    public function test_other_routes_are_blocked_until_password_changed_even_without_login_flag(): void
    {
        $user = User::factory()->create(['password' => User::DEFAULT_PASSWORD, 'role' => User::ROLE_ADMIN]);

        // No login flag (e.g. remember-me or registration): the middleware computes it.
        $this->actingAs($user);
        $this->get('/admin/users')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk()->assertSee('defaultPasswordModal', false);
        $this->post('/logout')->assertRedirect();
    }

    public function test_password_change_is_throttled(): void
    {
        $user = User::factory()->create(['password' => User::DEFAULT_PASSWORD]);
        $this->actingAs($user);

        for ($i = 0; $i < 6; $i++) {
            $this->put('/password', ['current_password' => 'wrong', 'new_password' => 'x', 'new_password_confirmation' => 'x']);
        }
        $this->put('/password', ['current_password' => 'password', 'new_password' => 'a-new-secret-1', 'new_password_confirmation' => 'a-new-secret-1'])
            ->assertStatus(429);
    }
}
