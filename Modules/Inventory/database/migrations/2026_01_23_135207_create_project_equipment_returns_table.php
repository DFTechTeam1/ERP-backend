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
        Schema::create('project_equipment_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_id')
                ->constrained('project_equipment_masters')
                ->onDelete('cascade');
            $table->uuid('uid');
            $table->date('return_date');
            $table->foreignId('inventory_id')
                ->constrained('inventories');
            $table->integer('issued_total');
            $table->integer('returned_total')->nullable();
            $table->integer('missing_total')->nullable();
            $table->integer('broken_total')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign key
        Schema::table('project_equipment_returns', function (Blueprint $table) {
            $table->dropForeign(['master_id']);
            $table->dropForeign(['inventory_id']);
        });

        Schema::dropIfExists('project_equipment_returns');
    }
};
