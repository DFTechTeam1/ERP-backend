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
        Schema::table('employee_inventory_masters', function (Blueprint $table) {
            $table->json('custom_inventory_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_inventory_masters', function (Blueprint $table) {
            $table->dropColumn('custom_inventory_id');
        });
    }
};
