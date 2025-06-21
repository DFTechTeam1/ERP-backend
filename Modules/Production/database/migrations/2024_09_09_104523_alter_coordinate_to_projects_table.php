<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function ($table) {
            $table->string('longitude', 150)->nullable();
            $table->string('latitude', 150)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function ($table) {
            $table->dropColumn('longitude');
            $table->dropColumn('latitude');
        });
    }
};
