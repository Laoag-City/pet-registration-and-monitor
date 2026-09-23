<?php

namespace Tests\Feature;

use App\Models\{Pet, User};
use App\Services\{PetCareReminders, StaffDashboardAnalytics};
use App\Support\PetQrReference;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PetManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // The shared TestCase enforces SQLite :memory: before migrations or writes.
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertExitCode(0);
    }

    public static function managers(): array
    {
        return [['staff'], ['admin']];
    }

    private function pet(): Pet
    {
        $owner = User::factory()->create(['role' => 'user']);
        $pet = $owner->pets()->create(['name' => 'Buddy', 'species' => 'Dog', 'sex' => 'Male',
            'owner_name' => $owner->name, 'photo_path' => 'pet-photos/existing.jpg']);
        $pet->vaccinations()->create(['vaccine' => 'Rabies', 'administered_at' => today()->subYear(), 'next_due_at' => today()]);
        $pet->dewormingRecords()->create(['product' => 'Treatment', 'administered_at' => today()->subMonth(), 'next_due_at' => today()]);

        return $pet;
    }

    #[DataProvider('managers')]
    public function test_edit_preserves_identity_owner_medical_records_and_qr(string $role): void
    {
        $pet = $this->pet();
        $original = $pet->only(['id', 'code', 'owner_email', 'owner_name', 'photo_path']);
        $url = PetQrReference::url($pet);
        $this->actingAs(User::factory()->create(['role' => $role]));
        $this->get(route('staff.pets'))->assertOk()->assertSee(route('staff.pets.edit', $pet));
        $this->get(route('staff.pets.edit', $pet))->assertOk()->assertSee($pet->owner->name);
        $this->put(route('staff.pets.update', $pet), ['name' => 'Buddy Jr.', 'species' => 'Dog', 'sex' => 'Male', 'color' => 'Brown'])
            ->assertRedirect(route('pets.show', $pet))->assertSessionHas('success', 'Pet details updated successfully.');
        $this->assertSame($original, $pet->fresh()->only(array_keys($original)));
        $this->assertSame('Buddy Jr.', $pet->fresh()->name);
        $this->assertSame(1, $pet->vaccinations()->count());
        $this->assertSame(1, $pet->dewormingRecords()->count());
        $this->get($url)->assertOk()->assertSee('Buddy Jr.');
    }

    #[DataProvider('managers')]
    public function test_confirmed_removal_preserves_history_and_hides_active_pet(string $role): void
    {
        $pet = $this->pet();
        $owner = $pet->owner;
        $scan = PetQrReference::url($pet);
        $manager = User::factory()->create(['role' => $role]);
        $reminders = app(PetCareReminders::class);
        $reminders->generateForPet($pet);
        $notificationCount = $owner->notifications()->count();
        $this->assertGreaterThan(0, $notificationCount);
        $this->actingAs($manager);
        $this->get(route('staff.pets.remove', $pet))->assertOk()->assertSee('Remove Pet?')->assertSee('Cancel')->assertSee($owner->name);
        $this->get(route('pets.show', $pet))->assertOk(); // Cancel is a GET; nothing changed.
        $this->assertFalse($pet->fresh()->trashed());
        $this->delete(route('staff.pets.destroy', $pet))->assertSessionHasErrors('confirm_removal');
        $this->assertFalse($pet->fresh()->trashed());
        $this->delete(route('staff.pets.destroy', $pet), ['confirm_removal' => '1'])->assertRedirect(route('staff.pets'));
        $this->assertSoftDeleted('pets', ['id' => $pet->id]);
        $archived = Pet::withTrashed()->findOrFail($pet->id);
        $this->assertSame($owner->email, $archived->owner_email);
        $this->assertSame('pet-photos/existing.jpg', $archived->photo_path);
        $this->assertSame(1, $archived->vaccinations()->count());
        $this->assertSame(1, $archived->dewormingRecords()->count());
        foreach (['staff.pets', 'pets.index', 'vaccinations.index', 'deworming.index', 'qrcodes.index', 'staff.dashboard'] as $route) {
            $this->get(route($route))->assertOk()->assertDontSee('Buddy');
        }
        $this->get(route('pets.show', $pet))->assertNotFound();
        $this->get(route('staff.pets.edit', $pet))->assertNotFound();
        $this->get(route('vaccinations.edit', $archived->vaccinations()->first()))->assertNotFound();
        $this->get(route('deworming.edit', $archived->dewormingRecords()->first()))->assertNotFound();
        $this->put(route('vaccinations.update', $archived->vaccinations()->first()), [
            'vaccine' => 'Changed', 'administered_at' => today()->toDateString(),
        ])->assertNotFound();
        $this->get($scan)->assertNotFound();
        $this->assertSame(0, $reminders->generateForPet($pet));
        $this->assertSame(0, $reminders->generate());
        $this->assertCount(0, $reminders->schedules($owner));
        $this->assertSame(0, $reminders->activeNotifications($owner)->count());
        $this->assertSame($notificationCount, $owner->notifications()->count());
        $data = app(StaffDashboardAnalytics::class)->data($manager);
        foreach (['petCount', 'vaccinationCount', 'dewormingCount', 'attentionCount', 'yearCount'] as $key) {
            $this->assertSame(0, $data[$key]);
        }
        $this->assertSame([], $data['species']);
        $this->assertSame(0, $data['statuses']['vaccination']['total']);
        $this->assertSame(0, $data['statuses']['deworming']['total']);
        $this->actingAs($owner);
        foreach (['pets.index', 'dashboard', 'vaccinations.index', 'deworming.index', 'qrcodes.index', 'notifications.index'] as $route) {
            $this->get(route($route))->assertOk()->assertDontSee('Buddy');
        }
    }

    public function test_owner_cannot_edit_or_remove_even_with_manipulated_role(): void
    {
        $pet = $this->pet();
        $this->actingAs($pet->owner);
        $this->get(route('pets.show', $pet))->assertOk()->assertDontSee('Edit Pet')->assertDontSee('Remove Pet');
        $this->get(route('staff.pets.edit', $pet))->assertForbidden();
        $this->get(route('staff.pets.remove', $pet))->assertForbidden();
        $this->put(route('staff.pets.update', $pet), ['role' => 'admin', 'name' => 'Changed'])->assertForbidden();
        $this->delete(route('staff.pets.destroy', $pet), ['role' => 'staff', 'confirm_removal' => '1'])->assertForbidden();
        $this->assertSame('Buddy', $pet->fresh()->name);
        $this->assertFalse($pet->fresh()->trashed());
    }

    public function test_validation_and_photo_replacement(): void
    {
        Storage::fake('public');
        $pet = $this->pet();
        Storage::disk('public')->put($pet->photo_path, 'existing');
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $valid = ['name' => 'Buddy', 'species' => 'Dog', 'sex' => 'Male'];
        $this->put(route('staff.pets.update', $pet), $valid + ['owner_email' => 'other@example.test'])
            ->assertSessionHasErrors('owner_email');
        $this->put(route('staff.pets.update', $pet), $valid + ['date_of_birth' => today()->addDay()->toDateString(),
            'photo' => UploadedFile::fake()->create('bad.php', 1, 'application/x-php')])
            ->assertSessionHasErrors(['date_of_birth', 'photo']);
        $this->put(route('staff.pets.update', $pet), $valid + ['photo' => UploadedFile::fake()->createWithContent('new.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a4s8AAAAASUVORK5CYII='))])
            ->assertSessionHasNoErrors()->assertRedirect(route('pets.show', $pet));
        $this->assertNotSame($pet->photo_path, $pet->fresh()->photo_path);
        Storage::disk('public')->assertExists($pet->fresh()->photo_path);
        Storage::disk('public')->assertExists($pet->photo_path);
    }

    public function test_archived_ownership_survives_profile_email_change(): void
    {
        $pet = $this->pet();
        $owner = $pet->owner;
        $pet->delete();
        $this->actingAs($owner)->put(route('profile.update'), ['name' => 'Updated Owner', 'email' => 'updated@example.test'])
            ->assertSessionHasNoErrors();
        $this->assertSame('updated@example.test', Pet::withTrashed()->findOrFail($pet->id)->owner_email);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->delete(route('admin.users.destroy', $owner))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }
}
