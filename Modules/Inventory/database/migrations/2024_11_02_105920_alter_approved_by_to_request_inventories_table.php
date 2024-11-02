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
        Schema::table('request_inventories', function (Blueprint $table) {
            $table->integer('approved_by')->nullable();
            $table->integer('rejected_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_inventories', function (Blueprint $table) {
            $table->dropColumn('approved_by');
            $table->dropColumn('rejected_by');
        });
    }
};
