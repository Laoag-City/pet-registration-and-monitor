<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Symfony\Component\Process\Process;

$connection = config('database.connections.'.config('database.default'));
if (config('database.default') !== 'pgsql') { throw new RuntimeException('Expected PostgreSQL.'); }
$directory = storage_path('app/private/backups');
if (! is_dir($directory)) { mkdir($directory, 0700, true); }
$path = $directory.'/pawid-pre-delete-audit-'.date('Ymd-His').'.dump';
$bin = 'C:/Program Files/PostgreSQL/18/bin/';
$env = ['PGHOST' => $connection['host'], 'PGPORT' => (string) $connection['port'],
    'PGDATABASE' => $connection['database'], 'PGUSER' => $connection['username'],
    'PGPASSWORD' => $connection['password'], 'PGSSLMODE' => $connection['sslmode'] ?? 'prefer'];
foreach ([[$bin.'pg_dump.exe', '--format=custom', '--no-password', '--file='.$path],
    [$bin.'pg_restore.exe', '--list', $path],
    [$bin.'pg_restore.exe', '--file=NUL', $path]] as $arguments) {
    $process = new Process($arguments, base_path(), $env, null, 120);
    $process->run();
    if (! $process->isSuccessful()) {
        throw new RuntimeException('Backup/verification failed; no migration allowed. Exit '.$process->getExitCode());
    }
}
echo json_encode(['backup' => $path, 'bytes' => filesize($path), 'sha256' => hash_file('sha256', $path),
    'verification' => 'Archive catalog and full SQL/data extraction succeeded; no database restored.'], JSON_PRETTY_PRINT).PHP_EOL;
