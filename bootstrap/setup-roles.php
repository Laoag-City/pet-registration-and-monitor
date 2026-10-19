<?php

use App\Models\User;

// Promote the first existing account to admin so the owner can log in immediately.
$first = User::query()->orderBy('id')->first();
if ($first && $first->role !== 'admin') {
    $first->update(['role' => 'admin']);
    echo 'Promoted to admin: ' . $first->email . PHP_EOL;
}

// Create the default staff account if missing.
if (!User::where('email', 'staff@pawid.test')->exists()) {
    User::create([
        'name' => 'OCV Staff',
        'email' => 'staff@pawid.test',
        'contact_number' => '0000-000-0000',
        'date_of_birth' => '1990-01-01',
        'password' => 'password',
        'role' => 'staff',
    ]);
    echo 'Created staff account: staff@pawid.test (password: password)' . PHP_EOL;
}

echo User::count() . ' users. Roles: ' . User::selectRaw('role, count(*) as c')->groupBy('role')->pluck('c', 'role')->toJson() . PHP_EOL;
