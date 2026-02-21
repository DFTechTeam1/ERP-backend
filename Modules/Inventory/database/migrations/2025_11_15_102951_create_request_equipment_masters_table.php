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
        Schema::create('request_equipment_masters', function (Blueprint $table) {
            $table->id();
            $table->char('uid', 32)->unique();
            $table->date('purchased_date')->nullable();
            $table->string('batch_code');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_equipment_masters');
    }
};
