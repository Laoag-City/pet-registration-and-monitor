<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['vaccinations', 'deworming_records'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                // Completes this row's NEXT schedule, not its historical administration.
                $table->date('schedule_completed_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['vaccinations', 'deworming_records'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('schedule_completed_at'));
        }
    }
};
