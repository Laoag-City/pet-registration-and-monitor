<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_change_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id');
            $table->string('record_table', 80);
            $table->unsignedBigInteger('record_id');
            $table->string('action', 30);
            $table->string('reason', 500)->nullable();
            $table->json('changes')->nullable();
            $table->timestamp('created_at');
            $table->index(['record_table', 'record_id']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Forward-only: preserve the record audit trail.');
    }
};
