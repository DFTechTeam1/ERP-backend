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
        Schema::table('employee_transfer_histories', function (Blueprint $table) {
            $table->string('to_boss_name');
            $table->bigInteger('to_boss_id');
            $table->string('from_boss_name');
            $table->bigInteger('from_boss_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_transfer_histories', function (Blueprint $table) {
            $table->dropColumn('to_boss_name');
            $table->dropColumn('to_boss_id');
            $table->dropColumn('from_boss_name');
            $table->dropColumn('from_boss_id');
        });
    }
};
