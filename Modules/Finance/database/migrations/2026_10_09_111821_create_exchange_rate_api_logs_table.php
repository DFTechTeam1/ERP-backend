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
        Schema::create('exchange_rate_api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->text('response');
            $table->boolean('is_success');
            $table->string('response_code', 10)->nullable();
            $table->string('actor_name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rate_api_logs');
    }
};
