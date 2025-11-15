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
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropColumn('warranty');
            $table->tinyInteger('warranty_month')->nullable()->after('description');
            $table->dropColumn('year_of_purchase');
            $table->date('purchased_date')->nullable()->after('warranty_month');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->integer('warranty')->nullable()->after('description');
            $table->integer('year_of_purchase')->nullable()->after('warranty');
            $table->dropColumn('warranty_month');
            $table->dropColumn('purchased_date');
        });
    }
};
