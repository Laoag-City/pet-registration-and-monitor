<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

Illuminate\Support\Facades\Auth::login(App\Models\User::first());

// simulate a photo upload through the controller store flow
$tmp = tempnam(sys_get_temp_dir(), 'pet') . '.png';
// 1x1 transparent PNG
file_put_contents($tmp, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

$request = Illuminate\Http\Request::create('/register-pet', 'POST', [
    'name' => 'PhotoPet',
    'species' => 'Dog',
    'sex' => 'Male',
    'owner_name' => 'T',
    'owner_email' => 't@t.com',
    'owner_number' => '1',
], [], [
    'photo' => new Illuminate\Http\UploadedFile($tmp, 'pet.png', 'image/png', null, true),
]);
$request->setLaravelSession(app('session.store'));

try {
    $response = app(App\Http\Controllers\PetController::class)->store($request);
    $pet = App\Models\Pet::where('name', 'PhotoPet')->first();
    if ($pet && $pet->photo_path && Illuminate\Support\Facades\Storage::disk('public')->exists($pet->photo_path)) {
        echo "upload OK: " . $pet->photo_path . "\n";
    } else {
        echo "upload FAILED\n";
    }
    if ($pet)
        $pet->delete();
} catch (Throwable $e) {
    echo 'error: ' . $e->getMessage() . "\n";
}
@unlink($tmp);
