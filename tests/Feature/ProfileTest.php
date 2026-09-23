<?php

namespace Tests\Feature;

use App\Models\{User, Pet};
use App\Support\PetQrReference;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertExitCode(0);
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => 'user', 'name' => 'Profile Owner', 'contact_number' => '+63 917 123 4567', 'date_of_birth' => '1992-05-20']);
    }

    private function values(User $user): array
    {
        return ['name' => $user->name, 'email' => $user->email, 'contact_number' => $user->contact_number, 'date_of_birth' => $user->date_of_birth?->toDateString()];
    }

    private function pet(User $user, string $name = 'Profile Pet'): Pet
    {
        return $user->pets()->create(['name' => $name, 'species' => 'Dog', 'sex' => 'Female', 'owner_name' => $user->name, 'owner_number' => $user->contact_number]);
    }

    public function test_login_view_edit_and_save_own_profile(): void
    {
        $owner = $this->owner();
        $hash = $owner->password;
        $this->post('/login', ['email' => $owner->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->get('/profile')->assertOk()->assertSee('My Profile')->assertSee($owner->name)->assertSee($owner->email)->assertSee('May 20, 1992')->assertSee(route('profile.edit'));
        $this->get('/profile/edit')->assertOk()->assertSee('value="1992-05-20"', false)->assertDontSee('name="user_id"', false)->assertDontSee('name="password"', false)->assertDontSee($hash);
        $this->put('/profile', array_replace($this->values($owner), ['name' => 'Updated Profile Owner', 'contact_number' => '(02) 8123-4567 ext. 8', 'date_of_birth' => '1988-02-29']))
            ->assertSessionHasNoErrors()->assertRedirect('/profile')->assertSessionHas('success', 'Your personal details have been updated successfully.');
        $this->get('/profile')->assertOk()->assertSee('Updated Profile Owner')->assertSee('(02) 8123-4567 ext. 8')->assertSee('Feb 29, 1988');
        $this->assertAuthenticatedAs($owner);
        $this->assertDatabaseCount('users', 1);
        $this->assertSame($hash, $owner->fresh()->password);
    }

    public function test_guests_are_redirected_and_other_roles_keep_separate_workflows(): void
    {
        foreach (['/profile', '/profile/edit'] as $url) { $this->get($url)->assertRedirect('/login'); }
        $this->put('/profile', [])->assertRedirect('/login');
        foreach (['staff', 'admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['/profile', '/profile/edit'] as $url) { $this->get($url)->assertForbidden(); }
            $this->put('/profile', [])->assertForbidden();
            $this->get('/dashboard')->assertOk()->assertDontSee('My Profile');
        }
    }

    public function test_profile_displays_no_credentials_or_backend_fields(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        foreach (['/profile', '/profile/edit'] as $url) {
            $response = $this->get($url)->assertOk();
            foreach ([$owner->password, $owner->remember_token, 'name="role"', 'name="permissions"', 'name="remember_token"', 'name="email_verified_at"', 'name="id"'] as $secret) {
                if ($secret) { $response->assertDontSee($secret, false); }
            }
        }
    }

    public function test_email_change_preserves_session_identity_pets_medical_records_and_qr(): void
    {
        $owner = $this->owner();
        $id = $owner->id;
        $oldEmail = $owner->email;
        $hash = $owner->password;
        $rememberToken = $owner->remember_token;
        $pet = $this->pet($owner);
        $secondPet = $this->pet($owner, 'Second Profile Pet');
        $other = $this->owner();
        $otherPet = $this->pet($other, 'Other Private Pet');
        $otherSnapshot = $otherPet->fresh()->getRawOriginal();
        $pet->vaccinations()->create(['vaccine' => 'Profile Vaccine', 'administered_at' => '2026-01-01']);
        $pet->dewormingRecords()->create(['product' => 'Profile Product', 'administered_at' => '2026-01-01']);
        $medicalBefore = [DB::table('vaccinations')->first(), DB::table('deworming_records')->first()];
        $qr = PetQrReference::url($pet);
        $this->actingAs($owner)->put('/profile', array_replace($this->values($owner), ['name' => 'New QR Owner', 'email' => 'New.Profile@Example.Test']))->assertSessionHasNoErrors()->assertRedirect('/profile');
        $owner->refresh();
        $this->assertSame($id, $owner->id);
        $this->assertSame('user', $owner->role);
        $this->assertSame('new.profile@example.test', $owner->email);
        $this->assertSame($hash, $owner->password);
        $this->assertSame($rememberToken, $owner->remember_token);
        $this->assertNull($owner->email_verified_at);
        $this->assertEqualsCanonicalizing([$pet->id, $secondPet->id], $owner->pets()->pluck('id')->all());
        $this->assertSame($id, $pet->fresh()->owner->id);
        $this->assertSame($otherSnapshot, $otherPet->fresh()->getRawOriginal());
        $this->assertEquals($medicalBefore, [DB::table('vaccinations')->first(), DB::table('deworming_records')->first()]);
        $this->assertAuthenticatedAs($owner);
        foreach (['/mypets', '/vaccinations', '/deworming'] as $url) { $this->get($url)->assertOk()->assertSee('Profile Pet')->assertDontSee('Other Private Pet'); }
        $this->get('/pets/'.$pet->id)->assertOk();
        $this->get('/qrcodes/'.$pet->id.'/preview')->assertOk();
        $this->get($qr)->assertOk()->assertSee('New QR Owner')->assertSee('Profile Pet')->assertDontSee($owner->email);
        $this->post('/logout');
        $this->post('/login', ['email' => $oldEmail, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $owner->email, 'password' => 'password'])->assertRedirect('/dashboard');
    }

    public function test_current_email_is_accepted_but_duplicate_email_is_rejected(): void
    {
        $owner = $this->owner();
        $other = $this->owner();
        $this->actingAs($owner)->put('/profile', $this->values($owner))->assertSessionHasNoErrors()->assertRedirect('/profile');
        $this->put('/profile', array_replace($this->values($owner), ['email' => $other->email]))->assertSessionHasErrors('email')->assertRedirect('/profile/edit');
        $this->assertNotSame($other->email, $owner->fresh()->email);
        $this->assertNotNull($owner->fresh()->email_verified_at);
    }

    public function test_query_or_url_ids_never_select_another_profile(): void
    {
        $owner = $this->owner();
        $other = $this->owner();
        $other->update(['name' => 'Unrelated Private Owner']);
        $snapshot = $other->fresh()->getRawOriginal();
        $this->actingAs($owner);
        foreach (['/profile', '/profile/edit'] as $url) {
            $this->get($url.'?user_id='.$other->id)->assertOk()->assertSee($owner->email)->assertDontSee($other->email);
        }
        $this->get('/profile/'.$other->id.'/edit')->assertNotFound();
        $this->put('/profile/'.$other->id, $this->values($owner))->assertNotFound();
        $this->put('/profile?user_id='.$other->id, $this->values($owner))->assertSessionHasErrors('user_id');
        $this->put('/profile', array_replace($this->values($owner), ['user_id' => $other->id]))->assertSessionHasErrors('user_id');
        $this->assertSame($snapshot, $other->fresh()->getRawOriginal());
    }

    public static function protectedFields(): array
    {
        return array_map(fn ($field) => [$field], ['id', 'user_id', 'owner_id', 'pet_id', 'role', 'is_admin', 'is_staff', 'permissions', 'password', 'password_confirmation', 'remember_token', 'email_verified_at', 'created_at', 'updated_at']);
    }

    #[DataProvider('protectedFields')]
    public function test_protected_fields_cannot_be_changed(string $field): void
    {
        $owner = $this->owner();
        $snapshot = $owner->fresh()->getRawOriginal();
        $this->actingAs($owner)->put('/profile', array_replace($this->values($owner), [$field => 'admin']))->assertSessionHasErrors($field);
        $this->assertSame($snapshot, $owner->fresh()->getRawOriginal());
    }

    public static function invalidFields(): array
    {
        return [
            ['name', ''], ['name', str_repeat('a', 256)], ['email', 'not-an-email'],
            ['contact_number', str_repeat('1', 41)], ['contact_number', ['invalid']],
            ['date_of_birth', '2999-01-01'], ['date_of_birth', '2025-02-29'], ['date_of_birth', 'not-a-date'],
        ];
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_profile_values_are_rejected(string $field, mixed $value): void
    {
        $owner = $this->owner();
        $before = $owner->fresh()->getRawOriginal();
        $this->actingAs($owner)->put('/profile', array_replace($this->values($owner), [$field => $value]))->assertSessionHasErrors($field)->assertRedirect('/profile/edit');
        $this->assertSame($before, $owner->fresh()->getRawOriginal());
    }

    public function test_inline_errors_and_old_input_are_preserved(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->put('/profile', array_replace($this->values($owner), ['name' => 'Keep My Input', 'email' => 'invalid']))->assertSessionHasErrors('email');
        $this->get('/profile/edit')->assertOk()->assertSee('value="Keep My Input"', false)->assertSee('value="invalid"', false)->assertSee('invalid-feedback')->assertSee('The email field must be a valid email address.');
    }

    public function test_nullable_existing_fields_and_date_only_changes_are_safe(): void
    {
        $owner = $this->owner();
        $pet = $this->pet($owner);
        $snapshot = $pet->fresh()->getRawOriginal();
        $this->actingAs($owner)->put('/profile', array_replace($this->values($owner), ['date_of_birth' => '1980-01-01']))->assertSessionHasNoErrors();
        $this->assertSame($snapshot, $pet->fresh()->getRawOriginal());
        $this->put('/profile', array_replace($this->values($owner), ['date_of_birth' => null, 'contact_number' => null]))->assertSessionHasNoErrors();
        $this->assertNull($owner->fresh()->date_of_birth);
        $this->assertNull($owner->fresh()->contact_number);
    }

    public function test_profile_cannot_claim_orphan_pet_records_by_email(): void
    {
        $owner = $this->owner();
        $pet = Pet::create(['name' => 'Unmatched Pet', 'species' => 'Dog', 'sex' => 'Male', 'owner_email' => 'unmatched@example.test']);
        $this->actingAs($owner)->put('/profile', array_replace($this->values($owner), ['email' => 'unmatched@example.test']))->assertSessionHasErrors('email')->assertRedirect('/profile/edit');
        $this->assertSame('unmatched@example.test', $pet->fresh()->owner_email);
        $this->assertCount(0, $owner->fresh()->pets);
    }

    public function test_pet_owner_column_limits_are_validated(): void
    {
        $owner = $this->owner();
        $this->pet($owner);
        $this->actingAs($owner)->put('/profile', array_replace($this->values($owner), ['name' => str_repeat('a', 121)]))->assertSessionHasErrors('name');
    }

    public function test_each_profile_page_highlights_one_link_in_both_menus(): void
    {
        $this->actingAs($this->owner());
        foreach (['/profile', '/profile/edit'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            preg_match_all('/<nav class="app-nav nav flex-column gap-1">(.*?)<\/nav>/s', $html, $menus);
            $this->assertCount(2, $menus[1]);
            foreach ($menus[1] as $menu) {
                preg_match_all('/<a class="nav-link active"[^>]*>(?:<i[^>]*><\/i>)?([^<]+)/', $menu, $links);
                $this->assertSame(['My Profile'], array_map('trim', $links[1]));
            }
        }
    }

    public function test_profile_update_requires_csrf_outside_test_bypass(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $this->app->instance('env', 'local');
        $this->put('/profile', $this->values($owner))->assertStatus(419);
    }

    public function test_failed_pet_contact_update_rolls_back_profile_update(): void
    {
        $owner = $this->owner();
        $pet = $this->pet($owner);
        $ownerBefore = $owner->fresh()->getRawOriginal();
        $petBefore = $pet->fresh()->getRawOriginal();
        DB::connection()->beforeExecuting(function ($query): void {
            if (str_starts_with($query, 'update "pets"')) {
                throw new \Illuminate\Database\QueryException('sqlite', $query, [], new \RuntimeException('Synthetic pet update failure'));
            }
        });
        $this->actingAs($owner)->put('/profile', array_replace($this->values($owner), ['email' => 'rollback@example.test']))->assertStatus(500);
        $this->assertSame($ownerBefore, $owner->fresh()->getRawOriginal());
        $this->assertSame($petBefore, $pet->fresh()->getRawOriginal());
    }
}
