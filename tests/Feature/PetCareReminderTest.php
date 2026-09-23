<?php

namespace Tests\Feature;

use App\Models\{Pet, User};
use App\Notifications\PetCareReminder;
use App\Services\PetCareReminders;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PetCareReminderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertExitCode(0);
        $this->travelTo(CarbonImmutable::parse('2026-09-20 12:00:00', 'UTC'));
    }

    private function owner(string $name = 'Care Owner'): User
    {
        return User::factory()->create(['role' => 'user', 'name' => $name]);
    }

    private function pet(User $owner, string $name = 'Buddy'): Pet
    {
        return $owner->pets()->create(['name' => $name, 'species' => 'Dog', 'sex' => 'Male', 'owner_name' => $owner->name]);
    }

    private function record(Pet $pet, string $type, ?string $due, string $administered = '2026-01-01', string $treatment = 'Rabies')
    {
        $values = ['administered_at' => $administered, 'next_due_at' => $due];

        return $type === 'vaccination'
            ? $pet->vaccinations()->create($values + ['vaccine' => $treatment])
            : $pet->dewormingRecords()->create($values + ['product' => $treatment]);
    }

    private function service(): PetCareReminders
    {
        return app(PetCareReminders::class);
    }

    public static function stages(): array
    {
        $cases = [];
        foreach (['vaccination', 'deworming'] as $type) {
            foreach ([7 => '7-day', 3 => '3-day', 0 => 'due-today', -1 => 'overdue'] as $days => $stage) {
                $cases[$type.' '.$stage] = [$type, $days, $stage];
            }
        }
        return $cases;
    }

    #[DataProvider('stages')]
    public function test_each_stage_reaches_only_correct_owner_and_is_idempotent(string $type, int $days, string $stage): void
    {
        $owner = $this->owner();
        $other = $this->owner('Private Other Owner');
        $record = $this->record($this->pet($owner), $type, today()->addDays($days)->toDateString());
        $this->artisan('pawid:care-reminders')->expectsOutput('Created 1 reminder(s).')->assertSuccessful();
        $notification = $owner->notifications()->sole();
        $this->assertSame($record->id, $notification->data['record_id']);
        $this->assertSame($stage, $notification->data['stage']);
        $this->assertSame($type, $notification->data['medical_type']);
        $this->assertCount(0, $other->notifications);
        $this->artisan('pawid:care-reminders')->expectsOutput('Created 0 reminder(s).')->assertSuccessful();
        $this->assertDatabaseCount('notifications', 1);
        $this->actingAs($owner)->get('/notifications')->assertOk()->assertSee('Buddy')->assertSee($days < 0 ? 'Overdue' : ($days === 0 ? 'Due Today' : 'Upcoming'));
        $this->get('/dashboard')->assertOk()->assertSee('Upcoming Pet Care')->assertSee('Buddy');
        $this->actingAs($other)->get('/notifications')->assertOk()->assertDontSee('Buddy')->assertSee('No upcoming vaccination or deworming reminders.');
    }

    public function test_more_than_seven_days_and_null_dates_do_not_notify_but_dashboard_shows_thirty_days(): void
    {
        $owner = $this->owner();
        foreach (['Eight Days' => 8, 'Ten Days' => 10, 'Thirty Days' => 30, 'Far Away' => 31] as $name => $days) {
            $this->record($this->pet($owner, $name), 'vaccination', today()->addDays($days)->toDateString());
        }
        $this->record($this->pet($owner, 'No Schedule'), 'deworming', null);
        $this->assertSame(0, $this->service()->generate());
        $this->actingAs($owner)->get('/dashboard')->assertOk()->assertViewHas('careSchedules', fn ($s) => $s->count() === 3)
            ->assertSee('10 days remaining')->assertSee('30 days remaining');
        $this->get('/notifications')->assertSee('No upcoming vaccination or deworming reminders.');
    }

    public function test_stage_progression_shows_only_latest_and_preserves_read_state(): void
    {
        $owner = $this->owner();
        $this->record($this->pet($owner), 'vaccination', '2026-09-27');
        $this->service()->generate();
        $first = $owner->notifications()->sole();
        $first->markAsRead();
        $this->service()->generate();
        $this->assertNotNull($first->fresh()->read_at);
        foreach (['2026-09-24', '2026-09-27', '2026-09-28', '2026-09-29'] as $date) {
            $this->travelTo(CarbonImmutable::parse($date.' 12:00:00', 'UTC'));
            $this->service()->generate();
            $this->assertSame(1, $this->service()->activeNotifications($owner)->count());
        }
        $this->assertDatabaseCount('notifications', 4);
        $this->actingAs($owner)->get('/notifications')->assertOk()->assertSee('is now overdue')->assertDontSee('7-day reminder')->assertSee('1 unread');
    }

    public function test_unique_database_channel_resists_repeated_delivery_and_keeps_read_timestamp(): void
    {
        $owner = $this->owner();
        $this->record($this->pet($owner), 'vaccination', '2026-09-27');
        $notification = new PetCareReminder($this->service()->schedules($owner)->sole(), '7-day', 1);
        $owner->notifyNow($notification);
        $stored = $owner->notifications()->sole();
        $stored->markAsRead();
        $readAt = $stored->fresh()->read_at->toDateTimeString();
        $owner->notifyNow($notification);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame($readAt, $stored->fresh()->read_at->toDateTimeString());
    }

    public static function types(): array
    {
        return [['vaccination'], ['deworming']];
    }

    #[DataProvider('types')]
    public function test_new_record_supersedes_old_due_date_and_backdated_entry_does_not(string $type): void
    {
        $owner = $this->owner();
        $pet = $this->pet($owner);
        $old = $this->record($pet, $type, '2026-09-15');
        $this->service()->generate();
        $new = $this->record($pet, $type, '2027-09-20', '2026-09-20');
        $this->record($pet, $type, '2026-09-10', '2025-01-01');
        $this->assertSame($new->id, $this->service()->schedules($owner)->sole()['record']->id);
        // No generator run needed to hide old schedules.
        $this->assertSame(0, $this->service()->activeNotifications($owner)->count());
        $this->actingAs($owner)->get('/notifications')->assertOk()->assertDontSee('September 15')->assertSee('No upcoming');
        $this->get('/dashboard')->assertViewHas('careSchedules', fn ($s) => $s->isEmpty());
        $this->get($type === 'vaccination' ? '/vaccinations' : '/deworming')->assertOk()->assertSee('Previous record')->assertDontSee('Superseded')
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 3 && $stats['overdue'] === 0);
        $this->assertDatabaseHas($old->getTable(), ['id' => $old->id, 'next_due_at' => '2026-09-15 00:00:00']);
        $this->assertDatabaseCount('notifications', 1);
    }

    #[DataProvider('types')]
    public function test_same_day_new_record_with_no_due_date_supersedes_previous_schedule(string $type): void
    {
        $owner = $this->owner();
        $pet = $this->pet($owner);
        $this->record($pet, $type, '2026-09-20');
        $this->service()->generate();
        $this->record($pet, $type, null);
        $this->assertTrue($this->service()->schedules($owner)->isEmpty());
        $this->assertSame(0, $this->service()->activeNotifications($owner)->count());
    }

    public function test_different_vaccines_have_separate_schedules_with_case_and_space_normalization(): void
    {
        $owner = $this->owner();
        $pet = $this->pet($owner);
        $this->record($pet, 'vaccination', '2026-09-15', '2026-01-01', 'Rabies');
        $this->record($pet, 'vaccination', '2027-09-20', '2026-09-20', ' RABIES ');
        $this->record($pet, 'vaccination', '2026-09-23', '2026-01-01', 'Combination');
        $this->assertSame(1, $this->service()->generate());
        $this->actingAs($owner)->get('/notifications')->assertOk()->assertSee('Combination')->assertDontSee('September 15');
    }

    public static function managersAndTypes(): array
    {
        return [['staff', 'vaccination'], ['admin', 'vaccination'], ['staff', 'deworming'], ['admin', 'deworming']];
    }

    #[DataProvider('managersAndTypes')]
    public function test_staff_correction_immediately_replaces_wrong_reminder_and_avoids_duplicates(string $role, string $type): void
    {
        $owner = $this->owner();
        $record = $this->record($this->pet($owner), $type, '2026-09-15');
        $this->service()->generate();
        $oldId = $owner->notifications()->sole()->id;
        $routeType = $type === 'vaccination' ? 'vaccinations' : 'deworming';
        $field = $type === 'vaccination' ? 'vaccine' : 'product';
        $values = [$field => 'Rabies', 'administered_at' => '2026-01-01', 'next_due_at' => '2026-09-23'];
        $this->actingAs(User::factory()->create(['role' => $role]))->put('/staff/'.$routeType.'/'.$record->id, $values)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('2026-09-23', $this->service()->activeNotifications($owner)->sole()->data['due_date']);
        $this->put('/staff/'.$routeType.'/'.$record->id, $values)->assertSessionHasNoErrors();
        $this->assertSame(0, $this->service()->generate());
        $this->assertDatabaseCount('notifications', 2);
        $this->actingAs($owner)->get('/notifications')->assertOk()->assertSee('September 23')->assertDontSee('September 15');
        $this->patch('/notifications/'.$oldId.'/read')->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => $role]))->put('/staff/'.$routeType.'/'.$record->id, array_replace($values, ['next_due_at' => '2026-10-20']))->assertSessionHasNoErrors();
        $this->assertSame(0, $this->service()->activeNotifications($owner)->count());
    }

    #[DataProvider('types')]
    public function test_staff_record_creation_generates_due_reminder_without_dashboard_visit(string $type): void
    {
        $owner = $this->owner();
        $pet = $this->pet($owner);
        $routeType = $type === 'vaccination' ? 'vaccinations' : 'deworming';
        $field = $type === 'vaccination' ? 'vaccine' : 'product';
        $this->actingAs(User::factory()->create(['role' => 'staff']))->post('/staff/'.$routeType, [
            'owner_id' => $owner->id, 'pet_id' => $pet->id, $field => 'Treatment',
            'administered_at' => '2026-09-20', 'next_due_at' => '2026-09-27',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('7-day', $owner->notifications()->sole()->data['stage']);
    }

    public function test_owner_only_read_actions_and_csrf_protection(): void
    {
        $owner = $this->owner();
        $other = $this->owner();
        $this->record($this->pet($owner), 'vaccination', '2026-09-23');
        $this->record($this->pet($other, 'Other Private Pet'), 'deworming', '2026-09-23');
        $this->service()->generate();
        $ownId = $owner->notifications()->sole()->id;
        $otherId = $other->notifications()->sole()->id;
        $this->get('/notifications')->assertRedirect('/login');
        $this->patch('/notifications/read-all')->assertRedirect('/login');
        $this->actingAs($owner)->get('/notifications?owner_id='.$other->id)->assertOk()->assertDontSee('Other Private Pet');
        $this->patch('/notifications/'.$otherId.'/read')->assertNotFound();
        $this->patch('/notifications/'.$ownId.'/read', ['owner_id' => $other->id])->assertRedirect('/notifications');
        $this->assertNotNull($owner->notifications()->sole()->read_at);
        $this->assertNull($other->notifications()->sole()->read_at);
        $owner->notifications()->update(['read_at' => null]);
        $this->patch('/notifications/read-all', ['owner_id' => $other->id])->assertRedirect('/notifications');
        $this->assertNotNull($owner->notifications()->sole()->read_at);
        $this->assertNull($other->notifications()->sole()->read_at);
        $this->post('/notifications', ['pet_id' => 1])->assertStatus(405);
        foreach (['staff', 'admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get('/notifications')->assertForbidden();
            $this->patch('/notifications/'.$ownId.'/read')->assertForbidden();
            $this->patch('/notifications/read-all')->assertForbidden();
        }
        $this->actingAs($owner);
        $this->app->instance('env', 'local');
        $this->patch('/notifications/'.$ownId.'/read')->assertStatus(419);
        $this->patch('/notifications/read-all')->assertStatus(419);
    }

    public function test_profile_email_update_preserves_reminders_and_current_pet_names_are_used(): void
    {
        $owner = $this->owner();
        $pet = $this->pet($owner);
        $this->record($pet, 'vaccination', '2026-09-23');
        $this->service()->generate();
        $id = $owner->notifications()->sole()->id;
        $this->actingAs($owner)->put('/profile', ['name' => 'New Owner Name', 'email' => 'new-care@example.test', 'contact_number' => null, 'date_of_birth' => null])->assertSessionHasNoErrors();
        $pet->update(['name' => 'Updated Buddy']);
        $this->get('/notifications')->assertOk()->assertSee('Updated Buddy');
        $this->assertSame($id, $this->service()->activeNotifications($owner->fresh())->sole()->id);
        $this->assertSame(0, $this->service()->generate());
    }

    public function test_ownership_changes_deleted_records_and_non_owner_roles_hide_reminders(): void
    {
        $owner = $this->owner();
        $other = $this->owner();
        $pet = $this->pet($owner);
        $record = $this->record($pet, 'vaccination', '2026-09-23');
        $this->service()->generate();
        $pet->update(['owner_email' => $other->email]);
        $this->assertSame(0, $this->service()->activeNotifications($owner)->count());
        $this->assertSame(1, $this->service()->generate());
        $this->assertSame(1, $this->service()->activeNotifications($other)->count());
        $record->delete();
        $this->assertSame(0, $this->service()->activeNotifications($other)->count());
        $this->record($pet, 'vaccination', '2026-09-23');
        $other->update(['role' => 'admin']);
        $this->assertSame(0, $this->service()->generate());
        $this->assertSame(0, $this->service()->activeNotifications($other)->count());
    }

    public function test_timezone_calendar_boundaries_and_missed_run_catchup(): void
    {
        config(['app.timezone' => 'Asia/Manila']);
        $owner = $this->owner();
        $this->record($this->pet($owner), 'vaccination', '2026-09-27');
        $this->travelTo(CarbonImmutable::parse('2026-09-19 15:59:59', 'UTC'));
        $this->assertSame(0, $this->service()->generate());
        $this->travelTo(CarbonImmutable::parse('2026-09-19 16:00:00', 'UTC'));
        $this->assertSame(1, $this->service()->generate());
        $this->assertSame(7, $this->service()->schedules($owner)->sole()['days']);
        $this->travelTo(CarbonImmutable::parse('2026-09-25 16:00:00', 'UTC'));
        $this->assertSame(1, $this->service()->generate());
        $this->assertSame('3-day', $this->service()->activeNotifications($owner)->sole()->data['stage']);
        $this->travelTo(CarbonImmutable::parse('2026-09-26 16:00:00', 'UTC'));
        $this->assertSame(0, $this->service()->schedules($owner)->sole()['days']);
        $this->service()->generate();
        $this->assertSame('due-today', $this->service()->activeNotifications($owner)->sole()->data['stage']);
    }

    public function test_dry_run_and_repeated_page_visits_never_generate_notifications(): void
    {
        $owner = $this->owner();
        $this->record($this->pet($owner), 'vaccination', '2026-09-23');
        $this->artisan('pawid:care-reminders', ['--dry-run' => true])->expectsOutput('Would create 1 reminder(s). No notifications written.')->assertSuccessful();
        $this->actingAs($owner);
        foreach (['/dashboard', '/notifications', '/dashboard', '/notifications'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_failed_reminder_write_rolls_back_staff_medical_correction(): void
    {
        $owner = $this->owner();
        $record = $this->record($this->pet($owner), 'vaccination', '2026-12-20');
        $before = $record->fresh()->getRawOriginal();
        DB::connection()->beforeExecuting(function ($query): void {
            if (str_starts_with($query, 'insert into "notifications"')) {
                throw new \Illuminate\Database\QueryException('sqlite', $query, [], new \RuntimeException('Synthetic reminder failure'));
            }
        });
        $this->actingAs(User::factory()->create(['role' => 'staff']))->put('/staff/vaccinations/'.$record->id, [
            'vaccine' => 'Rabies', 'administered_at' => '2026-01-01', 'next_due_at' => '2026-09-23',
        ])->assertStatus(500);
        $this->assertSame($before, $record->fresh()->getRawOriginal());
        $this->assertDatabaseCount('notifications', 0);
    }
}
