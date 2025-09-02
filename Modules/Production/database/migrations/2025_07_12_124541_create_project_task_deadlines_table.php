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
        Schema::create('project_task_deadlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_task_id')
                ->references('id')
                ->on('project_tasks')
                ->cascadeOnDelete();
            $table->foreignId('employee_id')
                ->references('id')
                ->on('employees');
            $table->timestamp('deadline');
            $table->timestamp('actual_finish_time')->nullable();
            $table->boolean('is_first_deadline')->default(false);
            $table->foreignId('due_reason')
                ->nullable()
                ->constrained(table: 'deadline_change_reasons', column: 'id');
            $table->foreignId('updated_by')
                ->references('id')
                ->on('users');
            $table->string('custom_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_task_deadlines', function (Blueprint $table) {
            $table->dropForeign(['project_task_id']);
            $table->dropForeign(['employee_id']);
            $table->dropForeign(['due_reason']);
            $table->dropForeign(['updated_by']);
        });

        Schema::dropIfExists('project_task_deadlines');
    }
};
