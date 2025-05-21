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
        Schema::create('event_equipment_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_equipment_id')
                ->references('id')
                ->on('event_equipments')
                ->cascadeOnDelete();
            $table->foreignId('inventory_item_id')
                ->references('id')
                ->on('inventory_items');
            $table->foreignId('custom_detail_id')
                ->nullable()
                ->constrained('custom_inventory_details');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_equipment_details');
    }
};
