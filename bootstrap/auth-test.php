<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Authorization + view rendering smoke test (run: php bootstrap/auth-test.php)
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Middleware\EnsureRole;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Http\Request;

$pass = true;
function check(string $name, bool $ok): void
{
    global $pass;
    if (! $ok) { $pass = false; }
    echo ($ok ? 'PASS' : 'FAIL') . "  {$name}" . PHP_EOL;
}

$mw = new EnsureRole();

function requestAs(?User $user): Request
{
    $request = Request::create('/test');
    $request->setUserResolver(fn () => $user);
    return $request;
}

$admin = User::where('role', 'admin')->first();
$staff = User::where('role', 'staff')->first();
$owner = User::where('role', 'user')->first();
$pet = Pet::first();

// Middleware behavior
try { $mw->handle(requestAs($owner), fn () => new Symfony\Component\HttpFoundation\Response('ok'), 'staff', 'admin'); $blocked = false; } catch (Throwable) { $blocked = true; }
check('Owner blocked from staff/admin middleware', $blocked);

try { $mw->handle(requestAs($staff), fn () => new Symfony\Component\HttpFoundation\Response('ok'), 'staff', 'admin'); $ok = true; } catch (Throwable) { $ok = false; }
check('Staff allowed on staff routes', $ok);

try { $mw->handle(requestAs($staff), fn () => new Symfony\Component\HttpFoundation\Response('ok'), 'admin'); $ok = false; } catch (Throwable) { $ok = true; }
check('Staff blocked from admin-only routes', $ok);

try { $mw->handle(requestAs($admin), fn () => new Symfony\Component\HttpFoundation\Response('ok'), 'admin'); $ok = true; } catch (Throwable) { $ok = false; }
check('Admin allowed on admin routes', $ok);

try { $mw->handle(requestAs(null), fn () => new Symfony\Component\HttpFoundation\Response('ok'), 'admin'); $ok = false; } catch (Throwable) { $ok = true; }
check('Guest blocked from role middleware', $ok);

// Pet ownership checks
if ($owner && $pet && $pet->owner_email !== $owner->email) {
    check('Owner cannot view another user\'s pet (canManageRecords false, email mismatch)', ! $owner->canManageRecords() && $pet->owner_email !== $owner->email);
}
if ($staff && $pet) {
    check('Staff can access any pet record (canManageRecords)', $staff->canManageRecords());
}

// Views render
view()->share('errors', new Illuminate\Support\ViewErrorBag);
$views = [
    'staff.dashboard' => fn () => view('staff.dashboard', ['user' => $admin, 'userCount' => 0, 'staffCount' => 0, 'petCount' => 0]),
    'staff.users' => fn () => view('staff.users', ['users' => User::where('role', 'user')->paginate(12), 'search' => '']),
    'staff.user-details' => fn () => view('staff.user-details', ['client' => $owner, 'pets' => collect()]),
    'staff.pets' => fn () => view('staff.pets', ['pets' => Pet::paginate(15), 'search' => '', 'speciesFilter' => '', 'speciesList' => collect()]),
    'staff.register-pet' => fn () => view('staff.register-pet', ['clients' => collect(), 'search' => '', 'selectedClient' => null]),
    'admin.dashboard' => fn () => view('admin.dashboard', ['user' => $admin, 'userCount' => 0, 'staffCount' => 0, 'petCount' => 0, 'recentUsers' => collect(), 'recentPets' => collect()]),
    'admin.users' => fn () => view('admin.users', ['users' => User::query()->paginate(12), 'search' => '', 'roleFilter' => '']),
    'admin.create-user' => fn () => view('admin.create-user'),
    'admin.edit-user' => fn () => view('admin.edit-user', ['editUser' => $owner]),
    'dashboard (owner)' => fn () => view('dashboard', ['user' => $owner, 'pets' => collect(), 'petCount' => 0, 'vaccinationCount' => 0, 'dewormingCount' => 0]),
];

foreach ($views as $name => $make) {
    try {
        $html = $make()->render();
        check("View renders: {$name}", strlen($html) > 0);
    } catch (Throwable $e) {
        check("View renders: {$name} — {$e->getMessage()}", false);
    }
}

exit($pass ? 0 : 1);

