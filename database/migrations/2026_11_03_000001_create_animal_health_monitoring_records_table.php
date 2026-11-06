<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('animal_health_monitoring_records', function (Blueprint $table) {
            $table->id();
            $table->date('visit_date')->index();
            $table->string('barangay', 150);
            $table->string('farmer_name', 150);
            $table->string('species', 20);
            $table->string('sex', 10);
            $table->decimal('age_value', 7, 2)->nullable();
            $table->string('age_unit', 10)->nullable();
            $table->integer('cases')->nullable();
            $table->text('clinical_signs')->nullable();
            $table->string('disorder', 255)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animal_health_monitoring_records');
    }
};
