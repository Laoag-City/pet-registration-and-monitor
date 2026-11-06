<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// End-to-end HTTP smoke test (run: php bootstrap/http-test.php)
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Contracts\Http\Kernel as HttpKernel;

$pass = true;
function check(string $name, bool $ok): void
{
    global $pass;
    if (!$ok) {
        $pass = false;
    }
    echo ($ok ? 'PASS' : 'FAIL') . "  {$name}" . PHP_EOL;
}

function login(User $user): array
{
    app('session')->flush();
    auth()->login($user);
    return [];
}

function get(string $uri): Symfony\Component\HttpFoundation\Response
{
    $kernel = app(HttpKernel::class);
    $request = Illuminate\Http\Request::create($uri, 'GET');
    $request->setLaravelSession(app('session.store'));
    return $kernel->handle($request);
}

function checkStaffSidebar(string $uri, string $expectedLabel): void
{
    $html = (string) get($uri)->getContent();
    preg_match_all('/<nav class="app-nav nav flex-column gap-1">(.*?)<\/nav>/s', $html, $menus);
    $valid = count($menus[1]) === 2;

    foreach ($menus[1] as $menu) {
        preg_match_all('/<a class="nav-link active"[^>]*>(?:<i[^>]*><\/i>)?([^<]+)/', $menu, $activeLinks);
        $activeLabels = array_map('trim', $activeLinks[1]);
        $valid = $valid && count($activeLabels) === 1 && $activeLabels[0] === $expectedLabel;
    }

    check("Staff sidebar: {$uri} highlights only {$expectedLabel}", $valid);
}

$admin = User::where('role', 'admin')->first();
$staff = User::where('role', 'staff')->first();
$owner = User::where('role', 'user')->first();

// Owner tests
login($owner);
check('Owner dashboard 200', get('/dashboard')->getStatusCode() === 200);
check('Owner mypets 200', get('/mypets')->getStatusCode() === 200);
check('Owner qrcodes 200', get('/qrcodes')->getStatusCode() === 200);
check('Owner vaccinations 200', get('/vaccinations')->getStatusCode() === 200);
check('Owner deworming 200', get('/deworming')->getStatusCode() === 200);
check('Owner blocked from staff/users (403)', get('/staff/users')->getStatusCode() === 403);
check('Owner blocked from admin/users (403)', get('/admin/users')->getStatusCode() === 403);
check('Owner blocked from staff/pets (403)', get('/staff/pets')->getStatusCode() === 403);
check('Owner blocked from pets.create (404, route removed)', get('/register-pet')->getStatusCode() === 404);

// Staff tests
login($staff);
check('Staff dashboard 200', get('/staff/dashboard')->getStatusCode() === 200);
check('Staff users 200', get('/staff/users')->getStatusCode() === 200);
check('Staff user search works', get('/staff/users?q=' . urlencode($owner->name))->getStatusCode() === 200);
check('Staff all pets 200', get('/staff/pets')->getStatusCode() === 200);
check('Staff register pet form 200', get('/staff/pets/create')->getStatusCode() === 200);
check('Staff client details 200', get('/staff/users/' . $owner->id)->getStatusCode() === 200);
checkStaffSidebar('/staff/dashboard', 'Dashboard');
checkStaffSidebar('/staff/users', 'User Management');
checkStaffSidebar('/staff/pets', 'All Pets');
checkStaffSidebar('/staff/pets/create', 'Register Pet');
checkStaffSidebar('/vaccinations', 'Vaccinations');
checkStaffSidebar('/deworming', 'Deworming');
checkStaffSidebar('/qrcodes', 'QR Codes');
check('Staff blocked from admin/users (403)', get('/admin/users')->getStatusCode() === 403);
check('Staff blocked from admin dashboard (403)', get('/admin/dashboard')->getStatusCode() === 403);

// Admin tests
login($admin);
check('Admin dashboard 200', get('/admin/dashboard')->getStatusCode() === 200);
check('Admin users 200', get('/admin/users')->getStatusCode() === 200);
check('Admin create user form 200', get('/admin/users/create')->getStatusCode() === 200);
check('Admin edit user 200', get('/admin/users/' . $owner->id . '/edit')->getStatusCode() === 200);
check('Admin can access staff pages', get('/staff/pets')->getStatusCode() === 200);

// Pet ownership: owner cannot view someone else's pet
login($owner);
$otherPet = App\Models\Pet::where('owner_email', '!=', $owner->email)->first();
if ($otherPet) {
    check('Owner blocked from other user pet (403)', get('/pets/' . $otherPet->id)->getStatusCode() === 403);
    check('Owner blocked from other pet QR download (403)', get('/qrcodes/' . $otherPet->id . '/download')->getStatusCode() === 403);
} else {
    check('No other-owner pet to test (skipped as pass)', true);
}
if ($otherPet) {
    login($staff);
    check('Staff can view other user pet (200)', get('/pets/' . $otherPet->id)->getStatusCode() === 200);
}

// Guest redirect
auth()->logout();
$resp = get('/dashboard');
check('Guest redirected from dashboard', $resp->getStatusCode() === 302);

exit($pass ? 0 : 1);
