<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

            // Renama qty to quantity
            $table->renameColumn('qty', 'quantity');

            $table->float('total_price')
                ->virtualAs('COALESCE(price, 0) * COALESCE(quantity, 0)');

            $table->string('code')->nullable();
            $table->string('barcode')->nullable();
            $table->uuid('uid')->nullable();
        });

        // Update existing records
        DB::statement('
            UPDATE custom_inventory_details 
            SET total_price = COALESCE(price, 0) * COALESCE(quantity, 0) 
            WHERE total_price IS NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop triggers first
        DB::unprepared('DROP TRIGGER IF EXISTS calculate_total_price_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS calculate_total_price_update');
        
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
