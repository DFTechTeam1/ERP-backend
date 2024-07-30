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
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('iso3');
            $table->string('iso2');
            $table->string('phone_code');
            $table->string('currency');
        });

        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('country_id');
            $table->string('name');
            $table->string('country_code');
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('country_id');
            $table->bigInteger('state_id');
            $table->string('name');
            $table->string('country_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('countries');
        Schema::dropIfExists('states');
        Schema::dropIfExists('cities');
    }
};
