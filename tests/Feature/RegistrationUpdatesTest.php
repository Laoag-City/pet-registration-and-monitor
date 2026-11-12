<?php

namespace Tests\Feature;

use App\Models\{Pet, User};
use App\Support\{LaoagBarangays, PetQrReference};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationUpdatesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertExitCode(0);
    }

    public static function managers(): array
    {
        return [['staff'], ['admin']];
    }

    private function payload(User $owner): array
    {
        return ['owner_id' => $owner->id, 'name' => 'Buddy', 'species' => 'Canine', 'sex' => 'Male'];
    }

    #[DataProvider('managers')]
    public function test_receipt_registration_editing_photo_and_privacy(string $role): void
    {
        Storage::fake('public');
        $owner = User::factory()->create(['role' => 'user']);
        $this->actingAs(User::factory()->create(['role' => $role]));
        $this->get(route('staff.pets.create', ['client' => $owner->id]))->assertOk()->assertSee('name="receipt_number"', false);
        $this->post(route('staff.pets.store'), $this->payload($owner) + [
            'receipt_number' => '  OR-2026/00125 (A)  ',
            'photo' => UploadedFile::fake()->createWithContent('pet.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a4s8AAAAASUVORK5CYII=')),
        ])->assertSessionHasNoErrors();
        $pet = Pet::sole();
        $this->assertSame('OR-2026/00125 (A)', $pet->receipt_number);
        Storage::disk('public')->assertExists($pet->photo_path);
        $this->get(route('pets.show', $pet))->assertOk()->assertSee($pet->receipt_number);
        $this->get(route('staff.users.show', $owner))->assertOk()->assertSee($pet->receipt_number);
        $this->get(PetQrReference::url($pet))->assertOk()->assertDontSee($pet->receipt_number);
        $this->put(route('staff.pets.update', $pet), ['name' => 'Buddy', 'species' => 'Canine', 'sex' => 'Male', 'receipt_number' => ' OR-2 '])->assertSessionHasNoErrors();
        $this->assertSame('OR-2', $pet->fresh()->receipt_number);
        $this->assertSame($pet->photo_path, $pet->fresh()->photo_path);
        $this->get(route('staff.pets.edit', $pet))->assertOk()->assertSee('value="OR-2"', false);
        $this->put(route('staff.pets.update', $pet), ['name' => 'Buddy', 'species' => 'Canine', 'sex' => 'Male', 'receipt_number' => '  '])->assertSessionHasNoErrors();
        $this->assertNull($pet->fresh()->receipt_number);
    }

    public function test_optional_receipts_and_existing_pets(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->post(route('staff.pets.store'), $this->payload($owner))->assertSessionHasNoErrors();
        $pet = Pet::sole();
        $this->assertNull($pet->receipt_number);
        $this->get(route('pets.show', $pet))->assertOk()->assertSee('Not Provided');
        $this->put(route('staff.pets.update', $pet), ['name' => 'Updated', 'species' => 'Canine', 'sex' => 'Male'])->assertSessionHasNoErrors();
        $this->assertNull($pet->fresh()->receipt_number);
    }

    public function test_receipt_validation_old_input_and_escaping(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $url = route('staff.pets.create', ['client' => $owner->id]);
        foreach ([str_repeat('A', 101), ['bad'], UploadedFile::fake()->create('receipt.pdf')] as $invalid) {
            $this->from($url)->post(route('staff.pets.store'), $this->payload($owner) + ['receipt_number' => $invalid])->assertSessionHasErrors('receipt_number');
        }
        $this->assertSame(0, Pet::count());
        $this->from($url)->post(route('staff.pets.store'), array_replace($this->payload($owner), ['name' => '', 'receipt_number' => 'OR-keep']))->assertSessionHasErrors('name')->assertSessionHasInput('receipt_number', 'OR-keep');
        $this->get($url)->assertSee('value="OR-keep"', false);
        $receipt = '<script>alert("x")</script>';
        $this->post(route('staff.pets.store'), $this->payload($owner) + ['receipt_number' => $receipt])->assertSessionHasNoErrors();
        $pet = Pet::sole();
        $this->get(route('pets.show', $pet))->assertSee(e($receipt), false)->assertDontSee($receipt, false);
        $this->put(route('staff.pets.update', $pet), ['name' => 'Buddy', 'species' => 'Canine', 'sex' => 'Male', 'receipt_number' => str_repeat('A', 101)])->assertSessionHasErrors('receipt_number');
        $this->assertSame($receipt, $pet->fresh()->receipt_number);
    }

    public function test_owner_cannot_create_or_change_receipts(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $pet = $owner->pets()->create(['name' => 'Buddy', 'species' => 'Canine', 'sex' => 'Male', 'receipt_number' => 'OR-original']);
        $this->actingAs($owner);
        $this->post(route('staff.pets.store'), $this->payload($owner) + ['receipt_number' => 'OR-fake'])->assertForbidden();
        $this->put(route('staff.pets.update', $pet), ['receipt_number' => 'OR-fake', 'role' => 'admin'])->assertForbidden();
        $this->assertSame('OR-original', $pet->fresh()->receipt_number);
        $this->get(route('pets.show', $pet))->assertOk()->assertSee('OR-original');
        $this->actingAs(User::factory()->create(['role' => 'user']))->get(route('pets.show', $pet))->assertForbidden();
    }

    public function test_registration_uses_shared_barangays_and_saves_valid_selection(): void
    {
        $response = $this->get(route('register'))->assertOk();
        foreach (LaoagBarangays::options() as $code => $label) {
            $response->assertSee('data-psgc="'.$code.'"', false)->assertSee($label);
        }
        $payload = ['name' => 'Owner', 'email' => 'owner@example.test', 'contact_number' => '09123456789', 'date_of_birth' => '1990-01-01', 'password' => 'secure-password', 'password_confirmation' => 'secure-password'];
        $this->post(route('register.store'), $payload + ['barangay' => 'Invented Barangay'])->assertSessionHasErrors('barangay');
        $barangay = LaoagBarangays::labels()[0];
        $this->post(route('register.store'), $payload + ['barangay' => $barangay])->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => $payload['email'], 'barangay' => $barangay, 'role' => 'user']);
    }

    public function test_management_pagination_preserves_filters_and_search(): void
    {
        $owners = User::factory()->count(23)->create(['role' => 'user', 'name' => 'Matching Owner']);
        foreach ($owners as $owner) {
            $owner->pets()->create(['name' => 'Matching Pet', 'species' => 'Canine', 'sex' => 'Male']);
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['staff.users', 'admin.users'] as $route) {
            $this->get(route($route, ['q' => 'Matching', 'role' => 'user', 'page' => 2]))->assertOk()->assertViewHas('users', fn ($rows) => $rows->perPage() === 10 && $rows->total() === 23 && $rows->count() === 10 && str_contains($rows->nextPageUrl(), 'q=Matching') && str_contains($rows->nextPageUrl(), 'role=user'));
        }
        $this->get(route('staff.pets', ['q' => 'Matching', 'species' => 'Canine', 'page' => 3]))->assertOk()->assertViewHas('pets', fn ($rows) => $rows->perPage() === 10 && $rows->total() === 23 && $rows->count() === 3 && str_contains($rows->previousPageUrl(), 'species=Canine') && str_contains($rows->previousPageUrl(), 'q=Matching'));
        $this->get(route('staff.pets', ['species' => 'Feline']))->assertViewHas('pets', fn ($rows) => $rows->total() === 0);
    }
}
