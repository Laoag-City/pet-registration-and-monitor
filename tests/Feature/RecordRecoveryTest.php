<?php

namespace Tests\Feature;

use App\Models\{User, Vaccination, DewormingRecord, AnimalHealthMonitoringRecord, RoutineServiceRecord};
use App\Services\{RecordDeletion, PetCareReminders};
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecordRecoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::unprepared(file_get_contents(base_path('tests/Fixtures/schema.sql')));
    }

    public static function types(): array
    {
        return [['vaccinations', Vaccination::class, 'vaccine'], ['deworming', DewormingRecord::class, 'product'],
            ['animal-health-monitoring', AnimalHealthMonitoringRecord::class, null], ['routine-services', RoutineServiceRecord::class, null]];
    }

    #[DataProvider('types')]
    public function test_restore_is_admin_only_confirmed_audited_and_preserves_original_values(string $type, string $model, ?string $field): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $staff = User::factory()->create(['role' => 'staff']);
        $pet = $owner->pets()->create(['name' => 'Recovery pet', 'species' => 'Dog', 'sex' => 'Male']);
        $row = $model::create($field ? ['pet_id' => $pet->id, $field => 'Treatment', 'administered_at' => today()->subMonth(), 'next_due_at' => null] :
            ['visit_date' => today(), 'barangay' => 'Bgy. No. 1, San Lorenzo', 'farmer_name' => 'Recovery farmer', 'species' => 'Bovine', 'sex' => 'Male', 'activity' => 'Visit', 'number_of_heads' => 1]);
        $original = $row->getAttributes();
        app(RecordDeletion::class)->delete($row, $staff);
        $url = route('admin.record-archive.restore', [$type, $row->id]);
        foreach ([$owner, $staff] as $actor) {
            $this->actingAs($actor)->get(route('admin.record-archive', $type))->assertForbidden();
            $this->get(route('admin.record-audit'))->assertForbidden();
            $this->post($url, ['confirm_restore' => 1, 'reason' => 'Wrong record selected'])->assertForbidden();
        }
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.record-archive', $type))->assertOk()->assertSee('Restore Record');
        $this->post($url, [])->assertSessionHasErrors(['reason', 'confirm_restore']);
        $this->assertSoftDeleted($row);
        $this->post($url, ['confirm_restore' => 1, 'reason' => 'Wrong record selected'])->assertRedirect();
        $restored = $model::findOrFail($row->id);
        foreach (array_diff_key($original, array_flip(['created_at', 'updated_at', 'deleted_at'])) as $key => $value) {
            $this->assertEquals($value, $restored->getRawOriginal($key));
        }
        $this->assertDatabaseHas('record_change_audits', ['actor_id' => $admin->id, 'record_id' => $row->id, 'action' => 'restored', 'reason' => 'Wrong record selected']);
        $this->assertSame(0, app(PetCareReminders::class)->generate());
        $this->get(route('admin.record-audit'))->assertOk()->assertSee('restored')->assertSee('soft_delete');
        $this->post($url, ['confirm_restore' => 1, 'reason' => 'Repeat request'])->assertNotFound();
        $this->get('/admin/record-archive/not-a-table')->assertNotFound();
    }

    #[DataProvider('types')]
    public function test_recovery_requires_csrf(string $type, string $model, ?string $field): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->app->instance('env', 'local');
        $this->post(route('admin.record-archive.restore', [$type, 999]), ['confirm_restore' => 1, 'reason' => 'Test restoration'])->assertStatus(419);
    }

    public function test_medical_edits_and_done_are_audited(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $staff = User::factory()->create(['role' => 'staff']);
        $pet = $owner->pets()->create(['name' => 'Audit pet', 'species' => 'Dog', 'sex' => 'Male']);
        $this->actingAs($staff);
        foreach (array_slice(self::types(), 0, 2) as [$type, $model, $field]) {
            $row = $model::create(['pet_id' => $pet->id, $field => 'Treatment', 'administered_at' => today()->subMonth(), 'next_due_at' => today()]);
            $this->put(route($type.'.update', $row), [$field => 'Corrected treatment', 'administered_at' => today()->subMonth()->toDateString(), 'next_due_at' => today()->toDateString(), 'audit_reason' => 'Corrected treatment name'])->assertSessionHasNoErrors()->assertRedirect();
            $event = DB::table('record_change_audits')->where('record_table', $row->getTable())->where('action', 'updated')->sole();
            $this->assertSame($staff->id, $event->actor_id);
            $this->assertSame('Treatment', json_decode($event->changes, true)[$field]['before']);
            $this->assertSame('Corrected treatment', json_decode($event->changes, true)[$field]['after']);
            $this->post(route($type.'.done', $row), ['confirm_completion' => 1, 'administered_at' => today()->toDateString()])->assertSessionHasNoErrors()->assertRedirect();
            $this->assertDatabaseHas('record_change_audits', ['record_table' => $row->getTable(), 'record_id' => $row->id, 'action' => 'schedule_completed']);
        }
    }

    public function test_dated_recovery_refreshes_reminders_and_archived_pets_stay_archived(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'admin']);
        $pet = $owner->pets()->create(['name' => 'Scheduled recovery', 'species' => 'Dog', 'sex' => 'Male']);
        $row = Vaccination::create(['pet_id' => $pet->id, 'vaccine' => 'Test', 'administered_at' => today()->subMonth(), 'next_due_at' => today()->addDay()]);
        app(RecordDeletion::class)->delete($row, $admin);
        $url = route('admin.record-archive.restore', ['vaccinations', $row->id]);
        $pet->delete();
        $this->actingAs($admin)->post($url, ['confirm_restore' => 1, 'reason' => 'Correction requested'])->assertSessionHasErrors('restore');
        $this->assertSoftDeleted($row);
        $this->assertSoftDeleted($pet);
        $pet->restore(); // Isolated fixture only; no application pet-recovery route is added.
        $this->post($url, ['confirm_restore' => 1, 'reason' => 'Correction requested'])->assertRedirect();
        $this->assertSame(1, app(PetCareReminders::class)->activeNotifications($owner)->count());
        $this->assertSame(0, app(PetCareReminders::class)->generate());
    }
}
