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
        Schema::table('employee_inventory_items', function (Blueprint $table) {
            $table->enum('inventory_source', ['custom', 'inventory']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_inventory_items', function (Blueprint $table) {
            $table->dropColumn('inventory_source');
        });
    }
};
