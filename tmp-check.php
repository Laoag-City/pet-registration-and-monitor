<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$u = App\Models\User::where('email', 'like', 'scope-%')->get(['id', 'email']);
$p = App\Models\Pet::whereIn('owner_email', $u->pluck('email'))->get(['id', 'name', 'owner_email']);
echo json_encode([$u, $p], JSON_PRETTY_PRINT);
