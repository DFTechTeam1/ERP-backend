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
        Schema::table('employee_resigns', function (Blueprint $table) {
            $table->unsignedBigInteger('current_position_id')->nullable();
            $table->tinyInteger('current_employee_status')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_resigns', function (Blueprint $table) {
            $table->dropColumn('current_position_id');
            $table->dropColumn('current_employee_status');
        });
    }
};
