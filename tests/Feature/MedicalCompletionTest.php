<?php

namespace Tests\Feature;

use App\Exports\DewormingRecordsExport;
use App\Exports\VaccinationsExport;
use App\Models\DewormingRecord;
use App\Models\User;
use App\Models\Vaccination;
use App\Services\PetCareReminders;
use App\Services\StaffDashboardAnalytics;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MedicalCompletionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertExitCode(0);
        $this->travelTo(now()->setDate(2026, 11, 4)->startOfDay());
    }

    public static function types(): array
    {
        return [['vaccinations', Vaccination::class, 'vaccine'], ['deworming', DewormingRecord::class, 'product']];
    }

    private function fixture(string $model, string $field): array
    {
        $owner = User::factory()->create(['role' => 'user']);
        $pet = $owner->pets()->create(['name' => 'Buddy', 'species' => 'Dog', 'sex' => 'Male']);
        $record = $model::create(['pet_id' => $pet->id, $field => 'Treatment', 'administered_at' => '2026-10-01', 'next_due_at' => '2026-11-01', 'notes' => 'Original history']);

        return [$owner, $pet, $record];
    }

    #[DataProvider('types')]
    public function test_done_without_next_due_stays_in_history_but_not_schedules(string $type, string $model, string $field): void
    {
        [$owner, $pet, $record] = $this->fixture($model, $field);
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);
        $this->post(route($type.'.done', $record), ['administered_at' => '2026-11-04', 'next_due_at' => '', 'confirm_completion' => 1])->assertSessionHasNoErrors();
        $new = $model::latest('id')->first();
        $this->assertSame(2, $model::count());
        $this->assertNull($new->next_due_at);
        $this->assertNull($new->schedule_completed_at); // No future obligation was completed.
        $this->assertSame('2026-11-04', $new->administered_at->toDateString());
        $page = $this->get(route($type.'.index'))->assertOk()->assertSee('History')->assertSee('Completed')->assertSee('Not Scheduled')->assertDontSee('No due date');
        $page->assertViewHas('records', fn ($rows) => $rows->count() === 2 && $rows->contains('id', $new->id));
        $page->assertViewHas('stats', fn ($stats) => $stats['total'] === 2 && $stats['current'] === 0 && $stats['dueSoon'] === 0 && $stats['overdue'] === 0);
        $reminders = app(PetCareReminders::class);
        $this->assertCount(0, $reminders->schedules($owner));
        $this->assertSame(0, $reminders->generateForPet($pet));
        $this->assertSame(0, $reminders->activeNotifications($owner)->count());
        $this->get(route('staff.dashboard'))->assertOk()->assertViewHas('attentionCount', 0);
        $response = $this->get(route($type.'.export'))->assertOk()->assertDownload();
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new \PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder);
            $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $rows = $book->getActiveSheet()->toArray();
            $this->assertCount(2, $rows); // Header plus the linked completed representation only.
            $this->assertSame('Nov 4, 2026', $rows[1][8]);
            $this->assertNull($rows[1][9]);
            $this->assertSame('Completed', $rows[1][13]);
            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }
        $this->post(route($type.'.done', $record), ['administered_at' => '2026-11-04', 'confirm_completion' => 1])->assertSessionHasErrors('completion');
        $this->assertSame(2, $model::count());
        $this->actingAs($owner)->get(route('dashboard'))->assertOk()->assertViewHas('careSchedules', fn ($schedules) => $schedules->isEmpty());
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.dashboard'))->assertOk();
        $this->travelTo(now()->addYear());
        $this->assertSame(0, $reminders->generateForPet($pet));
        $this->get(route($type.'.index'))->assertSee('Completed')->assertSee('Not Scheduled');
        $this->assertSame('2026-10-01', $record->fresh()->administered_at->toDateString());
    }

    #[DataProvider('types')]
    public function test_completion_preserves_history_and_tracks_the_following_schedule(string $type, string $model, string $field): void
    {
        [$owner, $pet, $record] = $this->fixture($model, $field);
        $staff = User::factory()->create(['role' => 'staff']);
        $reminders = app(PetCareReminders::class);
        $reminders->generateForPet($pet);
        $this->assertSame(1, $reminders->activeNotifications($owner)->count());
        $this->actingAs($staff)->get(route($type.'.index'))->assertOk()->assertSee('Confirm treatment administered')->assertSee('Scheduled due date: Nov 1, 2026');
        $this->post(route($type.'.done', $record), ['administered_at' => '2026-11-04', 'next_due_at' => '2026-12-04', 'confirm_completion' => 1])->assertSessionHasNoErrors()->assertRedirect(route($type.'.index'));
        $this->assertSame(2, $model::count());
        $record->refresh();
        $this->assertSame('2026-10-01', $record->administered_at->toDateString());
        $this->assertSame('2026-11-01', $record->next_due_at->toDateString());
        $this->assertSame('Original history', $record->notes);
        $this->assertSame('2026-11-04', $record->schedule_completed_at->toDateString());
        $new = $model::latest('id')->first();
        $this->assertSame('2026-11-04', $new->administered_at->toDateString());
        $this->assertNull($new->schedule_completed_at);
        $this->assertSame(0, $reminders->activeNotifications($owner)->count());
        $this->assertSame('2026-12-04', $reminders->schedules($owner)->sole()['due']->toDateString());
        $dashboard = app(StaffDashboardAnalytics::class)->data($staff);
        $key = $type === 'vaccinations' ? 'vaccination' : 'deworming';
        $this->assertSame(0, $dashboard['statuses'][$key]['counts']['Overdue']);
        $this->assertSame(1, $dashboard['statuses'][$key]['counts']['Up to Date']);
        $this->get(route($type.'.index'))->assertSee('Completed')->assertDontSee('data-bs-target="#done-'.$record->id.'"', false)->assertSee('data-bs-target="#done-'.$new->id.'"', false);
        $this->post(route($type.'.done', $record), ['administered_at' => '2026-11-04', 'confirm_completion' => 1])->assertSessionHasErrors('completion');
        $this->assertSame(2, $model::count());
        $this->travelTo(now()->setDate(2026, 12, 5));
        $this->assertSame('Overdue', $reminders->schedules($owner)->sole()['status']);
        $this->get(route($type.'.index'))->assertSee('Completed');
        $export = $type === 'vaccinations' ? new VaccinationsExport : new DewormingRecordsExport;
        $this->assertNull($export->query()->find($record->id));
        $this->assertSame(['Completed', 'Nov 4, 2026', 'Completed'], array_slice($export->map($export->query()->findOrFail($new->id)), -3));
    }

    #[DataProvider('types')]
    public function test_authorization_dates_stale_schedules_and_archived_pets(string $type, string $model, string $field): void
    {
        [$owner, $pet, $record] = $this->fixture($model, $field);
        $url = route($type.'.done', $record);
        $payload = ['administered_at' => '2026-11-04', 'confirm_completion' => 1];
        $this->post($url, $payload)->assertRedirect('/login');
        $this->actingAs($owner)->post($url, $payload)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['2026-11-05', '2026-09-01', 'invalid', ''] as $date) {
            $this->post($url, array_replace($payload, ['administered_at' => $date]))->assertSessionHasErrors('administered_at');
        }
        $this->post($url, $payload + ['next_due_at' => '2026-11-01'])->assertSessionHasErrors('next_due_at');
        $this->post($url, ['administered_at' => '2026-11-04'])->assertSessionHasErrors('confirm_completion');
        $new = $model::create(['pet_id' => $pet->id, $field => 'Treatment', 'administered_at' => '2026-11-02']);
        $this->post($url, $payload)->assertSessionHasErrors('completion');
        $this->post(route($type.'.done', $new), $payload)->assertSessionHasErrors('completion');
        $new->update(['next_due_at' => '2026-11-04']);
        $this->post(route($type.'.done', $new), $payload)->assertSessionHasNoErrors();
        $this->assertCount(0, app(PetCareReminders::class)->schedules($owner));
        $this->assertSame(3, $model::count());
        $pet->delete();
        $this->post($url, $payload)->assertNotFound();
        $this->assertSame(3, $model::count());
    }
}
