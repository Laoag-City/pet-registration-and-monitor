<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$pet = App\Models\Pet::create(['name' => 'QRTest', 'species' => 'dog', 'sex' => 'M', 'owner_name' => 'T']);
if (!$pet) {
    echo "no pets\n";
    exit;
}
$response = app(App\Http\Controllers\QRCodeController::class)->show($pet);
echo 'content-type: ' . $response->headers->get('Content-Type') . "\n";
echo substr($response->getContent(), 0, 120) . "\n";
