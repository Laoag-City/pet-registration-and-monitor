<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['vaccinations', 'deworming_records', 'animal_health_monitoring_records', 'routine_service_records'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->softDeletes()->index());
        }
        Schema::create('record_deletion_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id');
            $table->string('record_table', 80);
            $table->unsignedBigInteger('record_id');
            $table->string('action', 30);
            $table->timestamp('created_at');
            $table->index(['record_table', 'record_id']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Forward-only: retain soft-delete markers and audit history.');
    }
};
