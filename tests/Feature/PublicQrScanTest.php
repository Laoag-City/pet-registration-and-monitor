<?php

namespace Tests\Feature;

use App\Models\{User, Pet};
use App\Support\PetQrReference;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class PublicQrScanTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertExitCode(0);
    }

    private function pet(): Pet
    {
        // Existing installations may retain obsolete columns; QR must never expose them.
        if (! \Illuminate\Support\Facades\Schema::hasColumn('pets', 'microchip')) {
            \Illuminate\Support\Facades\Schema::table('pets', fn ($table) => $table->string('microchip', 60)->nullable()->unique());
        }
        $owner = User::factory()->create(['name' => 'Current Owner Name', 'role' => 'user', 'contact_number' => '09179998888']);

        $pet = $owner->pets()->create([
            'name' => 'Public Pet Name', 'species' => 'Dog', 'sex' => 'Male',
            'owner_name' => 'Stale Snapshot Name', 'owner_number' => '09171112222',
        ]);
        // Simulate a retained legacy database value, outside the removed public assignment path.
        $pet->forceFill(['microchip' => 'PRIVATE-MICROCHIP-'.$pet->id])->save();

        return $pet;
    }

    public function test_public_scan_uses_database_owner_and_exposes_only_name_fields(): void
    {
        $pet = $this->pet();
        $pet->vaccinations()->create(['vaccine' => 'PRIVATE-VACCINE', 'administered_at' => '2026-01-01', 'notes' => 'PRIVATE-MEDICAL-NOTES']);
        $pet->dewormingRecords()->create(['product' => 'PRIVATE-PRODUCT', 'administered_at' => '2026-01-01']);
        $url = PetQrReference::url($pet);
        $this->assertStringContainsString('/scan/', $url);
        $this->assertStringNotContainsString('/pets/'.$pet->id, $url);
        $response = $this->get($url.'?owner_name=Injected+Name&owner_id=999')->assertOk()
            ->assertViewHas('ownerName', 'Current Owner Name')->assertViewHas('petName', 'Public Pet Name')
            ->assertSee('Current Owner Name')->assertSee('Public Pet Name')
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        foreach ([$pet->code, $pet->owner_email, $pet->owner_number, $pet->owner->contact_number, $pet->owner->password, $pet->owner->remember_token, $pet->microchip, 'PRIVATE-VACCINE', 'PRIVATE-PRODUCT', 'PRIVATE-MEDICAL-NOTES', 'Injected Name', 'Stale Snapshot Name'] as $private) {
            if ($private) { $response->assertDontSee($private); }
        }
        $response->assertViewMissing('pet')->assertViewMissing('owner');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_public_page_does_not_display_authenticated_admin_information(): void
    {
        $pet = $this->pet();
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Secret Admin Identity']);
        $this->actingAs($admin)->get(PetQrReference::url($pet))->assertOk()
            ->assertDontSee($admin->name)->assertDontSee($admin->email)->assertDontSee('User Management');
    }

    public function test_invalid_missing_tampered_and_unrelated_tokens_are_safe_404s(): void
    {
        $url = PetQrReference::url($this->pet());
        $reference = basename(parse_url($url, PHP_URL_PATH));
        $reference[20] = $reference[20] === 'A' ? 'B' : 'A';
        $unrelated = rtrim(strtr(Crypt::encryptString('not-a-pet-reference'), '+/', '-_'), '=');
        foreach (['/scan', '/scan/123', '/scan/invalid-token', '/scan/path/with/slashes', '/scan/'.str_repeat('a', 1025), '/scan/'.$reference, '/scan/'.$unrelated] as $path) {
            $this->get($path)->assertNotFound()->assertSee('Pet not found')->assertDontSee('SQLSTATE')->assertDontSee('Stack trace')->assertDontSee('APP_KEY');
        }
    }

    public function test_deleted_pet_or_missing_owner_is_not_publicly_resolved(): void
    {
        $pet = $this->pet();
        $url = PetQrReference::url($pet);
        $pet->delete();
        $this->get($url)->assertNotFound()->assertSee('Pet not found');
        $pet = $this->pet();
        $url = PetQrReference::url($pet);
        $pet->owner->delete();
        $this->get($url)->assertNotFound()->assertSee('Pet not found');
    }

    public function test_owner_role_change_does_not_publish_admin_information(): void
    {
        $pet = $this->pet();
        $url = PetQrReference::url($pet);
        $pet->owner->update(['role' => 'admin']);
        $this->get($url)->assertNotFound()->assertDontSee('Current Owner Name');
    }

    public function test_reference_stays_valid_and_reads_current_names(): void
    {
        $pet = $this->pet();
        $first = PetQrReference::url($pet);
        $second = PetQrReference::url($pet);
        $this->assertNotSame($first, $second);
        $pet->owner->update(['name' => 'Updated Public Owner']);
        $pet->update(['name' => 'Updated Public Pet']);
        foreach ([$first, $second] as $url) {
            $this->get($url)->assertOk()->assertSee('Updated Public Owner')->assertSee('Updated Public Pet');
        }
    }

    public function test_legacy_links_and_generation_endpoints_remain_private(): void
    {
        $pet = $this->pet();
        foreach (['/pets/'.$pet->id, '/qrcodes/'.$pet->id.'/preview', '/qrcodes/'.$pet->id.'/download'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
        $this->actingAs(User::factory()->create(['role' => 'user']));
        foreach (['/pets/'.$pet->id, '/qrcodes/'.$pet->id.'/preview', '/qrcodes/'.$pet->id.'/download'] as $path) {
            $this->get($path)->assertForbidden();
        }
    }

    public function test_database_errors_on_public_scan_do_not_render_debug_details(): void
    {
        $url = PetQrReference::url($this->pet());
        \Illuminate\Support\Facades\DB::connection()->beforeExecuting(function ($query): void {
            if (str_contains($query, '"pets"')) {
                throw new \Illuminate\Database\QueryException('sqlite', $query, [], new \RuntimeException('Synthetic database failure'));
            }
        });
        $this->get($url)->assertStatus(503)->assertSee('Temporarily unavailable')->assertDontSee('SQLSTATE')->assertDontSee('Stack trace');
    }
}
