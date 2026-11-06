<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
try {
    DB::connection()->getPdo();
    echo 'Connected: ' . DB::connection()->getDatabaseName() . ' driver: ' . DB::connection()->getDriverName() . PHP_EOL;
} catch (Throwable $ex) {
    echo 'FAIL: ' . $ex->getMessage() . PHP_EOL;
}
