<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

DB::beginTransaction();
DB::statement('SET TRANSACTION READ ONLY');
try {
    $result = [];
    foreach (['users', 'pets', 'vaccinations', 'deworming_records', 'animal_health_monitoring_records', 'routine_service_records', 'notifications'] as $table) {
        // Compare original columns before/after the additive migration, without printing personal data.
        $columns = array_values(array_diff(Schema::getColumnListing($table), ['deleted_at']));
        $rows = DB::table($table)->select($columns)->orderBy('id')->get();
        $result[$table] = ['count' => $rows->count(), 'content_sha256' => hash('sha256', $rows->toJson())];
        $result[$table]['deleted_count'] = Schema::hasColumn($table, 'deleted_at') ? DB::table($table)->whereNotNull('deleted_at')->count() : 0;
    }
    $result['protected_account_present'] = DB::table('users')->where('email', 'geo@gmail.com')->where('name', 'Jeyo Pogi')->exists();
    $result['notification_owner_orphans'] = DB::table('notifications')->where('notifiable_type', App\Models\User::class)
        ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('users')->whereColumn('users.id', 'notifications.notifiable_id'))->count();
    foreach (['vaccinations' => 'vaccine', 'deworming_records' => 'product'] as $table => $field) {
        $result['possible_duplicate_groups'][$table] = DB::table($table)->select('pet_id', $field, 'administered_at')
            ->groupBy('pet_id', $field, 'administered_at')->havingRaw('COUNT(*) > 1')->get()->count();
        $groups = DB::table($table)->select('pet_id', $field, 'administered_at')->groupBy('pet_id', $field, 'administered_at')->havingRaw('COUNT(*) > 1')->get();
        foreach ($groups as $group) {
            $result['duplicate_review'][$table][] = [
                'pet_is_archived' => DB::table('pets')->where('id', $group->pet_id)->whereNotNull('deleted_at')->exists(),
                'records' => DB::table($table)->where('pet_id', $group->pet_id)->where($field, $group->$field)->where('administered_at', $group->administered_at)
                    ->get(['id', 'administered_at', 'next_due_at', 'schedule_completed_at', 'deleted_at']),
            ];
        }
    }
    foreach (['animal_health_monitoring_records', 'routine_service_records'] as $table) {
        $result['historical_barangays_requiring_review'][$table] = DB::table($table)->whereNotIn('barangay', App\Support\LaoagBarangays::labels())->count();
    }
    echo json_encode($result, JSON_PRETTY_PRINT).PHP_EOL;
} finally { DB::rollBack(); }
