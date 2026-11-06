<?php

namespace Tests\Feature;

use App\Models\DewormingRecord;
use App\Models\Pet;
use App\Models\User;
use App\Models\Vaccination;
use App\Services\PetCareReminders;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MedicalScheduleListTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::unprepared(file_get_contents(base_path('tests/Fixtures/schema.sql')));
    }

    public static function types(): array
    {
        return [['vaccinations', Vaccination::class, 'vaccine'], ['deworming', DewormingRecord::class, 'product']];
    }

    #[DataProvider('types')]
    public function test_only_current_dated_records_appear_in_schedules_while_history_is_complete(string $type, string $model, string $field): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $expected = [];
        foreach (['Unscheduled' => null, 'Upcoming' => today()->addDays(3), 'Overdue' => today()->subDays(2)] as $name => $due) {
            $pet = $owner->pets()->create(['name' => $name, 'species' => 'Dog', 'sex' => 'Male']);
            $row = $model::create(['pet_id' => $pet->id, $field => 'Treatment', 'administered_at' => today()->subMonth(), 'next_due_at' => $due]);
            if ($due !== null) $expected[] = $row->id;
        }
        // Empty dates cannot be stored in PostgreSQL DATE columns, but exclude legacy SQLite blanks too.
        $blankPet = $owner->pets()->create(['name' => 'Blank date', 'species' => 'Dog', 'sex' => 'Male']);
        $model::create(['pet_id' => $blankPet->id, $field => 'Treatment', 'administered_at' => today()->subMonth()]);
        DB::table((new $model)->getTable())->where('pet_id', $blankPet->id)->update(['next_due_at' => '']);
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $response = $this->get(route($type.'.index'))->assertOk()->assertSee('View complete medical history');
        $this->assertEqualsCanonicalizing($expected, $response->viewData('scheduledRecords')->pluck('id')->all());
        $this->assertCount(4, $response->viewData('records'));
        $this->assertSame(1, $response->viewData('stats')['dueSoon']);
        $this->assertSame(1, $response->viewData('stats')['overdue']);
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $section = (new \DOMXPath($dom))->query('//section[@aria-labelledby="schedule-heading"]')->item(0)->textContent;
        $this->assertStringNotContainsString('Unscheduled', $section);
        $this->assertStringNotContainsString('Blank date', $section);
        $this->assertStringContainsString('Upcoming', $section);
        $reminders = app(PetCareReminders::class);
        $this->assertCount(2, $reminders->schedules($owner));
        $this->assertSame(2, $reminders->generate());
        $this->assertSame(2, $reminders->activeNotifications($owner)->count());
        $this->actingAs(User::factory()->create(['role' => 'user']))->get(route($type.'.index'))
            ->assertViewHas('scheduledRecords', fn ($rows) => $rows->isEmpty());
    }

    #[DataProvider('types')]
    public function test_latest_unscheduled_administration_does_not_revive_an_old_schedule(string $type, string $model, string $field): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $pet = $owner->pets()->create(['name' => 'History only', 'species' => 'Dog', 'sex' => 'Male']);
        $model::create(['pet_id' => $pet->id, $field => 'Treatment', 'administered_at' => today()->subMonths(2), 'next_due_at' => today()->subMonth()]);
        $model::create(['pet_id' => $pet->id, $field => 'Treatment', 'administered_at' => today(), 'next_due_at' => null]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route($type.'.index'))->assertOk()
            ->assertViewHas('scheduledRecords', fn ($rows) => $rows->isEmpty())
            ->assertViewHas('records', fn ($rows) => $rows->count() === 2);
        $this->assertSame(0, app(PetCareReminders::class)->generateForPet($pet));
        $this->assertSame(2, $model::count());
    }
}
