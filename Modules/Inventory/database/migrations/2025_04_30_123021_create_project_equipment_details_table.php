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
        Schema::create('project_equipment_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_equipment_id')
                ->references(column: 'id')
                ->on(table: 'project_equipments')
                ->cascadeOnDelete();
            $table->foreignId('inventory_item_id')
                ->references('id')
                ->on('inventory_items');
            $table->unsignedBigInteger('custom_inventory_id')->nullable();
            $table->integer('qty')->default(1);
            $table->string('equipment_type', 100);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_equipment_details', function (Blueprint $table) {
            if (checkForeignKey(tableName: 'project_equipment_details', columnName: 'project_equipment_id')) {
                $table->dropForeign(['project_equipment_id']);
            }

            if (checkForeignKey(tableName: 'project_equipment_details', columnName: 'inventory_item_id')) {
                $table->dropForeign(['inventory_item_id']);
            }
        });

        Schema::dropIfExists('project_equipment_details');
    }
};
