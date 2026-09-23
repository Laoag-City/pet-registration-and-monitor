<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->softDeletes()->index();
        });
    }

    public function down(): void
    {
        // Removing this column would reactivate archived pets. Roll forward instead.
        throw new \RuntimeException('Pet archival is forward-only; retain deleted_at to preserve removal state.');
    }
};
