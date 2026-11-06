<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$u = User::where('email', 'usera@example.com')->first();
echo $u ? 'USER: ' . $u->name . ' | contact=' . $u->contact_number . ' | verified=' . var_export($u->email_verified_at !== null, true) . PHP_EOL : 'NOT FOUND' . PHP_EOL;
