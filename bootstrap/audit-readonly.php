<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Aggregate-only audit. Never print credentials, hashes, or personal records.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

DB::beginTransaction();
DB::statement('SET TRANSACTION READ ONLY');
try {
    $result = ['server' => DB::selectOne('SELECT version() AS version, inet_server_port() AS port')];
    foreach (['users', 'pets', 'vaccinations', 'deworming_records'] as $table) {
        $result['counts'][$table] = DB::table($table)->count();
        $result['missing_timestamps'][$table] = DB::table($table)->whereNull('created_at')->orWhereNull('updated_at')->count();
        $result['foreign_keys'][$table] = Schema::getForeignKeys($table);
    }
    $result['roles'] = DB::table('users')->select('role')->selectRaw('count(*) as count')->groupBy('role')->get();
    $result['pets_without_matching_user'] = DB::table('pets')->whereNotExists(fn ($q) => $q->selectRaw('1')->from('users')->whereColumn('users.email', 'pets.owner_email'))->count();
    $result['sessions_without_matching_user'] = DB::table('sessions')->whereNotNull('user_id')->whereNotExists(fn ($q) => $q->selectRaw('1')->from('users')->whereColumn('users.id', 'sessions.user_id'))->count();
    foreach (['users', 'pets'] as $table) {
        $result['future_birth_dates'][$table] = DB::table($table)->where('date_of_birth', '>', today())->count();
    }
    $result['nullable_owner_fields'] = DB::table('pets')->whereNull('owner_email')->orWhereNull('owner_name')->count();
    foreach (['vaccinations', 'deworming_records'] as $table) {
        $result['orphaned'][$table] = DB::table($table)->whereNotExists(fn ($q) => $q->selectRaw('1')->from('pets')->whereColumn('pets.id', $table.'.pet_id'))->count();
        $result['invalid_dates'][$table] = DB::table($table)->whereColumn('next_due_at', '<', 'administered_at')->count();
    }
    $result['unrecognized_password_formats'] = DB::table('users')->pluck('password')->filter(fn ($value) => password_get_info($value)['algoName'] === 'unknown')->count();
    $result['missing_photo_files'] = DB::table('pets')->whereNotNull('photo_path')->pluck('photo_path')->filter(fn ($path) => !is_file(storage_path('app/public/'.$path)))->count();
    $result['session'] = ['http_only' => config('session.http_only'), 'same_site' => config('session.same_site'), 'secure' => (bool) config('session.secure')];
    $log = storage_path('logs/laravel.log');
    $result['log'] = ['exists' => is_file($log)];
    if (is_file($log)) {
        $lines = file($log);
        $events = [];
        foreach ($lines as $line) {
            if (preg_match('/^\[([^]]+)\] \w+\.(ERROR|CRITICAL|EMERGENCY):/', $line, $match)) {
                $category = 'other (message withheld)';
                foreach (['SQLSTATE', 'Vite manifest', 'Undefined', 'Call to', 'ParseError', 'could not find driver', 'GD extension'] as $needle) {
                    if (str_contains($line, $needle)) { $category = $needle; break; }
                }
                $events[] = ['time' => $match[1], 'level' => $match[2], 'category' => $category];
            }
        }
        $result['log']['total_errors'] = count($events);
        $result['log']['latest_errors'] = array_slice($events, -8);
    }
    echo json_encode($result, JSON_PRETTY_PRINT).PHP_EOL;
} finally {
    DB::rollBack();
}
