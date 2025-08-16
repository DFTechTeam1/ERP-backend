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
        if (checkForeignKey('custom_inventory_details', 'inventory_id')) {
            Schema::table('custom_inventory_details', function (Blueprint $table) {
                $table->dropForeign(['inventory_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_inventory_details', function (Blueprint $table) {
            if (checkForeignKey('custom_inventory_details', 'inventory_id')) {
                $table->dropForeign(['inventory_id']);
            }
        });

    }
};
