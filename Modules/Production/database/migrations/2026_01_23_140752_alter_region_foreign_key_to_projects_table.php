<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add foreign key to country_id, state_id and city_id
        // Change column from int to bigint first
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedBigInteger('country_id')->nullable()->change();
            $table->unsignedBigInteger('state_id')->nullable()->change();
            $table->unsignedBigInteger('city_id')->nullable()->change();
        });

        // Clean up invalid foreign key references
        DB::statement('UPDATE projects SET country_id = NULL WHERE country_id NOT IN (SELECT id FROM countries)');
        DB::statement('UPDATE projects SET state_id = NULL WHERE state_id NOT IN (SELECT id FROM states)');
        DB::statement('UPDATE projects SET city_id = NULL WHERE city_id NOT IN (SELECT id FROM cities)');

        // Add foreign key constraints
        Schema::table('projects', function (Blueprint $table) {
            $table->foreign('country_id')->references('id')->on('countries')->onDelete('set null');
            $table->foreign('state_id')->references('id')->on('states')->onDelete('set null');
            $table->foreign('city_id')->references('id')->on('cities')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign key from country_id, state_id and city_id
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasIndex('projects', 'projects_country_id_foreign')) {
                $table->dropForeign(['country_id']);
            }
            if (Schema::hasIndex('projects', 'projects_state_id_foreign')) {
                $table->dropForeign(['state_id']);
            }
            if (Schema::hasIndex('projects', 'projects_city_id_foreign')) {
                $table->dropForeign(['city_id']);
            }
        });

        // Change column back to int
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedInteger('country_id')->nullable()->change();
            $table->unsignedInteger('state_id')->nullable()->change();
            $table->unsignedInteger('city_id')->nullable()->change();
        });
    }
};
