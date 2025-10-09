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
        Schema::create('interactive_project_task_revisestates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('intr_project_tasks')->onDelete('cascade');
            $table->foreignId('work_state_id')->constrained('intr_project_task_pic_workstates')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->timestamp('assign_at');
            $table->timestamp('start_at')->nullable();
            $table->timestamp('finish_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop foreign
        Schema::table('interactive_project_task_revisestates', function (Blueprint $table) {
            $table->dropForeign(['task_id']);
            $table->dropForeign(['work_state_id']);
            $table->dropForeign(['employee_id']);
        });
        Schema::dropIfExists('interactive_project_task_revisestates');
    }
};
