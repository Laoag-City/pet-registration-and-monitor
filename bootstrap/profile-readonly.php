<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
    foreach (['/profile', '/profile/edit'] as $url) {
        $check('Guest redirected: '.$url, $get($url)->getStatusCode() === 302);
    }
    foreach (['user', 'staff', 'admin'] as $role) {
        $user = User::where('role', $role)->first();
        $check($role.' fixture exists', $user !== null);
        if (!$user) { continue; }
        app('session.store')->flush();
        auth()->guard()->setUser($user);
        foreach (['/profile', '/profile/edit'] as $url) {
            $response = $get($url);
            $check($role.' response: '.$url, $response->getStatusCode() === ($role === 'user' ? 200 : 403));
            if ($role === 'user') {
                $html = $response->getContent();
                $check('Own identity displayed: '.$url, str_contains($html, e($user->name)) && str_contains($html, e($user->email)));
                $check('Credentials absent: '.$url, !str_contains($html, $user->password) && (!$user->remember_token || !str_contains($html, $user->remember_token)));
            }
        }
    }
} finally {
    DB::rollBack();
}
echo "Checks: {$checks}; failures: {$failures}".PHP_EOL;
exit($failures ? 1 : 0);
