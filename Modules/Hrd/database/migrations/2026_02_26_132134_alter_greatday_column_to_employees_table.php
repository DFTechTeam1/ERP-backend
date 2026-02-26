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
        Schema::table('employees', function (Blueprint $table) {
            $table->string('greatday_nationality')->nullable();
            $table->string('greatday_job_grade')->nullable();
            $table->string('greatday_marital_status')->nullable();
            $table->string('greatday_cost_center')->nullable();
            $table->string('greatday_employment_status')->nullable();
            $table->string('greatday_work_location')->nullable();
            $table->string('greatday_religion')->nullable();
            $table->string('greatday_timezone')->nullable();
            $table->string('greatday_shift_pattern')->nullable();
            $table->string('greatday_job_status')->nullable();
            $table->string('greatday_company')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('greatday_nationality');
            $table->dropColumn('greatday_job_grade');
            $table->dropColumn('greatday_marital_status');
            $table->dropColumn('greatday_cost_center');
            $table->dropColumn('greatday_employment_status');
            $table->dropColumn('greatday_work_location');
            $table->dropColumn('greatday_religion');
            $table->dropColumn('greatday_timezone');
            $table->dropColumn('greatday_shift_pattern');
            $table->dropColumn('greatday_job_status');
            $table->dropColumn('greatday_company');
        });
    }
};
