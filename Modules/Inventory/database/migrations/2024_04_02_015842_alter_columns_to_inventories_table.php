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
        Schema::table('brands', function (Blueprint $table) {
            $table->uuid('uid');
            $table->string('name');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->uuid('uid');
            $table->string('name');
        });

        Schema::table('inventories', function (Blueprint $table) {
            $table->uuid('uid');
            $table->string('name');
            $table->string('inventory_code', 20);
            $table->integer('item_type');
            $table->integer('brand_id');
            $table->integer('supplier_id');
            $table->string('qrcode')->nullable();
            $table->text('description')->nullable();
            $table->tinyInteger('warranty')->nullable()
                ->comment('in years');
            $table->year('year_of_purchase')->nullable();
            $table->double('purchase_price')->default(0);
            $table->tinyInteger('status')
                ->comment('1 for in use, 2 for in repair, 3 for broke, 4 for disposal');
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropColumn('uid');
            $table->dropColumn('name');
            $table->dropColumn('inventory_code');
            $table->dropColumn('item_type');
            $table->dropColumn('brand_id');
            $table->dropColumn('supplier_id');
            $table->dropColumn('qrcode');
            $table->dropColumn('description');
            $table->dropColumn('warranty');
            $table->dropColumn('year_of_purchase');
            $table->dropColumn('purchase_price');
            $table->dropColumn('status');
            $table->dropColumn('created_by');
            $table->dropColumn('updated_by');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('uid');
            $table->dropColumn('name');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('uid');
            $table->dropColumn('name');
        });
    }
};
