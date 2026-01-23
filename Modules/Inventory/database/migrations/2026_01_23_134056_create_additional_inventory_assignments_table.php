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
        Schema::create('additional_inventory_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_id')
                ->constrained('project_equipment_masters')
                ->onDelete('cascade');
            $table->uuid('uid');
            $table->foreignId('inventory_id')
                ->constrained('inventories');
            $table->integer('quantity');
            $table->text('note')->nullable();
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
        Schema::table('additional_inventory_assignments', function (Blueprint $table) {
            $table->dropForeign(['master_id']);
            $table->dropForeign(['inventory_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
        });

        Schema::dropIfExists('additional_inventory_assignments');
    }
};
