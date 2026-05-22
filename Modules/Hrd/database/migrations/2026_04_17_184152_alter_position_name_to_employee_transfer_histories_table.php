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
            $table->string('to_position_name')->after('to_position_id');
            $table->string('from_position_name')->after('from_position_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_transfer_histories', function (Blueprint $table) {
            $table->dropColumn('to_position_name');
            $table->dropColumn('from_position_name');
        });
    }
};
