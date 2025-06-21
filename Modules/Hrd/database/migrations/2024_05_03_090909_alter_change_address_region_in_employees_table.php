<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('country_id');
            $table->dropColumn('province_id');
            $table->dropColumn('city_id');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->integer('province_id')->nullable();
            $table->integer('city_id')->nullable();
            $table->bigInteger('district_id')->nullable();
            $table->bigInteger('village_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('province_id');
            $table->dropColumn('city_id');
            $table->dropColumn('district_id');
            $table->dropColumn('village_id');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->integer('country_id')->nullable();
            $table->integer('province_id')->nullable();
            $table->integer('city_id')->nullable();
        });
    }
};
