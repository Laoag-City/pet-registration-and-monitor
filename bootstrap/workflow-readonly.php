<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{User, Pet, Vaccination, DewormingRecord};
use App\Support\PetQrReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Contracts\Http\Kernel;

config(['session.driver' => 'array', 'cache.default' => 'array']);
$checks = 0;
$failures = 0;
$check = function (string $label, bool $pass) use (&$checks, &$failures): void {
    $checks++;
    if (!$pass) { $failures++; }
    echo ($pass ? 'PASS ' : 'FAIL ').$label.PHP_EOL;
};
$get = fn (string $url) => $app->make(Kernel::class)->handle(Request::create($url, 'GET'));

DB::beginTransaction();
DB::statement('SET TRANSACTION READ ONLY');
try {
    $pet = Pet::whereHas('owner', fn ($query) => $query->where('role', 'user'))->with('owner')->first();
    $check('Existing owner/pet fixture available', $pet !== null);
    if ($pet) {
        foreach (['staff', 'admin'] as $role) {
            $manager = User::where('role', $role)->first();
            $check($role.' account available', $manager !== null);
            if (!$manager) { continue; }
            app('session.store')->flush();
            auth()->guard()->setUser($manager);
            foreach (['vaccinations', 'deworming'] as $type) {
                $response = $get(route($type.'.create'));
                $check($role.' '.$type.' owner selection', $response->getStatusCode() === 200 && str_contains($response->getContent(), 'Select Owner'));
                foreach (['name' => mb_strtolower($pet->owner->name), 'email' => $pet->owner->email, 'contact' => $pet->owner->contact_number] as $field => $search) {
                    if (!$search) { echo 'SKIP owner '.$field.' unavailable'.PHP_EOL; continue; }
                    $response = $get(route($type.'.create', ['q' => $search]));
                    $check($role.' '.$type.' search by '.$field, $response->getStatusCode() === 200 && str_contains($response->getContent(), e($pet->owner->email)));
                }
                $response = $get(route($type.'.create', ['owner_id' => $pet->owner->id]));
                $check($role.' '.$type.' pet selection', $response->getStatusCode() === 200 && str_contains($response->getContent(), e($pet->name)));
                $response = $get(route($type.'.create', ['owner_id' => $pet->owner->id, 'pet_id' => $pet->id]));
                $check($role.' '.$type.' details form', $response->getStatusCode() === 200 && str_contains($response->getContent(), 'Review Record'));
                $record = $type === 'vaccinations' ? Vaccination::first() : DewormingRecord::first();
                if ($record) {
                    $response = $get(route($type.'.edit', $record));
                    $check($role.' '.$type.' existing record edit form', $response->getStatusCode() === 200 && str_contains($response->getContent(), 'Save Changes'));
                } else {
                    echo 'SKIP '.$type.' edit: no existing record; covered in isolated tests'.PHP_EOL;
                }
            }
        }
        app('session.store')->flush();
        auth()->forgetGuards();
        $response = $get(PetQrReference::url($pet));
        $html = $response->getContent();
        $check('Public QR shows correct current names', $response->getStatusCode() === 200 && str_contains($html, e($pet->name)) && str_contains($html, e($pet->owner->name)));
        $check('Public QR excludes owner email and PET code', !str_contains($html, e($pet->owner->email)) && !str_contains($html, e($pet->code)));
        $check('Public QR does not set session cookies', count($response->headers->getCookies()) === 0);
        $check('Legacy QR stays login-protected', $get('/pets/'.$pet->id)->getStatusCode() === 302);
    }
    $response = $get('/scan/invalid-reference');
    $check('Invalid public QR returns generic 404', $response->getStatusCode() === 404 && str_contains($response->getContent(), 'Pet not found'));
} finally {
    DB::rollBack();
}

echo "Checks: {$checks}; failures: {$failures}".PHP_EOL;
exit($failures ? 1 : 0);
