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
        Schema::create('greatday_timezones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->bigInteger('timezone_id')
                ->unique();
            $table->string('gmt_ref_hour', 5);
            $table->string('gmt_ref_minute', 5);
            $table->string('gmt_plus_min', 1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('greatday_timezones');
    }
};
