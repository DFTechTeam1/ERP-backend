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
        Schema::create('custom_inventory_assingments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid');
            $table->foreignId('master_id')
                ->constrained('project_equipment_masters')
                ->onDelete('cascade');
            $table->foreignId('inventory_id')
                ->constrained('inventories');
            $table->foreignId('created_by') 
                ->constrained('users');
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign key
        Schema::table('custom_inventory_assingments', function (Blueprint $table) {
            $table->dropForeign(['master_id']);
            $table->dropForeign(['inventory_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
        });

        Schema::dropIfExists('custom_inventory_assingments');
    }
};
