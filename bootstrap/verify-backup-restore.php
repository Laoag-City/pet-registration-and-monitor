<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

$root = realpath(storage_path('app/private/backups'));
$backup = realpath($argv[1] ?? '');
if (!$root || !$backup || !str_starts_with($backup, $root.DIRECTORY_SEPARATOR) || !str_ends_with($backup, '.dump')) {
    throw new RuntimeException('Supply a verified local .dump in the private backups directory.');
}
if (config('database.default') !== 'pgsql') throw new RuntimeException('Expected PostgreSQL.');
$source = config('database.connections.pgsql');
$target = 'pawid_restore_check_'.date('Ymd_His').'_'.bin2hex(random_bytes(3));
$env = ['PGHOST' => $source['host'], 'PGPORT' => (string) $source['port'], 'PGUSER' => $source['username'],
    'PGPASSWORD' => $source['password'], 'PGSSLMODE' => $source['sslmode'] ?? 'prefer'];
$bin = 'C:/Program Files/PostgreSQL/18/bin/';
// Always create a fresh generated database. Never use --clean, --create, or the source database as restore target.
foreach ([[$bin.'createdb.exe', '--no-password', '--template=template0', $target],
    [$bin.'pg_restore.exe', '--no-password', '--exit-on-error', '--no-owner', '--no-privileges', '--dbname='.$target, $backup]] as $args) {
    $process = new Process($args, base_path(), $env, null, 120);
    $process->run();
    if (!$process->isSuccessful()) throw new RuntimeException('Restore rehearsal failed (exit '.$process->getExitCode().'); isolated database: '.$target);
}
config(['database.connections.restore_check' => array_replace($source, ['database' => $target, 'url' => null])]);
$restored = DB::connection('restore_check');
if ($restored->getDatabaseName() === DB::connection()->getDatabaseName()) throw new RuntimeException('Restore isolation check failed.');
$result = ['isolated_database' => $target, 'backup_sha256' => hash_file('sha256', $backup), 'tables' => []];
foreach (['users', 'pets', 'vaccinations', 'deworming_records', 'animal_health_monitoring_records', 'routine_service_records', 'notifications', 'record_deletion_audits'] as $table) {
    $originalRows = DB::table($table)->orderBy('id')->get()->toJson();
    $restoredRows = $restored->table($table)->orderBy('id')->get()->toJson();
    $match = hash('sha256', $originalRows) === hash('sha256', $restoredRows);
    $result['tables'][$table] = ['rows' => $restored->table($table)->count(), 'matches_source' => $match];
    if (!$match) throw new RuntimeException('Restored content differs from current source in '.$table.'. No source data changed.');
}
echo json_encode($result, JSON_PRETTY_PRINT).PHP_EOL;
// Retain the isolated database for review. This script never drops databases or tables.
