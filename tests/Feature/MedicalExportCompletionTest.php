<?php

namespace Tests\Feature;

use App\Models\{Vaccination, DewormingRecord, User};
use App\Services\MedicalCompletionLinks;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MedicalExportCompletionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::unprepared(file_get_contents(base_path('tests/Fixtures/schema.sql')));
        $this->travelTo(now()->setDate(2026, 11, 5)->startOfDay());
    }

    public static function types(): array
    {
        return [['vaccinations', Vaccination::class, 'vaccine'], ['deworming', DewormingRecord::class, 'product']];
    }

    private function fixture(string $model, string $field): array
    {
        $owner = User::factory()->create(['role' => 'user']);
        $pet = $owner->pets()->create(['name' => 'Export pet', 'species' => 'Dog', 'sex' => 'Male']);
        $source = $model::create(['pet_id' => $pet->id, $field => 'Treatment', 'administered_at' => '2026-10-01', 'next_due_at' => '2026-11-04', 'notes' => 'Retain original history']);
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        return [$owner, $pet, $source];
    }

    private function rows(string $type): array
    {
        $response = $this->get(route($type.'.export'))->assertOk()->assertDownload();
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            $book = IOFactory::load($path);
            $rows = $book->getActiveSheet()->toArray();
            $book->disconnectWorksheets();
            return $rows;
        } finally { unlink($path); }
    }

    #[DataProvider('types')]
    public function test_done_exports_one_completed_row_with_optional_next_due_and_repeat_is_rejected(string $type, string $model, string $field): void
    {
        [$owner, $pet, $source] = $this->fixture($model, $field);
        $this->post(route($type.'.done', $source), ['administered_at' => '2026-11-05', 'confirm_completion' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $rows = $this->rows($type);
        $this->assertCount(2, $rows);
        $this->assertSame('Nov 5, 2026', $rows[1][8]);
        $this->assertNull($rows[1][9]);
        $this->assertSame(['Completed', 'Nov 5, 2026', 'Completed'], array_slice($rows[1], -3));
        $this->assertSame(2, $model::count());
        $this->assertSame('Retain original history', $source->fresh()->notes);
        $this->assertSame('2026-10-01', $source->fresh()->administered_at->toDateString());
        // Even an accidentally cleared marker must not allow a replay of an audited completion.
        $source->forceFill(['schedule_completed_at' => null])->save();
        $this->post(route($type.'.done', $source), ['administered_at' => '2026-11-05', 'confirm_completion' => 1])->assertSessionHasErrors('completion');
        $this->assertSame(2, $model::count());
    }

    #[DataProvider('types')]
    public function test_successive_done_visits_and_identical_independent_doses_remain_separate(string $type, string $model, string $field): void
    {
        [$owner, $pet, $source] = $this->fixture($model, $field);
        $this->post(route($type.'.done', $source), ['administered_at' => '2026-11-04', 'next_due_at' => '2026-11-05', 'confirm_completion' => 1])->assertSessionHasNoErrors();
        $first = $model::latest('id')->first();
        $this->post(route($type.'.done', $first), ['administered_at' => '2026-11-05', 'next_due_at' => '2026-12-05', 'confirm_completion' => 1])->assertSessionHasNoErrors();
        $second = $model::latest('id')->first();
        $rows = $this->rows($type);
        $this->assertCount(3, $rows);
        $this->assertSame(['Nov 5, 2026', 'Nov 4, 2026'], [$rows[1][8], $rows[2][8]]);
        $this->assertSame('Dec 5, 2026', $rows[1][9]);
        $this->assertSame('Nov 4, 2026', $rows[2][12]); // Not the following administration's date.
        $this->assertSame([$source->id], app(MedicalCompletionLinks::class)->resolve($model)['superseded_ids']);
        $this->assertNull($second->schedule_completed_at); // Its future treatment remains pending.
        $this->assertCount(1, app(\App\Services\PetCareReminders::class)->schedules($owner));
        foreach (['Dose one', 'Dose two'] as $note) {
            $model::create(['pet_id' => $pet->id, $field => 'Treatment', 'administered_at' => '2026-09-01', 'notes' => $note]);
        }
        $rows = $this->rows($type);
        $this->assertCount(5, $rows);
        $this->assertSame('Sep 1, 2026', $rows[3][8]);
        $this->assertSame('Sep 1, 2026', $rows[4][8]);
    }

    #[DataProvider('types')]
    public function test_unlinked_legacy_records_and_future_pending_records_are_not_collapsed(string $type, string $model, string $field): void
    {
        [$owner, $pet, $source] = $this->fixture($model, $field);
        $source->forceFill(['schedule_completed_at' => '2026-11-04'])->save();
        $model::create(['pet_id' => $pet->id, $field => 'Treatment', 'administered_at' => '2026-11-04']);
        $model::create(['pet_id' => $pet->id, $field => 'Treatment', 'administered_at' => '2026-12-01', 'next_due_at' => '2027-01-01']);
        $rows = $this->rows($type);
        $this->assertCount(4, $rows);
        $this->assertSame('Dec 1, 2026', $rows[1][8]);
        $this->assertSame('Administration not confirmed', $rows[1][13]);
        $this->assertSame([], app(MedicalCompletionLinks::class)->resolve($model)['pairs']);
        $this->actingAs($owner)->get(route($type.'.export'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->assertCount(4, $this->rows($type));
    }

    #[DataProvider('types')]
    public function test_conflicting_or_cross_pet_audit_links_never_hide_history(string $type, string $model, string $field): void
    {
        [$owner, $pet, $source] = $this->fixture($model, $field);
        $source->forceFill(['schedule_completed_at' => '2026-11-04'])->save();
        $otherPet = $owner->pets()->create(['name' => 'Other', 'species' => 'Dog', 'sex' => 'Male']);
        $target = $model::create(['pet_id' => $otherPet->id, $field => 'Treatment', 'administered_at' => '2026-11-04']);
        $audit = ['actor_id' => auth()->id(), 'record_table' => $source->getTable(), 'record_id' => $source->id, 'action' => 'schedule_completed', 'changes' => json_encode(['administration_id' => $target->id]), 'created_at' => now()];
        DB::table('record_change_audits')->insert($audit);
        $this->assertCount(3, $this->rows($type));
        $target->update(['pet_id' => $pet->id]);
        DB::table('record_change_audits')->insert(array_replace($audit, ['changes' => json_encode(['administration_id' => 99999])]));
        $this->assertCount(3, $this->rows($type));
        $this->assertSame([], app(MedicalCompletionLinks::class)->resolve($model)['pairs']);
    }

    #[DataProvider('types')]
    public function test_deleting_a_completion_does_not_revive_its_superseded_export_row(string $type, string $model, string $field): void
    {
        [$owner, $pet, $source] = $this->fixture($model, $field);
        $this->post(route($type.'.done', $source), ['administered_at' => '2026-11-05', 'confirm_completion' => 1])->assertSessionHasNoErrors();
        $target = $model::latest('id')->first();
        $target->delete();
        $this->assertCount(1, $this->rows($type)); // Header only, not the old pending representation.
        $target->restore();
        $this->assertCount(2, $this->rows($type));
        $this->assertSame(2, $model::withTrashed()->count());
    }
}
