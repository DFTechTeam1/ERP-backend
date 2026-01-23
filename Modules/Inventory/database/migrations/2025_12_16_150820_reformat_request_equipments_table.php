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
        Schema::dropIfExists('request_equipment_histories');
        Schema::dropIfExists('request_equipment_movement_histories');
        Schema::dropIfExists('request_equipments');
        Schema::dropIfExists('request_equipment_masters');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Do nothing
    }
};
