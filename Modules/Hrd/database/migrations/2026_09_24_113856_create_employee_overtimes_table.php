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
        Schema::create('employee_overtimes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->nullable();
            $table->foreignId('employee_id')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete();
            $table->decimal('overtime_hours', 12, 4)->default(0);
            $table->string('remark');
            $table->foreignId('project_id')
                ->nullable()
                ->constrained('projects')
                ->nullOnDelete();
            $table->foreignId('task_id')
                ->nullable()
                ->constrained('project_tasks')
                ->nullOnDelete();
            $table->string('task_name')->comment('snapshot task name');
            $table->string('project_name')->comment('snapshot project name');
            $table->string('employee_name')->comment('snapshot employee name');
            $table->string('position_name')->comment('snapshot of current employee position name');
            $table->string('employee_number', 20)->comment('snapshot current employee id, refer to employees.employee_id');
            $table->string('overtime_date')->comment('from greatday');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_overtimes', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropForeign(['project_id']);
            $table->dropForeign(['task_id']);
        });
        Schema::dropIfExists('employee_overtimes');
    }
};
