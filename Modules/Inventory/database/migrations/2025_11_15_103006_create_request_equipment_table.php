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
        Schema::create('request_equipments', function (Blueprint $table) {
            $table->id();
            $table->softDeletes();
            $table->char('uid', 32)->unique();
            $table->foreignId('master_id')->constrained('request_equipment_masters')->onDelete('cascade');
            $table->string('name');
            $table->integer('quantity');
            $table->bigInteger('price');
            $table->bigInteger('total_price');
            $table->foreignId('inventory_id')->nullable()->constrained('inventories')->onDelete('set null');
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->foreignId('brand_id')->constrained('brands')->onDelete('cascade');
            $table->foreignId('unit_id')->constrained('units')->onDelete('cascade');
            $table->foreignId('inventory_type_id')->constrained('inventory_types')->onDelete('cascade');
            $table->tinyInteger('warehouse_id')->nullable();
            $table->string('image')->nullable();
            $table->string('link')->nullable();
            $table->enum('status', [
                'on_request',
                'on_process',
                'returned',
                'arrived',
                'canceled'
            ]);
            $table->enum('order_type', [
                'online',
                'offline'
            ]);
            $table->integer('warranty_month')->nullable();
            $table->tinyInteger('is_approved');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop foreign key
        Schema::table('request_equipments', function (Blueprint $table) {
            $table->dropForeign(['master_id']);
            $table->dropForeign(['inventory_id']);
            $table->dropForeign(['supplier_id']);
            $table->dropForeign(['brand_id']);
            $table->dropForeign(['unit_id']);
            $table->dropForeign(['inventory_type_id']);
        });

        Schema::dropIfExists('request_equipments');
    }
};
