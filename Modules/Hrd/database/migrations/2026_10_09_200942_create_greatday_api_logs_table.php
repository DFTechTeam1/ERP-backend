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
        Schema::create('greatday_api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('url', 150);
            $table->string('creator', 200);
            $table->json('response')->nullable();
            $table->json('payload')->nullable();
            $table->boolean('is_success')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('greatday_api_logs');
    }
};
