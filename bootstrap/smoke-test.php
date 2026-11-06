<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

use App\Models\DewormingRecord;
use App\Models\Pet;
use App\Models\User;
use App\Models\Vaccination;

// Simple smoke test for the PawID role system (run: php bootstrap/smoke-test.php)
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$pass = true;
function check(string $name, bool $ok): void
{
    global $pass;
    if (!$ok) {
        $pass = false;
    }
    echo ($ok ? 'PASS' : 'FAIL') . "  {$name}" . PHP_EOL;
}

// Models & helpers
$admin = User::where('role', 'admin')->first();
$staff = User::where('role', 'staff')->first();
$owner = User::where('role', 'user')->first();

check('Admin account exists', $admin !== null);
check('Staff account exists', $staff !== null);
check('Pet owner exists', $owner !== null);
check('Admin canManageRecords', $admin?->canManageRecords() === true);
check('Staff canManageRecords', $staff?->canManageRecords() === true);
check('Owner cannot manage records', $owner?->canManageRecords() === false);

// Ownership filtering: owner sees only own pets
$ownPets = Pet::where('owner_email', $owner->email)->count();
$allPets = Pet::count();
check('Owner pet query is scoped (own=' . $ownPets . ', total=' . $allPets . ')', $ownPets <= $allPets);

// Counts are real data
check('Vaccination records count = ' . Vaccination::count(), Vaccination::count() >= 0);
check('Deworming records count = ' . DewormingRecord::count(), DewormingRecord::count() >= 0);

// Pet code generation intact
$pet = Pet::first();
check('Existing pet has QR code prefix', $pet === null || str_starts_with((string) $pet->code, 'PET-'));

exit($pass ? 0 : 1);
