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
            $table->dropColumn('inventory_code');
            $table->dropColumn('status');
            $table->dropColumn('qrcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->string('inventory_code', 20)->nullable();
            $table->string('qrcode')->nullable();
            $table->tinyInteger('status')
                ->nullable()
                ->comment('1 for in use, 2 for in repair, 3 for broke, 4 for disposal');
        });
    }
};
