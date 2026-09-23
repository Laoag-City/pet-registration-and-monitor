<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Exercise real PostgreSQL records without writing sessions, users, or domain data.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{User, Pet};
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Contracts\Http\Kernel;

config(['session.driver' => 'array', 'cache.default' => 'array']);
DB::beginTransaction();
DB::statement('SET TRANSACTION READ ONLY');
$failed = 0;
$checked = 0;
$check = function (string $label, bool $ok) use (&$failed, &$checked): void {
    $checked++;
    if (!$ok) { $failed++; }
    echo ($ok ? 'PASS ' : 'FAIL ').$label.PHP_EOL;
};
$get = function (string $url) use ($app) {
    return $app->make(Kernel::class)->handle(Request::create($url, 'GET'));
};
try {
    foreach (['user', 'staff', 'admin'] as $role) {
        $user = User::where('role', $role)->first();
        $check($role.' account present', $user !== null);
        if (!$user) { continue; }
        app('session.store')->flush();
        auth()->guard()->setUser($user);
        foreach (['/dashboard', '/mypets', '/vaccinations', '/deworming', '/qrcodes'] as $url) {
            $check($role.' '.$url, $get($url)->getStatusCode() === 200);
        }
        foreach (['/staff/dashboard', '/staff/users', '/staff/pets', '/staff/pets/create'] as $url) {
            $check($role.' '.$url, $get($url)->getStatusCode() === ($role === 'user' ? 403 : 200));
        }
        foreach (['/admin/dashboard', '/admin/users', '/admin/users/create'] as $url) {
            $check($role.' '.$url, $get($url)->getStatusCode() === ($role === 'admin' ? 200 : 403));
        }
        foreach (Pet::all() as $pet) {
            $expected = $user->canManageRecords() || $pet->owner_email === $user->email ? 200 : 403;
            foreach (['/pets/'.$pet->id, '/qrcodes/'.$pet->id.'/preview', '/qrcodes/'.$pet->id.'/download'] as $url) {
                $check($role.' '.$url, $get($url)->getStatusCode() === $expected);
            }
        }
    }
    auth()->forgetGuards();
    foreach (['/', '/login', '/register', '/features', '/how-it-works', '/up'] as $url) {
        $check('guest '.$url, $get($url)->getStatusCode() === 200);
    }
    $check('guest protected route redirects', $get('/dashboard')->getStatusCode() === 302);
    $response = $app->make(Kernel::class)->handle(Request::create('/login', 'POST', ['email' => 'audit-invalid@example.test', 'password' => 'invalid-test-only']));
    $check('CSRF missing token rejected', $response->getStatusCode() === 419);
} finally {
    DB::rollBack();
}
echo "Checks: {$checked}; failures: {$failed}".PHP_EOL;
exit($failed ? 1 : 0);
