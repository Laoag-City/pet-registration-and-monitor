<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Services\MedicalCompletionLinks;

DB::beginTransaction();
DB::statement('SET TRANSACTION READ ONLY');
try {
    foreach ([App\Models\Vaccination::class, App\Models\DewormingRecord::class] as $model) {
        $links = app(MedicalCompletionLinks::class)->resolve($model);
        $export = $model === App\Models\Vaccination::class ? new App\Exports\VaccinationsExport : new App\Exports\DewormingRecordsExport;
        $exportRows = $export->query()->get()->map(fn ($row) => ['id' => $row->id, 'completion_columns' => array_slice($export->map($row), -3)]);
        $marked = $model::withTrashed()->whereNotNull('schedule_completed_at')->pluck('id')->all();
        echo json_encode(['table' => (new $model)->getTable(), 'verified_links' => $links,
            'completed_sources_without_verified_link' => array_values(array_diff($marked, array_keys($links['pairs']))),
            'exported_records' => $exportRows], JSON_PRETTY_PRINT).PHP_EOL;
    }
} finally { DB::rollBack(); }
