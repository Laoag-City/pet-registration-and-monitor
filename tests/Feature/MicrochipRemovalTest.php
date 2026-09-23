<?php

namespace Tests\Feature;

use App\Models\{Pet, User};
use App\Support\PetQrReference;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MicrochipRemovalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertExitCode(0);
    }

    public function test_registration_and_edit_ignore_retired_field_and_retain_legacy_data(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $staff = User::factory()->create(['role' => 'staff']);
        $values = ['owner_id' => $owner->id, 'name' => 'Chipless Pet', 'species' => 'Dog', 'sex' => 'Male'];
        $this->actingAs($staff)->post('/staff/pets', $values)->assertSessionHasNoErrors()->assertRedirect();
        $pet = Pet::sole();
        $this->assertNull($pet->microchip);
        $this->addLegacyColumnForCompatibilityCheck();
        $pet->forceFill(['microchip' => 'LEGACY-PRIVATE-777'])->save();
        $qr = PetQrReference::url($pet);
        $pet->vaccinations()->create(['vaccine' => 'Test', 'administered_at' => '2026-01-01']);
        $pet->dewormingRecords()->create(['product' => 'Test', 'administered_at' => '2026-01-01']);
        $this->get('/staff/pets/'.$pet->id.'/edit')->assertOk()->assertDontSee('microchip', false);
        $edit = ['name' => 'Edited Chipless Pet', 'species' => 'Dog', 'sex' => 'Female'];
        $this->put('/staff/pets/'.$pet->id, $edit)->assertSessionHasNoErrors()->assertRedirect('/pets/'.$pet->id);
        $this->put('/staff/pets/'.$pet->id, $edit + ['microchip' => 'REPLACEMENT', 'microchip_number' => 'INJECTED'])->assertSessionHasNoErrors();
        $this->assertSame('LEGACY-PRIVATE-777', $pet->fresh()->microchip);
        $this->assertSame($owner->id, $pet->fresh()->owner->id);
        $this->assertSame('Edited Chipless Pet', $pet->fresh()->name);
        $this->assertTrue(Schema::hasColumn('pets', 'microchip'));
        $this->assertArrayNotHasKey('microchip', $pet->fresh()->toArray());
        $this->get($qr)->assertOk()->assertSee('Edited Chipless Pet')->assertDontSee('LEGACY-PRIVATE-777');
        $this->assertDatabaseCount('vaccinations', 1);
        $this->assertDatabaseCount('deworming_records', 1);
        $this->post('/staff/pets', $values + ['microchip' => str_repeat('x', 200)])->assertSessionHasNoErrors();
        $this->assertNull(Pet::latest('id')->first()->microchip);
    }

    public function test_no_microchip_on_visible_pages_or_qr_pdf(): void
    {
        $this->addLegacyColumnForCompatibilityCheck();
        $owner = User::factory()->create(['role' => 'user']);
        $pet = $owner->pets()->create(['name' => 'Hidden Chip Pet', 'species' => 'Dog', 'sex' => 'Male', 'owner_name' => $owner->name]);
        $pet->forceFill(['microchip' => 'LEGACY-PRIVATE-888'])->save();
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        foreach (['/staff/pets/create?client='.$owner->id, '/staff/pets/'.$pet->id.'/edit', '/staff/pets', '/staff/dashboard', '/pets/'.$pet->id, '/vaccinations', '/deworming', '/staff/vaccinations/create?owner_id='.$owner->id.'&pet_id='.$pet->id, '/staff/deworming/create?owner_id='.$owner->id.'&pet_id='.$pet->id, '/qrcodes/'.$pet->id.'/preview'] as $url) {
            $response = $this->get($url)->assertOk();
            $this->assertStringNotContainsString('microchip', strtolower($response->getContent()));
            $response->assertDontSee('LEGACY-PRIVATE-888');
        }
        $pdfHtml = view('qrcodes.pdf', ['pet' => $pet, 'qrSvg' => '<svg/>'])->render();
        $this->assertStringNotContainsString('microchip', strtolower($pdfHtml));
        $this->assertStringNotContainsString('LEGACY-PRIVATE-888', $pdfHtml);
        $this->actingAs($owner);
        foreach (['/mypets', '/pets/'.$pet->id, '/dashboard', '/qrcodes', PetQrReference::url($pet)] as $url) {
            $response = $this->get($url)->assertOk();
            $this->assertStringNotContainsString('microchip', strtolower($response->getContent()));
            $response->assertDontSee('LEGACY-PRIVATE-888');
        }
    }

    public function test_pet_edit_is_manager_only_and_cannot_transfer_ownership(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $pet = $owner->pets()->create(['name' => 'Protected Pet', 'species' => 'Dog', 'sex' => 'Male']);
        $url = '/staff/pets/'.$pet->id;
        $this->get($url.'/edit')->assertRedirect('/login');
        $this->put($url, [])->assertRedirect('/login');
        $this->actingAs($owner)->get($url.'/edit')->assertForbidden();
        $this->put($url, [])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get($url.'/edit')->assertOk();
        $values = ['name' => 'Updated', 'species' => 'Dog', 'sex' => 'Male'];
        $this->put($url, $values + ['owner_email' => 'claim@example.test', 'owner_id' => 999])->assertSessionHasErrors(['owner_email', 'owner_id']);
        $this->put($url, array_replace($values, ['date_of_birth' => '2025-02-29']))->assertSessionHasErrors('date_of_birth');
        $this->get($url.'/edit')->assertOk()->assertSee('invalid-feedback')->assertSee('value="Updated"', false);
        $this->assertSame($owner->email, $pet->fresh()->owner_email);
        $this->put($url, $values)->assertSessionHasNoErrors()->assertRedirect('/pets/'.$pet->id);
        $this->app->instance('env', 'local');
        $this->put($url, $values)->assertStatus(419);
    }

    public function test_supported_serialization_keeps_relationships_and_excludes_obsolete_attributes(): void
    {
        $pet = new Pet(['name' => 'Supported Pet', 'species' => 'Dog']);
        $pet->setAttribute('obsolete_private_column', 'private');
        $pet->setRelation('vaccinations', collect());
        $pet->setRelation('dewormingRecords', collect());
        $values = $pet->toArray();
        $this->assertSame('Supported Pet', $values['name']);
        $this->assertArrayHasKey('vaccinations', $values);
        $this->assertArrayHasKey('deworming_records', $values);
        $this->assertArrayNotHasKey('obsolete_private_column', $values);
    }

    private function addLegacyColumnForCompatibilityCheck(): void
    {
        // Simulate existing installations only in the isolated in-memory test database.
        if (! Schema::hasColumn('pets', 'microchip')) {
            Schema::table('pets', fn ($table) => $table->string('microchip', 60)->nullable()->unique());
        }
    }
}
