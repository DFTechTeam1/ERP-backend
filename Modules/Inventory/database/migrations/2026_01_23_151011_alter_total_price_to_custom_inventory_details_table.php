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
        Schema::table('custom_inventory_details', function (Blueprint $table) {
            $table->float('price')->change();
            $table->float('total_price')->nullable();

            // Renama qty to quantity
            $table->renameColumn('qty', 'quantity');

            $table->string('code')->nullable();
            $table->string('barcode')->nullable();
            $table->uuid('uid')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_inventory_details', function (Blueprint $table) {
            $table->double('price')->change();
            $table->dropColumn('total_price');
            // Rename quantity back to qty
            $table->renameColumn('quantity', 'qty');
            $table->dropColumn('code');
            $table->dropColumn('barcode');
            $table->dropColumn('uid');
        });
    }
};
