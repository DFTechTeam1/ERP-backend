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
        Schema::create('custom_inventory_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_inventory_id')
                ->references('id')
                ->on('custom_inventories')
                ->cascadeOnDelete();
            $table->integer('inventory_id');
            $table->integer('qty');
            $table->double('price');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_inventory_details', function (Blueprint $table) {
            $table->dropForeign(['custom_inventory_id']);
            $table->dropForeign(['inventory_id']);
        });

        Schema::dropIfExists('custom_inventory_details');
    }
};
