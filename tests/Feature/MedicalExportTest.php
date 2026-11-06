<?php

namespace Tests\Feature;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MedicalExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertExitCode(0);
    }

    public static function managersAndTypes(): array
    {
        return [['staff', 'vaccinations'], ['admin', 'vaccinations'], ['staff', 'deworming'], ['admin', 'deworming']];
    }

    public static function types(): array
    {
        return [['vaccinations'], ['deworming']];
    }

    private function record(Pet $pet, string $type, string $date, string $label): void
    {
        $values = ['administered_at' => $date, 'notes' => 'Internal notes omitted'];
        if ($type === 'vaccinations') {
            $pet->vaccinations()->create($values + ['vaccine' => $label, 'veterinarian' => 'Dr. Vet']);
        } else {
            $pet->dewormingRecords()->create($values + ['product' => $label, 'weight_kg' => '12.50']);
        }
    }

    private function headings(string $type): array
    {
        return ['Owner Name', 'Owner Email', 'Owner Contact Number', 'Pet Name', 'Species', 'Breed', 'Sex',
            $type === 'vaccinations' ? 'Vaccine Name' : 'Deworming Product',
            'Date Administered', 'Next Due Date', $type === 'vaccinations' ? 'Veterinarian' : 'Weight (kg)', 'Schedule Completion Status', 'Actual Completion Date', 'Treatment Status'];
    }

    private function download(string $type): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $prefix = $type === 'vaccinations' ? 'vaccination' : 'deworming';
        $response = $this->get(route($type.'.export'))->assertOk()
            ->assertDownload('pawid_'.$prefix.'_records_'.now('Asia/Manila')->format('Y-m-d').'.xlsx')
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            return IOFactory::load($path);
        } finally {
            unlink($path);
        }
    }

    #[DataProvider('managersAndTypes')]
    public function test_managers_download_all_active_pet_records_with_safe_values(string $role, string $type): void
    {
        $manager = User::factory()->create(['role' => $role]);
        $owner = User::factory()->create(['name' => '=1+1', 'contact_number' => '09171234567']);
        $pet = $owner->pets()->create(['name' => 'Alpha', 'species' => 'Dog', 'breed' => 'Mixed', 'sex' => 'Female', 'owner_name' => 'Old name']);
        $other = Pet::create(['name' => 'Beta', 'species' => 'Cat', 'sex' => 'Male', 'owner_name' => 'Unlinked Owner', 'owner_email' => 'unlinked@example.test', 'owner_number' => '09991234567']);
        $archived = Pet::create(['name' => 'Archived', 'species' => 'Dog', 'sex' => 'Male']);
        $this->record($pet, $type, '2026-01-01', 'Old treatment');
        $this->record($pet, $type, '2026-09-01', '=HYPERLINK("https://example.test")');
        $this->record($other, $type, '2026-09-01', 'Other owner treatment');
        $this->record($archived, $type, '2026-10-01', 'Archived treatment');
        $archived->delete();
        $relation = $type === 'vaccinations' ? 'vaccinations' : 'dewormingRecords';
        $pet->$relation()->whereDate('administered_at', '2026-09-01')->update(['next_due_at' => '2027-09-01']);
        $table = $type === 'vaccinations' ? 'vaccinations' : 'deworming_records';
        $before = DB::table($table)->orderBy('id')->get()->toJson();

        $this->post('/login', ['email' => $manager->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->get(route($type.'.index'))->assertOk()->assertSee('Download Excel')->assertSee(route($type.'.export'));
        $workbook = $this->download($type);
        $sheet = $workbook->getActiveSheet();
        $rows = $sheet->toArray();
        $this->assertCount(4, $rows);
        $this->assertSame($this->headings($type), $rows[0]);
        $this->assertSame(['Unlinked Owner', 'unlinked@example.test', '09991234567', 'Beta'], array_slice($rows[1], 0, 4));
        $this->assertSame(['=1+1', $owner->email, '09171234567', 'Alpha', 'Dog', 'Mixed', 'Female'], array_slice($rows[2], 0, 7));
        $this->assertSame('Sep 1, 2026', $rows[2][8]);
        $this->assertSame('Sep 1, 2027', $rows[2][9]);
        $this->assertSame($type === 'vaccinations' ? 'Dr. Vet' : '12.50', $rows[2][10]);
        $this->assertNull($rows[3][9]);
        $this->assertSame('Old treatment', $rows[3][7]);
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('A3')->getDataType());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('H3')->getDataType());
        $this->assertSame('=HYPERLINK("https://example.test")', $sheet->getCell('H3')->getValue());
        $this->assertTrue($sheet->getStyle('A1')->getFont()->getBold());
        $this->assertGreaterThan(10, $sheet->getColumnDimension('B')->getWidth());
        $this->assertSame('A2', $sheet->getFreezePane());
        $this->assertSame($before, DB::table($table)->orderBy('id')->get()->toJson());
        $this->assertNotNull($archived->fresh()->deleted_at);
        $workbook->disconnectWorksheets();
    }

    #[DataProvider('types')]
    public function test_empty_export_has_only_headings(string $type): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $workbook = $this->download($type);
        $this->assertSame([$this->headings($type)], $workbook->getActiveSheet()->toArray());
        $workbook->disconnectWorksheets();
    }

    #[DataProvider('types')]
    public function test_guests_and_owners_cannot_export(string $type): void
    {
        $this->get(route($type.'.export'))->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['role' => 'user']));
        $this->get(route($type.'.export'))->assertForbidden();
        $this->get(route($type.'.index'))->assertOk()->assertDontSee('Download Excel')->assertDontSee(route($type.'.export'));
    }
}
