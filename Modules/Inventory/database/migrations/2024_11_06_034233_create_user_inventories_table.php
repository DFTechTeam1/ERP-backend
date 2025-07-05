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
        Schema::create('user_inventories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid');
            $table->foreignId('inventory_id')
                ->references('id')
                ->on('inventory_items')
                ->cascadeOnDelete();
            $table->foreignId('employee_id')
                ->references('id')
                ->on('employees')
                ->cascadeOnDelete();
            $table->tinyInteger('quantity')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_inventories', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropForeign(['inventory_id']);
        });

        Schema::dropIfExists('user_inventories');
    }
};
