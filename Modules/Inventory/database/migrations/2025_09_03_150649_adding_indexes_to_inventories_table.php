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
            $table->unsignedBigInteger('brand_id')->nullable()->change();
            $table->foreign('brand_id')->references('id')->on('brands')
                ->nullable()
                ->onDelete('set null');

            $table->unsignedBigInteger('supplier_id')->nullable()->change();
            $table->foreign('supplier_id')->references('id')->on('suppliers')
                ->nullable()
                ->onDelete('set null');

            $table->unsignedBigInteger('item_type')->nullable()->change();
            $table->foreign('item_type')->references('id')->on('inventory_types')
                ->nullable()
                ->onDelete('set null');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop foreign if exists
        Schema::table('inventories', function (Blueprint $table) {
            if (checkForeignKey('inventories', 'brand_id')) {
                $table->dropForeign(['brand_id']);
            }
            if (checkForeignKey('inventories', 'supplier_id')) {
                $table->dropForeign(['supplier_id']);
            }
            if (checkForeignKey('inventories', 'supplier_id')) {
                $table->dropForeign(['supplier_id']);
            }
        });
    }
};
