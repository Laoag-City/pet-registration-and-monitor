<?php

namespace Tests\Feature;

use App\Models\{User, Pet, Vaccination, DewormingRecord};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MedicalWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertExitCode(0);
    }

    public static function managersAndTypes(): array
    {
        return [
            'staff vaccination' => ['staff', 'vaccinations'],
            'admin vaccination' => ['admin', 'vaccinations'],
            'staff deworming' => ['staff', 'deworming'],
            'admin deworming' => ['admin', 'deworming'],
        ];
    }

    public static function recordTypes(): array
    {
        return [['vaccinations'], ['deworming']];
    }

    private function owner(string $name = 'Owner Alpha'): User
    {
        return User::factory()->create(['name' => $name, 'role' => 'user', 'contact_number' => '09171234567']);
    }

    private function pet(User $owner, string $name = 'Alpha Pet'): Pet
    {
        return $owner->pets()->create(['name' => $name, 'species' => 'Dog', 'breed' => 'Mixed', 'sex' => 'Female', 'owner_name' => $owner->name]);
    }

    private function payload(string $type, User $owner, Pet $pet): array
    {
        $values = ['owner_id' => $owner->id, 'pet_id' => $pet->id, 'administered_at' => '2026-01-10', 'next_due_at' => '2026-12-10', 'notes' => 'Workflow test notes'];

        return $type === 'vaccinations'
            ? $values + ['vaccine' => 'Rabies Test', 'veterinarian' => 'Test Veterinarian']
            : $values + ['product' => 'Dewormer Test', 'weight_kg' => '12.50'];
    }

    private function table(string $type): string
    {
        return $type === 'vaccinations' ? 'vaccinations' : 'deworming_records';
    }

    private function record(string $type, Pet $pet)
    {
        return $type === 'vaccinations'
            ? $pet->vaccinations()->create(['vaccine' => 'Original vaccine', 'administered_at' => '2015-01-01'])
            : $pet->dewormingRecords()->create(['product' => 'Original product', 'administered_at' => '2015-01-01']);
    }

    #[DataProvider('managersAndTypes')]
    public function test_login_owner_pet_details_review_and_save(string $role, string $type): void
    {
        $manager = User::factory()->create(['role' => $role]);
        $owner = $this->owner();
        $pet = $this->pet($owner);
        $this->pet($this->owner('Owner Beta'), 'Other Owner Pet');
        $values = $this->payload($type, $owner, $pet);

        $this->post('/login', ['email' => $manager->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->get(route($type.'.index'))->assertOk()->assertSee(route($type.'.create'));
        $this->get(route($type.'.create'))->assertOk()->assertSee('Select Owner')->assertDontSee('name="pet_id"', false)->assertDontSee('name="vaccine"', false)->assertDontSee('name="product"', false);
        $this->get(route($type.'.create', ['q' => $owner->email]))->assertOk()->assertSee($owner->name)->assertDontSee('Owner Beta');
        $this->get(route($type.'.create', ['owner_id' => $owner->id]))->assertOk()->assertSee('Select Pet')->assertSee($pet->name)->assertDontSee('Other Owner Pet');
        $this->get(route($type.'.create', ['owner_id' => $owner->id, 'pet_id' => $pet->id]))->assertOk()->assertSee('Review Record')->assertSee($owner->name)->assertSee($pet->name);
        $this->post(route($type.'.review'), $values)->assertOk()->assertSee('Review and Save')->assertSee('Workflow test notes');
        $this->assertDatabaseCount($this->table($type), 0);
        $this->post(route($type.'.review'), $values + ['edit_details' => '1'])->assertOk()->assertSee('Review Record')->assertSee('Workflow test notes');
        $this->post(route($type.'.store'), $values)->assertSessionHasNoErrors()->assertRedirect(route($type.'.index'));
        $this->assertDatabaseCount($this->table($type), 1);
        $this->assertDatabaseHas($this->table($type), ['pet_id' => $pet->id, 'notes' => 'Workflow test notes']);
    }

    #[DataProvider('managersAndTypes')]
    public function test_manager_can_edit_old_record_without_moving_it(string $role, string $type): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));
        $owner = $this->owner();
        $pet = $this->pet($owner);
        $record = $this->record($type, $pet);
        $values = $this->payload($type, $owner, $pet);
        unset($values['owner_id'], $values['pet_id']);

        $this->get(route($type.'.index'))->assertOk()->assertSee(route($type.'.edit', $record));
        $this->get(route($type.'.edit', $record))->assertOk()->assertSee($owner->name)->assertSee($pet->name)
            ->assertSee('value="2015-01-01"', false)->assertDontSee('name="pet_id"', false)->assertDontSee('name="owner_id"', false);
        $this->put(route($type.'.update', $record), $values)->assertSessionHasNoErrors()->assertRedirect(route($type.'.index'))
            ->assertSessionHas('success', ($type === 'vaccinations' ? 'Vaccination' : 'Deworming').' record updated successfully.');
        $this->assertSame($pet->id, $record->fresh()->pet_id);
        $this->assertSame('Workflow test notes', $record->fresh()->notes);
    }

    #[DataProvider('recordTypes')]
    public function test_owner_and_guest_cannot_use_creation_or_edit_urls(string $type): void
    {
        $owner = $this->owner();
        $pet = $this->pet($owner);
        $record = $this->record($type, $pet);
        $values = $this->payload($type, $owner, $pet);
        foreach (['create' => [], 'edit' => $record] as $action => $parameters) {
            $this->get(route($type.'.'.$action, $parameters))->assertRedirect('/login');
        }
        $this->put(route($type.'.update', $record), [])->assertRedirect('/login');
        $this->actingAs($owner);
        $this->get(route($type.'.create'))->assertForbidden();
        $this->get(route($type.'.edit', $record))->assertForbidden();
        $this->put(route($type.'.update', $record), [])->assertForbidden();
        $this->post(route($type.'.review'), $values)->assertForbidden();
        $this->post(route($type.'.store'), $values)->assertForbidden();
        $this->get(route($type.'.index'))->assertOk()->assertDontSee(route($type.'.edit', $record))->assertDontSee(route($type.'.create'));
        $this->assertDatabaseCount($this->table($type), 1);
        $this->assertNull($record->fresh()->notes);
    }

    #[DataProvider('managersAndTypes')]
    public function test_mismatched_owner_and_pet_are_rejected_at_every_step(string $role, string $type): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));
        $owner = $this->owner();
        $otherPet = $this->pet($this->owner('Owner Beta'), 'Other Owner Pet');
        $this->get(route($type.'.create', ['owner_id' => $owner->id, 'pet_id' => $otherPet->id]))->assertNotFound();
        foreach (['review', 'store'] as $action) {
            $this->post(route($type.'.'.$action), $this->payload($type, $owner, $otherPet))->assertSessionHasErrors('pet_id');
        }
        $this->assertDatabaseCount($this->table($type), 0);
    }

    #[DataProvider('recordTypes')]
    public function test_edit_rejects_relationship_tampering_and_invalid_fields(string $type): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $owner = $this->owner();
        $pet = $this->pet($owner);
        $record = $this->record($type, $pet);
        $other = $this->owner('Other Owner');
        $otherPet = $this->pet($other, 'Other Pet');
        $values = $this->payload($type, $other, $otherPet);
        $this->put(route($type.'.update', $record), $values)->assertSessionHasErrors(['owner_id', 'pet_id']);
        unset($values['owner_id'], $values['pet_id']);
        $values['administered_at'] = '2999-01-01';
        $this->put(route($type.'.update', $record), $values)->assertSessionHasErrors('administered_at');
        $this->assertSame($pet->id, $record->fresh()->pet_id);
        $this->assertSame('2015-01-01', $record->fresh()->administered_at->toDateString());
        $this->get(route($type.'.edit', 999999))->assertNotFound();
        $this->put(route($type.'.update', 999999), $values)->assertNotFound();
    }

    #[DataProvider('recordTypes')]
    public function test_owner_search_by_name_email_contact_partial_and_pagination(string $type): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff', 'name' => 'Hidden Staff Client']));
        User::factory()->create(['role' => 'admin', 'name' => 'Hidden Admin Client']);
        $owner = $this->owner('Searchable Owner');
        $owner->update(['contact_number' => '09987654321']);
        for ($i = 0; $i < 13; $i++) { $this->owner(sprintf('Page Owner %02d', $i)); }
        foreach (['searchable', $owner->email, '765432'] as $search) {
            $response = $this->get(route($type.'.create', ['q' => $search]))->assertOk();
            $response->assertViewHas('owners', fn ($owners) => $owners->contains('id', $owner->id));
            $response->assertSee('Searchable Owner');
        }
        $response = $this->get(route($type.'.create', ['q' => 'Owner']))->assertOk()->assertSee('page=2', false);
        $response->assertViewHas('owners', fn ($owners) => $owners->count() === 12 && $owners->total() === 14);
        $this->get(route($type.'.create', ['q' => 'Owner', 'page' => 2]))->assertOk()->assertViewHas('owners', fn ($owners) => $owners->count() === 2);
        $this->get(route($type.'.create', ['q' => 'NoMatchingClient']))->assertOk()->assertSee('No owners found.');
        $this->get(route($type.'.create', ['q' => 'Hidden']))->assertOk()->assertViewHas('owners', fn ($owners) => $owners->isEmpty());
    }

    #[DataProvider('recordTypes')]
    public function test_empty_owner_pets_and_invalid_selections(string $type): void
    {
        $manager = User::factory()->create(['role' => 'admin']);
        $this->actingAs($manager);
        $owner = $this->owner();
        $this->get(route($type.'.create', ['owner_id' => $owner->id]))->assertOk()->assertSee('No registered pets found for this owner.')->assertDontSee('name="vaccine"', false);
        $this->get(route($type.'.create', ['owner_id' => $manager->id]))->assertNotFound();
        $this->get(route($type.'.create', ['owner_id' => 999999]))->assertNotFound();
        $this->get(route($type.'.create', ['pet_id' => 999999]))->assertSessionHasErrors('owner_id');
        $pet = $this->pet($owner);
        $values = $this->payload($type, $owner, $pet);
        $values['owner_id'] = $manager->id;
        $this->post(route($type.'.store'), $values)->assertSessionHasErrors('owner_id');
    }

    #[DataProvider('recordTypes')]
    public function test_validation_returns_to_get_form_with_details_preserved(string $type): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $owner = $this->owner();
        $pet = $this->pet($owner);
        $values = $this->payload($type, $owner, $pet);
        $values['administered_at'] = '2999-01-01';
        $response = $this->from(route($type.'.review'))->post(route($type.'.store'), $values)->assertSessionHasErrors('administered_at');
        $response->assertRedirect(route($type.'.create', ['owner_id' => $owner->id, 'pet_id' => $pet->id]));
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('Workflow test notes')->assertSee('The administered at field must be a date before or equal to today.');
        $this->assertDatabaseCount($this->table($type), 0);
    }

    #[DataProvider('recordTypes')]
    public function test_changed_ownership_after_review_is_rechecked_on_save(string $type): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $owner = $this->owner();
        $pet = $this->pet($owner);
        $values = $this->payload($type, $owner, $pet);
        $this->post(route($type.'.review'), $values)->assertOk();
        $pet->update(['owner_email' => $this->owner('New Owner')->email]);
        $this->post(route($type.'.store'), $values)->assertSessionHasErrors('pet_id');
        $this->assertDatabaseCount($this->table($type), 0);
    }
}
