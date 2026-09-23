<?php

namespace Tests\Feature;

use App\Models\{Pet, User};
use App\Services\StaffDashboardAnalytics;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class StaffDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // TestCase refuses all connections except isolated SQLite :memory: before this runs.
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertExitCode(0);
        $this->travelTo(CarbonImmutable::parse('2026-09-21 12:00:00', 'UTC'));
    }

    public function test_empty_dashboard_and_both_staff_entry_points(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        foreach (['/dashboard', '/staff/dashboard'] as $url) {
            $response = $this->actingAs($staff)->get($url)->assertOk()
                ->assertViewHas('petCount', 0)->assertViewHas('userCount', 0)
                ->assertSee('No upcoming or overdue pet care schedules.')
                ->assertDontSee('Search pets, records...')->assertDontSee('Recent pet registrations')
                ->assertDontSee('Recent client registrations')->assertSee('QR Codes');
            $this->assertSame(array_fill(0, 12, 0), $response->viewData('registrations'));
            // One active link in each of the desktop and mobile navigations.
            $this->assertSame(2, substr_count($response->getContent(), 'class="nav-link active"'));
        }
        $this->actingAs(User::factory()->create(['role' => 'user']))->get('/staff/dashboard')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/dashboard')->assertViewIs('admin.dashboard');
    }

    public function test_monthly_and_species_aggregations_use_real_rows(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        User::factory()->count(2)->create(['role' => 'user']);
        foreach ([['Dog', '2026-01-01'], [' dog ', '2026-09-01'], ['DOG', '2025-12-31'], ['Cat', '2026-09-02'], ['Rabbit', '2026-12-01']] as [$species, $date]) {
            $pet = Pet::create(['name' => 'Test pet', 'species' => $species, 'sex' => 'Male']);
            $pet->forceFill(['created_at' => $date])->save();
        }
        $data = app(StaffDashboardAnalytics::class)->data($staff);
        $this->assertSame(2, $data['userCount']);
        $this->assertSame(5, $data['petCount']);
        $this->assertSame([1, 0, 0, 0, 0, 0, 0, 0, 2, 0, 0, 1], $data['registrations']);
        $this->assertSame(['label' => 'Dog', 'count' => 3, 'percentage' => 60.0], $data['species'][0]);
        $this->assertSame(3, count($data['species']));
    }

    public function test_latest_schedules_boundaries_null_dates_and_unique_attention(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $pet = Pet::create(['name' => 'Care pet', 'species' => 'Cat', 'sex' => 'Female']);
        $vaccinate = fn ($vaccine, $due, $administered = '2026-01-01') => $pet->vaccinations()->create([
            'vaccine' => $vaccine, 'next_due_at' => $due, 'administered_at' => $administered,
        ]);
        $vaccinate(' Rabies ', '2026-09-01');
        $vaccinate('rabies', '2026-10-01', '2026-09-01');
        foreach (['Overdue' => '2026-09-20', 'Today' => '2026-09-21', 'Three' => '2026-09-24', 'Seven' => '2026-09-28', 'Eight' => '2026-09-29', 'Null' => null] as $vaccine => $due) {
            $vaccinate($vaccine, $due);
        }
        $pet->dewormingRecords()->create(['product' => 'Old', 'administered_at' => '2026-01-01', 'next_due_at' => '2026-09-01']);
        $pet->dewormingRecords()->create(['product' => 'New', 'administered_at' => '2026-09-01', 'next_due_at' => null]);
        $data = app(StaffDashboardAnalytics::class)->data($staff);
        $this->assertSame(['Up to Date' => 2, 'Due Soon' => 2, 'Due Today' => 1, 'Overdue' => 1], $data['statuses']['vaccination']['counts']);
        $this->assertSame(33, $data['statuses']['vaccination']['percentage']);
        $this->assertSame(1, $data['statuses']['vaccination']['unscheduled']);
        $this->assertSame(0, $data['statuses']['deworming']['total']);
        $this->assertSame(1, $data['statuses']['deworming']['unscheduled']);
        $this->assertNull($data['statuses']['deworming']['percentage']);
        $this->assertSame(1, $data['attentionCount']);
        $this->assertSame(['Overdue', 'Due Today', 'Due Soon', 'Due Soon'], $data['attention']->pluck('status')->all());
        $this->assertSame(8, $data['vaccinationCount']);
        $this->assertSame(2, $data['dewormingCount']);
        // A tied administered date is superseded by the higher record ID.
        $vaccinate('Overdue', null);
        for ($i = 0; $i < 6; $i++) {
            $other = Pet::create(['name' => 'Extra '.$i, 'species' => 'Dog', 'sex' => 'Male']);
            $other->dewormingRecords()->create(['product' => 'Current', 'administered_at' => '2026-01-01', 'next_due_at' => '2026-09-20']);
        }
        $data = app(StaffDashboardAnalytics::class)->data($staff);
        $this->assertSame(0, $data['statuses']['vaccination']['counts']['Overdue']);
        $this->assertSame(7, $data['attentionCount']);
        $this->assertCount(5, $data['attention']);
        $this->assertSame(['Overdue'], $data['attention']->pluck('status')->unique()->values()->all());
    }
}
