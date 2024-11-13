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
        Schema::create('employee_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_inventory_master_id');
            $table->unsignedBigInteger('inventory_item_id');
            $table->unsignedBigInteger('inventory_status')
                ->comment('1 for active, 2 for disable')
                ->default(1);

            $table->foreign('employee_inventory_master_id')
                ->references('id')->on('employee_inventory_masters')
                ->cascadeOnDelete();
            $table->foreign('inventory_item_id')
                ->references('id')->on('inventory_items')
                ->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_inventory_items', function (Blueprint $table) {
            $table->dropForeign(['inventory_item_id']);
            $table->dropForeign(['employee_inventory_master_id']);
        });
        Schema::dropIfExists('employee_inventory_items');
    }
};
