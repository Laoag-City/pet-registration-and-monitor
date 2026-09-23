<?php

namespace Tests\Feature;

use App\Models\{Pet, User};
use Tests\TestCase;

class SpeciesFilterTest extends TestCase
{
    public function test_species_options_are_normalized_and_filter_matches_all_case_variants(): void
    {
        // The shared TestCase refuses any database other than SQLite :memory:.
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertExitCode(0);
        $staff = User::factory()->create(['role' => 'staff']);
        foreach (['dog', 'Dog', 'DOG', ' dog ', 'cat', 'FISH', 'rabbit', '   '] as $index => $species) {
            Pet::create(['name' => 'Pet '.$index, 'species' => $species, 'sex' => 'Male']);
        }

        foreach (['dog', 'DOG', ' Dog '] as $input) {
            $response = $this->actingAs($staff)->get('/staff/pets?'.http_build_query(['species' => $input]))->assertOk();
            $this->assertSame(['Cat', 'Dog', 'Fish', 'Rabbit'], $response->viewData('speciesList')->all());
            $this->assertSame('Dog', $response->viewData('speciesFilter'));
            $this->assertSame(4, $response->viewData('pets')->total());
            $response->assertSee('<option value="Dog" selected>Dog</option>', false);
        }
        $response = $this->get('/staff/pets?species=DOG&q=PET+1')->assertOk();
        $this->assertSame(1, $response->viewData('pets')->total());
        $this->assertSame('Pet 1', $response->viewData('pets')->first()->name);
        $this->assertDatabaseHas('pets', ['name' => 'Pet 0', 'species' => 'dog']);
    }
}
