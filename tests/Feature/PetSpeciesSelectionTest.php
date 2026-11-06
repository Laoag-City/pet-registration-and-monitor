<?php

namespace Tests\Feature;

use App\Models\LivestockRecord;
use App\Models\Pet;
use App\Models\User;
use Tests\TestCase;

class PetSpeciesSelectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertExitCode(0);
        $this->actingAs(User::factory()->create(['role' => 'staff']));
    }

    public function test_registration_stores_selected_or_specified_species_and_rejects_invalid_values(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $base = ['owner_id' => $owner->id, 'name' => 'Test Pet', 'sex' => 'Female'];
        $this->get(route('staff.pets.create', ['client' => $owner->id]))->assertOk()->assertSee('Select Species')->assertSee('Specify Species');
        foreach (['Canine', 'Feline', 'Rabbit', 'Bird'] as $species) {
            $values = in_array($species, ['Canine', 'Feline']) ? ['species' => $species] : ['species' => 'Others', 'species_other' => $species];
            $this->post(route('staff.pets.store'), $base + $values)->assertSessionHasNoErrors();
            $this->assertSame($species, Pet::latest('id')->first()->species);
        }
        foreach (['', 'Dog', 'Cat', 'Rabbit'] as $invalid) {
            $this->post(route('staff.pets.store'), $base + ['species' => $invalid])->assertSessionHasErrors('species');
        }
        foreach (['', '   ', 'Others', str_repeat('x', 61)] as $invalid) {
            $this->post(route('staff.pets.store'), $base + ['species' => 'Others', 'species_other' => $invalid])->assertSessionHasErrors('species_other');
        }
        $this->assertSame(4, Pet::count());
        $this->assertSame(['Bubaline', 'Bovine', 'Caprine', 'Swine', 'Equine', 'Ovine'], LivestockRecord::SPECIES);
        $this->assertSame(['Male', 'Female'], LivestockRecord::SEXES);
    }

    public function test_historical_species_are_selected_without_writing_and_edit_accepts_others(): void
    {
        foreach (['Dog' => 'Canine', 'Canine' => 'Canine', 'Cat' => 'Feline', 'Feline' => 'Feline', 'Rabbit' => 'Others'] as $original => $selection) {
            $pet = Pet::create(['name' => 'Buddy', 'species' => $original, 'sex' => 'Male']);
            $this->get(route('staff.pets.edit', $pet))->assertOk()->assertSee('value="'.$selection.'" selected', false);
            $this->assertSame($original, $pet->fresh()->species);
            $this->put(route('staff.pets.update', $pet), ['name' => 'Buddy', 'species' => 'Others', 'species_other' => 'Bird', 'sex' => 'Male'])->assertSessionHasNoErrors();
            $this->assertSame('Bird', $pet->fresh()->species);
        }
        $this->from(route('staff.pets.edit', $pet))->put(route('staff.pets.update', $pet), ['name' => '', 'species' => 'Others', 'species_other' => 'Rabbit', 'sex' => 'Male'])->assertSessionHasErrors('name');
        $this->get(route('staff.pets.edit', $pet))->assertSee('value="Others" selected', false)->assertSee('value="Rabbit"', false);
    }
}
