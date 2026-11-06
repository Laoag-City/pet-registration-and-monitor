<?php

namespace Tests\Feature;

use App\Models\AnimalHealthMonitoringRecord;
use App\Models\RoutineServiceRecord;
use App\Models\User;
use App\Support\LaoagBarangays;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LaoagBarangayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // TestCase refuses anything except isolated SQLite :memory:.
        // Define only test fixtures; do not invoke migration or db commands.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        foreach (['animal_health_monitoring_records', 'routine_service_records'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) use ($tableName) {
                $table->id();
                $table->date('visit_date');
                $table->softDeletes();
                $table->string('barangay');
                $table->string('farmer_name');
                $table->string('species');
                $table->string('sex');
                $table->decimal('age_value', 7, 2)->nullable();
                $table->string('age_unit')->nullable();
                $table->text('remarks')->nullable();
                if ($tableName === 'animal_health_monitoring_records') {
                    $table->integer('cases')->nullable();
                    $table->text('clinical_signs')->nullable();
                    $table->string('disorder')->nullable();
                } else {
                    $table->string('activity');
                    $table->integer('number_of_heads');
                }
                $table->timestamps();
            });
        }
    }

    public static function modules(): array
    {
        return [['animal-health-monitoring', AnimalHealthMonitoringRecord::class], ['routine-services', RoutineServiceRecord::class]];
    }

    private function payload(string $barangay = 'Bgy. No. 1, San Lorenzo'): array
    {
        return ['visit_date' => '2026-11-03', 'barangay' => $barangay, 'farmer_name' => 'Test Farmer', 'species' => 'Bovine', 'sex' => 'Female', 'cases' => 0, 'activity' => 'Inspection', 'number_of_heads' => 0];
    }

    private function url(string $module, string $action, mixed $params = []): string
    {
        return route('staff.'.$module.'.'.$action, $params);
    }

    private function dropdown(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);

        return new \DOMXPath($dom);
    }

    public function test_official_codes_numbers_names_and_natural_order(): void
    {
        $options = LaoagBarangays::options();
        $this->assertCount(80, $options);
        $this->assertCount(80, array_unique($options));
        $expectedCodes = [];
        foreach (range(1, 84) as $suffix) {
            if (! in_array($suffix, [18, 25, 51, 52])) {
                $expectedCodes[] = '0102812'.str_pad((string) $suffix, 3, '0', STR_PAD_LEFT);
            }
        }
        $codes = array_keys($options);
        sort($codes);
        $this->assertSame($expectedCodes, $codes);
        $numbers = [];
        foreach ($options as $code => $label) {
            $this->assertIsString($code);
            preg_match('/No\. (\d+(?:-[A-Z])?)/', $label, $matches);
            $numbers[] = $matches[1];
        }
        $expectedNumbers = [];
        foreach (range(1, 62) as $number) {
            $suffixes = in_array($number, [32, 55]) ? ['A', 'B', 'C'] : (in_array($number, [7, 30, 33, 34, 38, 48, 49, 51, 52, 54, 56, 59, 60, 62]) ? ['A', 'B'] : []);
            foreach ($suffixes ?: [''] as $suffix) {
                $expectedNumbers[] = $number.($suffix ? '-'.$suffix : '');
            }
        }
        $this->assertSame($expectedNumbers, $numbers);
        $this->assertSame('Bgy. No. 7-A, Nstra. Sra. De Natividad', $options['0102812042']);
        $this->assertSame('Bgy. No. 7-B, Nstra. Sra. De Natividad', $options['0102812043']);
        $this->assertSame('Bgy. No. 56-A, Bacsil North', $options['0102812003']);
        $this->assertSame('Bgy. No. 56-B, Bacsil South', $options['0102812004']);
        $this->assertSame('Bry. No. 18, San Quirino', $options['0102812069']);
    }

    #[DataProvider('modules')]
    public function test_dropdown_create_edit_validation_and_old_input(string $module, string $model): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        foreach (['create', 'index'] as $action) {
            $response = $this->get($this->url($module, $action))->assertOk()->assertSee('Select Barangay');
            $xpath = $this->dropdown($response->getContent());
            $options = $xpath->query('//select[@name="barangay"]/option[@data-psgc]');
            $this->assertSame(80, $options->length);
            $actual = [];
            foreach ($options as $option) {
                $actual[$option->getAttribute('data-psgc')] = $option->getAttribute('value');
            }
            $this->assertSame(LaoagBarangays::options(), $actual);
        }
        foreach (['', 'San Pedro', 'Made up barangay', '0102812062'] as $invalid) {
            $this->post($this->url($module, 'store'), $this->payload($invalid))->assertSessionHasErrors('barangay');
        }
        $this->post($this->url($module, 'store'), $this->payload() + ['keep_existing_barangay' => 1])->assertSessionHasErrors('keep_existing_barangay');
        $this->post($this->url($module, 'store'), $this->payload())->assertSessionHasNoErrors();
        $record = $model::sole();
        $this->assertSame('Bgy. No. 1, San Lorenzo', $record->barangay);
        $html = $this->get($this->url($module, 'edit', $record))->assertOk()->getContent();
        $selected = $this->dropdown($html)->query('//select[@name="barangay"]/option[@selected]');
        $this->assertSame($record->barangay, $selected->item(0)->getAttribute('value'));
        $new = 'Bgy. No. 7-B, Nstra. Sra. De Natividad';
        $this->from($this->url($module, 'edit', $record))->put($this->url($module, 'update', $record), array_replace($this->payload($new), ['farmer_name' => '']))->assertSessionHasErrors('farmer_name');
        $html = $this->get($this->url($module, 'edit', $record))->assertOk()->getContent();
        $this->assertSame($new, $this->dropdown($html)->query('//select[@name="barangay"]/option[@selected]')->item(0)->getAttribute('value'));
        $this->put($this->url($module, 'update', $record), $this->payload($new))->assertSessionHasNoErrors();
        $this->assertSame($new, $record->fresh()->barangay);
        $this->put($this->url($module, 'update', $record), $this->payload('Unknown'))->assertSessionHasErrors('barangay');
    }

    #[DataProvider('modules')]
    public function test_historical_values_preserved_and_flagged_without_allowing_arbitrary_replacements(string $module, string $model): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['San Pedro', 'Unmatched location'] as $original) {
            $record = $model::create($this->payload($original));
            $this->get($this->url($module, 'edit', $record))->assertOk()->assertSee('Barangay needs review')->assertSee($original);
            $this->put($this->url($module, 'update', $record), $this->payload('Injected replacement') + ['keep_existing_barangay' => 1])->assertSessionHasNoErrors();
            $this->assertSame($original, $record->fresh()->barangay);
            $this->put($this->url($module, 'update', $record), $this->payload('Injected replacement') + ['keep_existing_barangay' => 0])->assertSessionHasErrors('barangay');
            $this->assertSame($original, $record->fresh()->barangay);
        }
    }

    #[DataProvider('modules')]
    public function test_filter_search_and_excel_keep_labels_and_historical_values(string $module, string $model): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $model::create($this->payload());
        $model::create(array_replace($this->payload('San Pedro'), ['farmer_name' => 'Historical Farmer']));
        $this->get($this->url($module, 'index', ['barangay' => 'Bgy. No. 1, San Lorenzo', 'date_from' => '2026-11-03', 'date_to' => '2026-11-03']))->assertOk()->assertViewHas('records', fn ($r) => $r->total() === 1 && $r->first()->farmer_name === 'Test Farmer');
        $this->get($this->url($module, 'index', ['q' => 'San Pedro']))->assertOk()->assertViewHas('records', fn ($r) => $r->total() === 1 && $r->first()->barangay === 'San Pedro');
        $this->get($this->url($module, 'index', ['barangay' => 'Unknown']))->assertSessionHasErrors('barangay');
        $before = $model::all()->toJson();
        $response = $this->get($this->url($module, 'export'))->assertOk()->assertDownload()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            Cell::setValueBinder(new DefaultValueBinder);
            $book = IOFactory::load($path);
            $rows = $book->getActiveSheet()->toArray();
            $this->assertSame(['Barangay', 'San Pedro', 'Bgy. No. 1, San Lorenzo'], array_column($rows, 1));
            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }
        $this->assertSame($before, $model::all()->toJson());
    }

    #[DataProvider('modules')]
    public function test_staff_only_authorization_is_unchanged(string $module, string $model): void
    {
        $record = $model::create($this->payload());
        $this->get($this->url($module, 'create'))->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['role' => 'user']));
        foreach (['index' => [], 'create' => [], 'edit' => $record, 'show' => $record, 'delete' => $record, 'export' => []] as $action => $params) {
            $this->get($this->url($module, $action, $params))->assertForbidden();
        }
        $this->post($this->url($module, 'store'), $this->payload())->assertForbidden();
        $this->put($this->url($module, 'update', $record), $this->payload())->assertForbidden();
        $this->delete($this->url($module, 'destroy', $record), ['confirm_delete' => 1])->assertForbidden();
    }
}
