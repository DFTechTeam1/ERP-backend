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
        Schema::table('request_equipment_masters', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'on_process',
                'success',
                'cancel'
            ])->after('batch_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_equipment_masters', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
