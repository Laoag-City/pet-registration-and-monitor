<?php

namespace Tests\Feature;

use App\Models\{User, Vaccination, DewormingRecord, AnimalHealthMonitoringRecord, RoutineServiceRecord};
use App\Services\PetCareReminders;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecordDeletionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::unprepared(file_get_contents(base_path('tests/Fixtures/schema.sql')));
    }

    public static function records(): array
    {
        return [
            ['vaccinations', Vaccination::class, 'vaccine'],
            ['deworming', DewormingRecord::class, 'product'],
            ['staff.animal-health-monitoring', AnimalHealthMonitoringRecord::class, null],
            ['staff.routine-services', RoutineServiceRecord::class, null],
        ];
    }

    private function record(string $model, ?string $field, User $owner): mixed
    {
        if ($field) {
            $pet = $owner->pets()->create(['name' => 'Delete test pet', 'species' => 'Dog', 'sex' => 'Male']);
            return $model::create(['pet_id' => $pet->id, $field => 'Test treatment', 'administered_at' => today()->subMonth(), 'next_due_at' => today()->addDay()]);
        }
        return $model::create(['visit_date' => today(), 'barangay' => 'Bgy. No. 10, San Jose', 'farmer_name' => 'Test farmer', 'species' => 'Bovine', 'sex' => 'Female', 'age_value' => 2, 'age_unit' => 'years', 'cases' => 1, 'clinical_signs' => 'Observation', 'disorder' => 'Observation', 'activity' => 'Field visit', 'number_of_heads' => 1]);
    }

    #[DataProvider('records')]
    public function test_deletion_requires_role_and_explicit_confirmation_and_preserves_other_data(string $route, string $model, ?string $field): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $row = $this->record($model, $field, $owner);
        $other = $this->record($model, $field, $owner);
        $url = route($route.'.destroy', $row);
        $this->delete($url, ['confirm_delete' => 1])->assertRedirect('/login');
        $this->actingAs($owner)->get(route($route.'.delete', $row))->assertForbidden();
        $this->delete($url, ['confirm_delete' => 1])->assertForbidden();
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->get(route($route.'.delete', $row))->assertOk()
            ->assertSee($field ? 'Delete test pet' : 'Test farmer')
            ->assertSee('name="_token"', false)->assertSee('value="DELETE"', false);
        $this->delete($url)->assertSessionHasErrors('confirm_delete');
        $this->assertNotNull($row->fresh());
        if ($field) app(PetCareReminders::class)->generate();
        $this->delete($url, ['confirm_delete' => 1])->assertRedirect();
        $this->assertSoftDeleted($row);
        $this->assertSame(1, $model::count());
        $this->assertSame(2, $model::withTrashed()->count());
        $this->assertDatabaseHas('record_deletion_audits', ['actor_id' => $staff->id, 'record_table' => $row->getTable(), 'record_id' => $row->id, 'action' => 'soft_delete']);
        $this->assertNotNull($owner->fresh());
        $this->assertNotNull($other->fresh());
        $export = match ($model) {
            Vaccination::class => new \App\Exports\VaccinationsExport,
            DewormingRecord::class => new \App\Exports\DewormingRecordsExport,
            default => new \App\Exports\LivestockRecordsExport($model),
        };
        $this->assertSame([$other->id], $export->query()->pluck('id')->all());
        if ($field) {
            $this->assertNotNull($row->pet->fresh());
            $this->assertCount(1, app(PetCareReminders::class)->schedules($owner));
            $this->assertSame(1, app(PetCareReminders::class)->activeNotifications($owner)->count());
            $this->assertSame(0, app(PetCareReminders::class)->generate());
        }
        $this->delete($url, ['confirm_delete' => 1])->assertNotFound();
        $this->get(route($route.'.delete', $row))->assertNotFound();
        $this->assertDatabaseCount('record_deletion_audits', 1);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->delete(route($route.'.destroy', $other), ['confirm_delete' => 1])->assertRedirect();
        $this->assertSoftDeleted($other);
    }

    #[DataProvider('records')]
    public function test_delete_requests_without_csrf_are_rejected(string $route, string $model, ?string $field): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $row = $this->record($model, $field, $owner);
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->app->instance('env', 'local');
        $this->delete(route($route.'.destroy', $row), ['confirm_delete' => 1])->assertStatus(419);
        $this->assertNotNull($row->fresh());
        $this->assertDatabaseCount('record_deletion_audits', 0);
    }
}
