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
        Schema::create('employee_custom_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->references('id')
                ->on('employees')
                ->cascadeOnDelete();
            $table->foreignId('custom_inventory_id')
                ->references('id')
                ->on('custom_inventories')
                ->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_custom_inventories', function (Blueprint $table) {
            $table->dropForeign(['custom_inventory_id']);
            $table->dropForeign(['employee_id']);
        });
        Schema::dropIfExists('employee_custom_inventories');
    }
};
