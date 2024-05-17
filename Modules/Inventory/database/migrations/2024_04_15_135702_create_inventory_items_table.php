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
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')
                ->references('id')
                ->on('inventories')
                ->cascadeOnDelete();
            $table->string('inventory_code', 50);
            $table->tinyInteger('status')
                ->comment('1 for in use, 2 for in repair, 3 for broke, 4 for disposal');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropForeign(['inventory_id']);
        });
        
        Schema::dropIfExists('inventory_items');
    }
};
