<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Pet, User};
use App\Services\PetCareReminders;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};

config(['session.driver' => 'array', 'cache.default' => 'array']);
$result = ['checks' => [], 'counts' => [], 'fingerprints' => []];
$baseline = in_array('--baseline', $argv, true);
$check = function (string $name, bool $pass) use (&$result): void { $result['checks'][$name] = $pass; };
$get = fn (string $url) => $app->make(Kernel::class)->handle(Request::create($url, 'GET'));

DB::beginTransaction();
DB::statement('SET TRANSACTION READ ONLY');
try {
    // Aggregate fingerprints compare complete domain rows without printing any personal data or credentials.
    foreach (['users', 'pets', 'vaccinations', 'deworming_records'] as $table) {
        $result['counts'][$table] = DB::table($table)->count();
        $digest = hash_init('sha256');
        foreach (DB::table($table)->orderBy('id')->cursor() as $row) {
            hash_update($digest, json_encode($row, JSON_THROW_ON_ERROR));
        }
        $result['fingerprints'][$table] = hash_final($digest);
    }
    $result['notifications_table_exists'] = Schema::hasTable('notifications');
    $result['timezone'] = config('app.timezone');
    $check('Legacy microchip column retained', Schema::hasColumn('pets', 'microchip'));
    $check('Existing vaccination due date reused', Schema::hasColumn('vaccinations', 'next_due_at'));
    $check('Existing deworming due date reused', Schema::hasColumn('deworming_records', 'next_due_at'));

    if (! $baseline && $result['notifications_table_exists']) {
        $check('Guest cannot view notifications', $get('/notifications')->getStatusCode() === 302);
        $result['notification_count'] = DB::table('notifications')->count();
        // PostgreSQL enforces that even the generator's dry-run cannot write here.
        $result['dry_run_new_reminders'] = app(PetCareReminders::class)->generate(true);
        $check('Dry run does not create notifications', $result['notification_count'] === DB::table('notifications')->count());

        foreach (['user', 'staff', 'admin'] as $role) {
            $user = User::where('role', $role)->when($role === 'user', fn ($q) => $q->whereHas('pets'))->first();
            $check($role.' fixture exists', $user !== null);
            if (! $user) { continue; }
            app('session.store')->flush();
            auth()->guard()->setUser($user);
            $response = $get('/notifications');
            $check($role.' notification access', $response->getStatusCode() === ($role === 'user' ? 200 : 403));
            if ($role === 'user') {
                foreach (['/dashboard', '/profile', '/profile/edit', '/mypets', '/vaccinations', '/deworming'] as $url) {
                    $response = $get($url);
                    $check('Owner page renders: '.$url, $response->getStatusCode() === 200);
                    $check('Microchip absent: '.$url, !str_contains(strtolower($response->getContent()), 'microchip'));
                }
                $schedules = app(PetCareReminders::class)->schedules($user);
                $check('All schedules belong to session owner', $schedules->every(fn ($s) => $s['pet']->owner_email === $user->email));
                $pet = $user->pets()->first();
                $response = $get(\App\Support\PetQrReference::url($pet));
                $check('Public QR uses current owner and pet names', $response->getStatusCode() === 200 && str_contains($response->getContent(), e($user->name)) && str_contains($response->getContent(), e($pet->name)));
                $check('Public QR excludes microchip and email', !str_contains(strtolower($response->getContent()), 'microchip') && !str_contains($response->getContent(), $user->email));
            } else {
                $pet = Pet::first();
                foreach (['/staff/pets/create?client='.$pet->owner->id, '/staff/pets/'.$pet->id.'/edit', '/pets/'.$pet->id, '/qrcodes/'.$pet->id.'/preview'] as $url) {
                    $response = $get($url);
                    $check($role.' page renders without microchip: '.$url, $response->getStatusCode() === 200 && !str_contains(strtolower($response->getContent()), 'microchip'));
                }
            }
        }
    }
} finally {
    DB::rollBack();
}

$result['failed_checks'] = count(array_filter($result['checks'], fn ($pass) => ! $pass));
echo json_encode($result, JSON_PRETTY_PRINT).PHP_EOL;
exit($result['failed_checks'] ? 1 : 0);
