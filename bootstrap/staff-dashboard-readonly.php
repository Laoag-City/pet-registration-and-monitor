<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Services\StaffDashboardAnalytics;
use Illuminate\Support\Facades\{DB, Schema};

DB::beginTransaction();
DB::statement('SET TRANSACTION READ ONLY');
try {
    $staff = User::where('role', User::ROLE_STAFF)->firstOrFail();
    DB::enableQueryLog();
    $data = app(StaffDashboardAnalytics::class)->data($staff);
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();
    $output = array_intersect_key($data, array_flip(['userCount', 'petCount', 'vaccinationCount', 'dewormingCount', 'year', 'registrations', 'species', 'statuses', 'attentionCount']));
    $output['queryCount'] = $queryCount;
    $output['attentionRows'] = $data['attention']->count();
    $output['checks'] = [
        'species_total_matches' => array_sum(array_column($data['species'], 'count')) === $data['petCount'],
        'twelve_months' => count($data['registrations']) === 12,
        'attention_limited' => $data['attention']->count() <= 5,
    ];
    foreach (['users', 'pets', 'vaccinations', 'deworming_records'] as $table) {
        $output['columns'][$table] = Schema::getColumnListing($table);
    }
    echo json_encode($output, JSON_PRETTY_PRINT).PHP_EOL;
    if (in_array(false, $output['checks'], true)) { exit(1); }
} finally {
    DB::rollBack();
}
