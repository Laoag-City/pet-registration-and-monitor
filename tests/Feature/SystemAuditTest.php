<?php

namespace Tests\Feature;

use App\Models\{User, Pet, Vaccination, DewormingRecord};
use Illuminate\Support\Facades\{Hash, Storage};
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SystemAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertExitCode(0);
    }

    private function account(string $role = 'user'): User
    {
        return User::factory()->create(['role' => $role, 'contact_number' => '09000000000', 'date_of_birth' => '1990-01-01']);
    }

    private function pet(User $owner, string $name = 'Audit Pet'): Pet
    {
        return Pet::create(['name' => $name, 'species' => 'Dog', 'sex' => 'Male', 'date_of_birth' => '2020-01-01', 'owner_email' => $owner->email, 'owner_name' => $owner->name, 'owner_number' => $owner->contact_number]);
    }

    private function profile(User $user): array
    {
        return ['name' => $user->name, 'email' => $user->email, 'contact_number' => $user->contact_number, 'date_of_birth' => '1990-01-01', 'role' => $user->role];
    }

    public function test_login_logout_and_invalid_credentials(): void
    {
        $user = $this->account();
        $this->post('/login', ['email' => $user->email, 'password' => 'incorrect'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_guest_and_role_route_matrix(): void
    {
        $shared = ['/dashboard', '/mypets', '/vaccinations', '/deworming', '/qrcodes'];
        $staff = ['/staff/dashboard', '/staff/users', '/staff/pets', '/staff/pets/create'];
        $admin = ['/admin/dashboard', '/admin/users', '/admin/users/create'];
        foreach (array_merge($shared, $staff, $admin) as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        foreach (['user', 'staff', 'admin'] as $role) {
            $this->actingAs($this->account($role));
            foreach ($shared as $url) { $this->get($url)->assertOk(); }
            foreach ($staff as $url) { $this->get($url)->assertStatus($role === 'user' ? 403 : 200); }
            foreach ($admin as $url) { $this->get($url)->assertStatus($role === 'admin' ? 200 : 403); }
        }
    }

    public function test_owner_isolation_in_lists_details_and_qr(): void
    {
        $owner = $this->account();
        $own = $this->pet($owner, 'Own Audit Pet');
        $other = $this->pet($this->account(), 'Private Other Pet');
        foreach ([$own, $other] as $pet) {
            Vaccination::create(['pet_id' => $pet->id, 'vaccine' => $pet->name.' Vaccine', 'administered_at' => '2025-01-01']);
            DewormingRecord::create(['pet_id' => $pet->id, 'product' => $pet->name.' Product', 'administered_at' => '2025-01-01']);
        }
        $this->actingAs($owner);
        foreach (['/dashboard', '/mypets', '/vaccinations', '/deworming', '/qrcodes'] as $url) {
            $this->get($url)->assertOk()->assertSee('Own Audit Pet')->assertDontSee('Private Other Pet');
        }
        $this->get('/pets/'.$own->id)->assertOk();
        foreach (['/pets/'.$other->id, '/qrcodes/'.$other->id.'/preview', '/qrcodes/'.$other->id.'/download'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->get('/pets/999999')->assertNotFound();
        $this->get('/qrcodes/999999/preview')->assertNotFound();
    }

    public function test_owner_cannot_write_medical_or_staff_records_or_edit_users(): void
    {
        $owner = $this->account();
        $target = $this->account();
        $this->actingAs($owner);
        foreach (['/staff/pets', '/staff/vaccinations', '/staff/deworming', '/admin/users'] as $url) {
            $this->post($url, [])->assertForbidden();
        }
        $this->put('/admin/users/'.$target->id, $this->profile($target))->assertForbidden();
        $this->delete('/admin/users/'.$target->id)->assertForbidden();
        $this->actingAs($this->account('staff'));
        $this->get('/admin/users/'.$target->id.'/edit')->assertForbidden();
        $this->put('/admin/users/'.$target->id, $this->profile($target))->assertForbidden();
        $this->delete('/admin/users/'.$target->id)->assertForbidden();
    }

    public function test_staff_registration_uses_selected_owner_and_validates_uploads(): void
    {
        Storage::fake('public');
        $owner = $this->account();
        $staff = $this->account('staff');
        $this->actingAs($staff);
        $data = ['owner_id' => $owner->id, 'name' => 'Audit Registered', 'species' => 'Feline', 'sex' => 'Female', 'owner_email' => 'attacker@example.test'];
        $this->post('/staff/pets', $data)->assertRedirect('/staff/users/'.$owner->id);
        $this->assertDatabaseHas('pets', ['name' => 'Audit Registered', 'owner_email' => $owner->email]);
        $this->post('/staff/pets', array_merge($data, ['date_of_birth' => '2999-01-01']))->assertSessionHasErrors('date_of_birth');
        $this->post('/staff/pets', array_merge($data, ['photo' => UploadedFile::fake()->create('payload.php', 1, 'application/x-php')]))->assertSessionHasErrors('photo');
        $this->post('/staff/pets', array_merge($data, ['owner_id' => $staff->id]))->assertSessionHasErrors('owner_id');
        $this->get('/staff/pets/create?client='.$staff->id)->assertNotFound();
        $this->get('/staff/users/'.$staff->id)->assertNotFound();
    }

    public function test_staff_and_admin_can_add_medical_records_with_validation(): void
    {
        $pet = $this->pet($this->account());
        foreach (['staff', 'admin'] as $role) {
            $this->actingAs($this->account($role));
            foreach (['vaccinations' => 'vaccine', 'deworming' => 'product'] as $path => $field) {
                $data = ['owner_id' => $pet->owner->id, 'pet_id' => $pet->id, $field => 'Audit Treatment', 'administered_at' => '2025-01-01', 'next_due_at' => '2026-01-01'];
                $this->post('/staff/'.$path, $data)->assertSessionHasNoErrors()->assertRedirect();
                $this->post('/staff/'.$path, array_merge($data, ['pet_id' => 999999]))->assertSessionHasErrors('pet_id');
                $this->post('/staff/'.$path, array_merge($data, ['next_due_at' => '2024-01-01']))->assertSessionHasErrors('next_due_at');
                $this->post('/staff/'.$path, array_merge($data, ['administered_at' => '2999-01-01']))->assertSessionHasErrors('administered_at');
            }
        }
        $this->assertDatabaseCount('vaccinations', 2);
        $this->assertDatabaseCount('deworming_records', 2);
    }

    public function test_admin_profile_edit_preserves_ownership_and_password(): void
    {
        $owner = $this->account();
        $pet = $this->pet($owner);
        $hash = $owner->password;
        $this->actingAs($this->account('admin'));
        $this->get('/admin/users/'.$owner->id.'/edit')->assertOk()->assertDontSee($hash);
        $data = array_merge($this->profile($owner), ['name' => 'Updated Owner', 'email' => 'updated@example.test', 'password' => '']);
        $this->put('/admin/users/'.$owner->id, $data)->assertSessionHasNoErrors()->assertRedirect('/admin/users');
        $this->assertSame($hash, $owner->fresh()->password);
        $this->assertSame('updated@example.test', $pet->fresh()->owner_email);
        $this->assertSame('Updated Owner', $pet->fresh()->owner_name);
        $this->actingAs($owner->fresh())->get('/pets/'.$pet->id)->assertOk();
    }

    public function test_admin_password_role_changes_are_validated_and_hashed(): void
    {
        $owner = $this->account();
        $this->actingAs($this->account('admin'));
        $data = array_merge($this->profile($owner), ['role' => 'staff', 'password' => 'Audit-New-Password', 'password_confirmation' => 'wrong']);
        $this->put('/admin/users/'.$owner->id, $data)->assertSessionHasErrors('password');
        $data['password_confirmation'] = $data['password'];
        $this->put('/admin/users/'.$owner->id, $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue(Hash::check($data['password'], $owner->fresh()->password));
        $this->assertSame('staff', $owner->fresh()->role);
        $this->assertArrayNotHasKey('password', $owner->fresh()->toArray());
    }

    public function test_deleting_an_owner_with_pets_is_blocked(): void
    {
        $owner = $this->account();
        $pet = $this->pet($owner);
        $this->actingAs($this->account('admin'))->delete('/admin/users/'.$owner->id)->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
        $this->assertDatabaseHas('pets', ['id' => $pet->id]);
    }

    public function test_qr_svg_and_pdf_work_and_remain_private(): void
    {
        $owner = $this->account();
        $pet = $this->pet($owner);
        $this->get('/pets/'.$pet->id)->assertRedirect('/login');
        $this->get('/qrcodes/'.$pet->id.'/preview')->assertRedirect('/login');
        $this->actingAs($owner);
        $this->get('/qrcodes/'.$pet->id.'/preview')->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertSee('<svg', false);
        $response = $this->get('/qrcodes/'.$pet->id.'/download')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $response->assertDownload('pet-credential.pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_sidebar_has_one_active_item_in_each_menu(): void
    {
        $this->actingAs($this->account('staff'));
        foreach (['/dashboard' => 'Dashboard', '/staff/dashboard' => 'Dashboard', '/staff/users' => 'User Management', '/staff/pets' => 'All Pets', '/staff/pets/create' => 'Register Pet', '/vaccinations' => 'Vaccinations', '/deworming' => 'Deworming', '/qrcodes' => 'QR Codes'] as $url => $label) {
            $html = $this->get($url)->assertOk()->getContent();
            preg_match_all('/<nav class="app-nav nav flex-column gap-1">(.*?)<\/nav>/s', $html, $menus);
            $this->assertCount(2, $menus[1]);
            foreach ($menus[1] as $menu) {
                preg_match_all('/<a class="nav-link active"[^>]*>(?:<i[^>]*><\/i>)?([^<]+)/', $menu, $active);
                $this->assertSame([$label], array_map('trim', $active[1]), $url);
            }
        }
    }

    public function test_pet_codes_and_broken_delete_button_are_not_visible(): void
    {
        $owner = $this->account();
        $pet = $this->pet($owner);
        foreach ([$owner, $this->account('staff'), $this->account('admin')] as $user) {
            $this->actingAs($user);
            foreach (['/dashboard', '/mypets', '/pets/'.$pet->id, '/vaccinations', '/deworming', '/qrcodes'] as $url) {
                $this->get($url)->assertOk()->assertDontSee($pet->code)->assertDontSee('Delete pet');
            }
            if ($user->canManageRecords()) { $this->get('/staff/pets')->assertOk()->assertDontSee($pet->code); }
        }
    }

    public function test_public_nav_has_no_registration_link(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        preg_match('/<header class="site-header">(.*?)<\/header>/s', $html, $header);
        $this->assertStringNotContainsString('/register', $header[1]);
    }

    public function test_validation_feedback_is_visible(): void
    {
        $this->actingAs($this->account('staff'));
        $this->from('/vaccinations')->post('/staff/vaccinations', [])->assertSessionHasErrors('pet_id');
        $this->get('/vaccinations')->assertSee('The pet id field is required.');
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/login', ['email' => 'absent@example.test', 'password' => 'incorrect']);
        }
        $this->post('/login', ['email' => 'absent@example.test', 'password' => 'incorrect'])->assertStatus(429);
    }

    public function test_search_pagination_and_client_selection(): void
    {
        $owner = $this->account();
        for ($i = 0; $i < 16; $i++) { $this->pet($owner, sprintf('Audit Pet %02d', $i)); }
        $this->actingAs($this->account('staff'));
        $this->get('/staff/pets')->assertOk()->assertSee('page=2', false);
        $this->get('/staff/pets?q=Audit+Pet+15')->assertOk()->assertSee('Audit Pet 15')->assertDontSee('Audit Pet 00');
        $this->get('/staff/users?q='.urlencode($owner->email))->assertOk()->assertSee($owner->email);
        $this->get('/staff/pets/create?client='.$owner->id)->assertOk()->assertSee('Selected client:')->assertSee($owner->email);
        $this->get('/staff/users/'.$owner->id)->assertOk()->assertSee('Audit Pet 00');
    }

    public function test_public_registration_cannot_assign_a_privileged_role(): void
    {
        $data = ['name' => 'Audit Registration', 'email' => 'audit-registration@example.test', 'contact_number' => '09000000000', 'date_of_birth' => '1990-01-01', 'password' => 'Audit-Test-Password', 'password_confirmation' => 'Audit-Test-Password', 'role' => 'admin'];
        $this->post('/register', $data)->assertRedirect('/login');
        $user = User::where('email', $data['email'])->firstOrFail();
        $this->assertSame('user', $user->role);
        $this->assertTrue(Hash::check($data['password'], $user->password));
        $this->assertGuest();
        $this->post('/register', $data)->assertSessionHasErrors('email');
    }

    public function test_csrf_middleware_rejects_missing_token(): void
    {
        // Laravel bypasses CSRF in tests; explicitly enable its normal request behavior.
        $this->app->instance('env', 'local');
        $this->post('/login', ['email' => 'audit@example.test', 'password' => 'invalid'])->assertStatus(419);
    }

    public function test_login_rotates_session_and_logout_rotates_csrf_token(): void
    {
        $user = $this->account();
        $this->get('/login');
        $session = $this->app['session.store'];
        $id = $session->getId();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->assertNotSame($id, $session->getId());
        $token = $session->token();
        $this->post('/logout')->assertRedirect('/');
        $this->assertNotSame($token, $session->token());
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_password_reset_invalidates_prior_authenticated_sessions(): void
    {
        $user = $this->account();
        $oldHash = $user->password;
        $user->update(['password' => 'Changed-Audit-Password']);
        $this->actingAs($user->fresh())->withSession(['password_hash_web' => $oldHash])->get('/dashboard')->assertRedirect('/login');
    }

    public function test_valid_photo_upload_and_pet_field_validation(): void
    {
        Storage::fake('public');
        $owner = $this->account();
        $this->actingAs($this->account('staff'));
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII=');
        $data = ['owner_id' => $owner->id, 'name' => 'Audit Photo', 'species' => 'Canine', 'sex' => 'Male', 'photo' => UploadedFile::fake()->createWithContent('audit.png', $png)];
        $this->post('/staff/pets', $data)->assertSessionHasNoErrors()->assertRedirect();
        $pet = Pet::where('name', 'Audit Photo')->firstOrFail();
        Storage::disk('public')->assertExists($pet->photo_path);
        $this->get('/pets/'.$pet->id)->assertSee('storage/'.$pet->photo_path);
        unset($data['photo']);
        $this->post('/staff/pets', array_merge($data, ['sex' => 'invalid']))->assertSessionHasErrors('sex');
        $owner->update(['name' => str_repeat('a', 121)]);
        $this->post('/staff/pets', $data)->assertSessionHasErrors('owner_id');
    }

    public function test_admin_pet_owner_fields_respect_database_lengths(): void
    {
        $owner = $this->account();
        $this->pet($owner);
        $this->actingAs($this->account('admin'))->put('/admin/users/'.$owner->id, array_merge($this->profile($owner), ['name' => str_repeat('a', 121)]))->assertSessionHasErrors('name');
    }

    public function test_qr_payload_targets_the_correct_public_pet_reference(): void
    {
        $owner = $this->account();
        $pet = $this->pet($owner);
        $qr = \Mockery::mock();
        \Linkxtr\QrCode\Facades\QrCode::swap($qr);
        \Linkxtr\QrCode\Facades\QrCode::shouldReceive('format')->once()->with('svg')->andReturnSelf();
        \Linkxtr\QrCode\Facades\QrCode::shouldReceive('size')->once()->with(300)->andReturnSelf();
        \Linkxtr\QrCode\Facades\QrCode::shouldReceive('margin')->once()->with(2)->andReturnSelf();
        \Linkxtr\QrCode\Facades\QrCode::shouldReceive('errorCorrection')->once()->with('M')->andReturnSelf();
        \Linkxtr\QrCode\Facades\QrCode::shouldReceive('generate')->once()->with(\Mockery::on(function ($url) use ($pet) {
            $reference = basename(parse_url($url, PHP_URL_PATH));
            return str_contains($url, '/scan/') && \App\Support\PetQrReference::resolve($reference)?->is($pet);
        }))->andReturn('<svg />');
        $this->actingAs($owner)->get('/qrcodes/'.$pet->id.'/preview')->assertContent('<svg />');
    }

    public function test_admin_password_reset_revokes_database_sessions_and_remember_token(): void
    {
        config(['session.driver' => 'database']);
        $owner = $this->account();
        $admin = $this->account('admin');
        $oldToken = $owner->getRememberToken();
        \Illuminate\Support\Facades\DB::table('sessions')->insert([
            ['id' => 'audit-owner-session', 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'audit-other-session', 'user_id' => $admin->id, 'payload' => '', 'last_activity' => time()],
        ]);
        $data = array_merge($this->profile($owner), ['password' => 'Audit-Reset-Password', 'password_confirmation' => 'Audit-Reset-Password']);
        $this->actingAs($admin)->put('/admin/users/'.$owner->id, $data)->assertSessionHasNoErrors()->assertRedirect('/admin/users');
        $this->assertDatabaseMissing('sessions', ['id' => 'audit-owner-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'audit-other-session']);
        $this->assertNotSame($oldToken, $owner->fresh()->getRememberToken());
    }
}
