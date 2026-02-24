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
        Schema::create('request_equipment_movement_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('request_equipments')->onDelete('cascade');
            $table->string('action');
            $table->string('description');
            $table->string('user');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop foreign key
        Schema::table('request_equipment_movement_histories', function (Blueprint $table) {
            $table->dropForeign(['request_id']);
        });

        Schema::dropIfExists('request_equipment_movement_histories');
    }
};
