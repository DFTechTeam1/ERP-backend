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
        Schema::create('greatday_cost_centers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)
                ->unique();
            $table->string('name_en');
            $table->string('name_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('greatday_cost_centers');
    }
};
