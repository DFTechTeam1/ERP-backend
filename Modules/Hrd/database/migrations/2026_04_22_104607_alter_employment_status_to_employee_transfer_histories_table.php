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
            $table->string('to_employment_status_name');
            $table->bigInteger('to_employment_status_id');
            $table->string('from_employment_status_name');
            $table->bigInteger('from_employment_status_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_transfer_histories', function (Blueprint $table) {
            $table->dropColumn('to_employment_status_name');
            $table->dropColumn('to_employment_status_id');
            $table->dropColumn('from_employment_status_name');
            $table->dropColumn('from_employment_status_id');
        });
    }
};
