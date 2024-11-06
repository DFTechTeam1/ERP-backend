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
        Schema::table('user_inventories', function (Blueprint $table) {
            $table->foreignId('user_inventory_master_id')
                ->references('id')
                ->on('user_inventory_masters')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
//        Schema::table('user_inventories', function (Blueprint $table) {
//            $table->dropForeign(['user_inventory_master_id']);
//        });

        Schema::table('user_inventories', function (Blueprint $table) {
            $table->dropColumn('user_inventory_master_id');
        });
    }
};
