<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_service_records', function (Blueprint $table) {
            $table->id();
            $table->date('visit_date')->index();
            $table->string('barangay', 150);
            $table->string('farmer_name', 150);
            $table->string('activity', 255);
            $table->string('species', 20);
            $table->string('sex', 10);
            $table->decimal('age_value', 7, 2)->nullable();
            $table->string('age_unit', 10)->nullable();
            $table->integer('number_of_heads');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_service_records');
    }
};
