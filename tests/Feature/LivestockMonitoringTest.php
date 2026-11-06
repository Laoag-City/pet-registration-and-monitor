<?php

namespace Tests\Feature;

use App\Models\AnimalHealthMonitoringRecord;
use App\Models\LivestockRecord;
use App\Models\RoutineServiceRecord;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LivestockMonitoringTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertExitCode(0);
    }

    public static function modules(): array
    {
        return [['animal-health-monitoring', AnimalHealthMonitoringRecord::class], ['routine-services', RoutineServiceRecord::class]];
    }

    public static function managers(): array
    {
        $cases = [];
        foreach (self::modules() as [$module, $model]) {
            foreach (['staff', 'admin'] as $role) {
                $cases[] = [$module, $model, $role];
            }
        }

        return $cases;
    }

    private function url(string $module, string $action, mixed $parameters = []): string
    {
        return route('staff.'.$module.'.'.$action, $parameters);
    }

    private function payload(string $module): array
    {
        return ['visit_date' => '2026-10-01', 'barangay' => 'Bgy. No. 10, San Jose', 'farmer_name' => 'Farmer Alpha', 'species' => 'Bovine', 'sex' => 'Female', 'age_value' => '2.50', 'age_unit' => 'years', 'remarks' => "Follow up\nNext week"]
            + ($module === 'animal-health-monitoring' ? ['cases' => 0, 'clinical_signs' => "Reduced appetite\nLethargy", 'disorder' => 'Under observation'] : ['activity' => 'Field visit', 'number_of_heads' => 0]);
    }

    #[DataProvider('managers')]
    public function test_managers_can_create_view_edit_and_confirm_delete(string $module, string $model, string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));
        $this->get($this->url($module, 'index'))->assertOk()->assertSee('No records found')->assertSee('Download Excel');
        $form = $this->get($this->url($module, 'create'))->assertOk()->assertSee('Select Sex');
        foreach (LivestockRecord::SPECIES as $species) {
            $form->assertSee('value="'.$species.'"', false);
        }
        foreach (['Dog', 'Cat', 'Unknown', 'Population', 'Deaths'] as $excluded) {
            $form->assertDontSee('value="'.$excluded.'"', false);
        }
        $form->assertDontSee('name="population"', false)->assertDontSee('name="deaths"', false);
        $this->post($this->url($module, 'store'), $this->payload($module))->assertSessionHasNoErrors()->assertRedirect($this->url($module, 'index'));
        $record = $model::sole();
        $this->assertSame('2.5 years', $record->ageLabel());
        $this->get($this->url($module, 'show', $record))->assertOk()->assertSee('Farmer Alpha')->assertSee('Follow up');
        $this->get($this->url($module, 'edit', $record))->assertOk()->assertSee('Farmer Alpha');
        $this->put($this->url($module, 'update', $record), array_replace($this->payload($module), ['farmer_name' => 'Farmer Updated']))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Farmer Updated', $record->fresh()->farmer_name);
        $this->get($this->url($module, 'delete', $record))->assertOk()->assertSee('I confirm deletion');
        $this->delete($this->url($module, 'destroy', $record))->assertSessionHasErrors('confirm_delete');
        $this->assertSame(1, $model::count());
        $this->delete($this->url($module, 'destroy', $record), ['confirm_delete' => '1'])->assertRedirect($this->url($module, 'index'));
        $this->assertSame(0, $model::count());
        $this->get($this->url($module, 'show', $record))->assertNotFound();
    }

    #[DataProvider('modules')]
    public function test_validation_restricts_options_counts_dates_and_age_pairs(string $module, string $model): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $count = $module === 'animal-health-monitoring' ? 'cases' : 'number_of_heads';
        $invalid = ['species' => ['Dog', 'Cat', 'Other', ''], 'sex' => ['Unknown', 'Both', ''], $count => [-1, 1.5, 2147483648], 'visit_date' => ['invalid', '2026-02-30', ''], 'barangay' => [''], 'farmer_name' => [''], 'age_value' => [-1, 100000, '1.234', ''], 'age_unit' => ['weeks', '']];
        if ($module === 'routine-services') {
            $invalid['activity'] = [''];
        }
        foreach ($invalid as $field => $values) {
            foreach ($values as $value) {
                $this->post($this->url($module, 'store'), array_replace($this->payload($module), [$field => $value]))->assertSessionHasErrors($field);
            }
        }
        $this->assertSame(0, $model::count());
        foreach (LivestockRecord::SPECIES as $species) {
            foreach (LivestockRecord::SEXES as $sex) {
                $this->post($this->url($module, 'store'), array_replace($this->payload($module), ['species' => $species, 'sex' => $sex, 'age_value' => null, 'age_unit' => null]))->assertSessionHasNoErrors();
            }
        }
        $this->assertSame(12, $model::count());
        $record = $model::first();
        $this->put($this->url($module, 'update', $record), array_replace($this->payload($module), ['species' => 'Dog']))->assertSessionHasErrors('species');
        $this->assertSame('Bubaline', $record->fresh()->species);
    }

    #[DataProvider('modules')]
    public function test_guest_and_owner_are_denied_every_module_action(string $module, string $model): void
    {
        $record = $model::create($this->payload($module));
        $urls = ['index' => [], 'create' => [], 'show' => $record, 'edit' => $record, 'delete' => $record, 'export' => []];
        foreach ($urls as $action => $parameters) {
            $this->get($this->url($module, $action, $parameters))->assertRedirect('/login');
        }
        $this->post($this->url($module, 'store'), $this->payload($module))->assertRedirect('/login');
        $this->put($this->url($module, 'update', $record), $this->payload($module))->assertRedirect('/login');
        $this->delete($this->url($module, 'destroy', $record), ['confirm_delete' => 1])->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['role' => 'user']));
        foreach ($urls as $action => $parameters) {
            $this->get($this->url($module, $action, $parameters))->assertForbidden();
        }
        $this->post($this->url($module, 'store'), $this->payload($module))->assertForbidden();
        $this->put($this->url($module, 'update', $record), $this->payload($module))->assertForbidden();
        $this->delete($this->url($module, 'destroy', $record), ['confirm_delete' => 1])->assertForbidden();
        $this->get(route('pets.index'))->assertOk()->assertDontSee($this->url($module, 'index'));
        $this->assertSame(1, $model::count());
    }

    #[DataProvider('modules')]
    public function test_search_date_filters_pagination_and_module_isolation(string $module, string $model): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $model::create($this->payload($module));
        $model::create(array_replace($this->payload($module), ['farmer_name' => 'Farmer Beta', 'barangay' => 'Other Barangay', 'species' => 'Swine', 'visit_date' => '2026-09-01']));
        foreach (['alpha', 'san jose', 'bovine'] as $q) {
            $this->get($this->url($module, 'index', ['q' => $q]))->assertOk()->assertSee('Farmer Alpha')->assertDontSee('Farmer Beta');
        }
        $this->get($this->url($module, 'index', ['date_from' => '2026-10-01', 'date_to' => '2026-10-01']))->assertOk()->assertSee('Farmer Alpha')->assertDontSee('Farmer Beta');
        $this->get($this->url($module, 'index', ['date_from' => '2026-10-01', 'date_to' => '2026-09-01']))->assertSessionHasErrors('date_to');
        $this->get($this->url($module, 'index', ['q' => 'no matching farmer']))->assertOk()->assertSee('No records found');
        for ($i = 0; $i < 15; $i++) {
            $model::create($this->payload($module));
        }
        $this->get($this->url($module, 'index', ['q' => 'Alpha']))->assertOk()->assertViewHas('records', fn ($records) => $records->count() === 15 && $records->total() === 16)->assertSee('page=2', false);
        $other = $model === AnimalHealthMonitoringRecord::class ? RoutineServiceRecord::class : AnimalHealthMonitoringRecord::class;
        $this->assertSame(0, $other::count());
        foreach (['pets', 'vaccinations', 'deworming_records'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    #[DataProvider('managers')]
    public function test_exports_contain_all_records_and_numeric_zero_with_literal_text(string $module, string $model, string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));
        $model::create($this->payload($module));
        $model::create(array_replace($this->payload($module), ['farmer_name' => '=1+1', 'visit_date' => '2026-10-02', 'remarks' => '=HYPERLINK("https://example.test")']));
        $before = $model::all()->toJson();
        $sheet = $this->download($module, ['q' => 'no matches']);
        $this->assertSame(3, $sheet->getHighestRow());
        $this->assertSame('Oct 2, 2026', $sheet->getCell('A2')->getValue());
        $this->assertSame('=1+1', $sheet->getCell('C2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('C2')->getDataType());
        $health = $module === 'animal-health-monitoring';
        $countCell = $sheet->getCell($health ? 'G2' : 'H2');
        $this->assertSame(0, $countCell->getValue());
        $this->assertSame(DataType::TYPE_NUMERIC, $countCell->getDataType());
        $this->assertSame('Bovine', $sheet->getCell($health ? 'D2' : 'E2')->getValue());
        $this->assertSame('Female', $sheet->getCell($health ? 'E2' : 'F2')->getValue());
        $this->assertSame('2.5 years', $sheet->getCell($health ? 'F2' : 'G2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell($health ? 'J2' : 'I2')->getDataType());
        $this->assertTrue($sheet->getStyle('A1')->getFont()->getBold());
        $this->assertSame('A2', $sheet->getFreezePane());
        $this->assertGreaterThan(20, $sheet->getColumnDimension('C')->getWidth());
        $this->assertSame($before, $model::all()->toJson());
        $sheet->getParent()->disconnectWorksheets();
    }

    #[DataProvider('modules')]
    public function test_empty_exports_have_exact_headings_and_no_excluded_columns(string $module, string $model): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $sheet = $this->download($module);
        $expected = $module === 'animal-health-monitoring'
            ? ['Date of Visit', 'Barangay', "Farmer's Name", 'Species', 'Sex', 'Age', 'Cases', 'Clinical Signs', 'Disorder', 'Remarks']
            : ['Date of Visit', 'Barangay', "Farmer's Name", 'Activity', 'Species', 'Sex', 'Age', 'Number of Heads', 'Remarks'];
        $this->assertSame([$expected], $sheet->toArray());
        $this->assertFalse(Schema::hasColumn((new $model)->getTable(), 'population'));
        $this->assertFalse(Schema::hasColumn((new $model)->getTable(), 'deaths'));
        $sheet->getParent()->disconnectWorksheets();
    }

    private function download(string $module, array $filters = []): Worksheet
    {
        $response = $this->get($this->url($module, 'export', $filters))->assertOk()
            ->assertDownload('pawid_'.str_replace('-', '_', $module).'_'.now('Asia/Manila')->format('Y-m-d').'.xlsx')
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            // Read independently of the export's process-global value binder.
            Cell::setValueBinder(new DefaultValueBinder);

            return IOFactory::load($path)->getActiveSheet();
        } finally {
            unlink($path);
        }
    }
}
