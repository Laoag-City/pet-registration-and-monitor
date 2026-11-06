<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

use App\Models\Pet;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/bootstrap/app.php';

$p = Pet::first();
$svg = (new QRCode(new QROptions(['outputType' => QRCode::OUTPUT_MARKUP_SVG, 'scale' => 8])))->render(url('/pets/' . $p->id));
echo $p->code . ' -> OK, svg length: ' . strlen($svg);
