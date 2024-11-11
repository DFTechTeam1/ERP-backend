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
            $table->string('inventory_type', 20);
            $table->integer('custom_inventory_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_inventories', function (Blueprint $table) {
            $table->dropColumn('inventory_type');
            $table->dropColumn('custom_inventory_id');
        });
    }
};
