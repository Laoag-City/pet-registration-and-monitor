<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_default_password_shows_change_dialog_until_changed(): void
    {
        $user = User::factory()->create(['password' => User::DEFAULT_PASSWORD, 'role' => User::ROLE_STAFF]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->get('/dashboard')->assertSee('defaultPasswordModal', false);

        $this->put('/password', ['current_password' => 'password', 'new_password' => 'password', 'new_password_confirmation' => 'password'])
            ->assertSessionHasErrorsIn('passwordChange', 'new_password');

        $this->put('/password', ['current_password' => 'password', 'new_password' => 'a-new-secret-1', 'new_password_confirmation' => 'a-new-secret-1'])
            ->assertSessionHasNoErrors();

        $this->get('/dashboard')->assertDontSee('defaultPasswordModal', false);
        $this->assertFalse($user->fresh()->usesDefaultPassword());
    }

    public function test_login_with_custom_password_shows_no_dialog(): void
    {
        $user = User::factory()->create(['password' => 'custom-pass-123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'custom-pass-123']);
        $this->get('/dashboard')->assertDontSee('defaultPasswordModal', false);
    }
}
