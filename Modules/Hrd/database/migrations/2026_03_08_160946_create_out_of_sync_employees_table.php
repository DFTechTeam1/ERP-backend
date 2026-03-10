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
        Schema::create('out_of_sync_employees', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->string('employee_id');
            $table->string('greatday_employee_id')->unique();
            $table->string('position_code');
            $table->string('position_name');
            $table->string('employment_status', 100);
            $table->string('employment_status_code', 100);
            $table->timestamp('start_working_date')->nullable();
            $table->timestamp('end_working_date')->nullable();
            $table->bigInteger('company_id');
            $table->string('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('job_status', 100)->nullable();
            $table->string('work_location_code', 100)->nullable();
            $table->string('cost_center_code', 100)->nullable();
            $table->string('org_unit', 100)->nullable();
            $table->timestamp('employment_start_date')->nullable();
            $table->enum('status', ['synced', 'out_of_sync']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('out_of_sync_employees');
    }
};
