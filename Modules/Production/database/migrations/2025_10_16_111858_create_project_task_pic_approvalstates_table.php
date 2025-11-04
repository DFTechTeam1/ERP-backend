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
        Schema::create('project_task_pic_approvalstates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pic_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('task_id')->constrained('project_tasks')->onDelete('cascade');
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('work_state_id')->constrained('project_task_pic_workstates')->onDelete('cascade');
            $table->timestamp('started_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop foreign
        Schema::table('project_task_pic_approvalstates', function (Blueprint $table) {
            $table->dropForeign(['pic_id']);
            $table->dropForeign(['task_id']);
            $table->dropForeign(['project_id']);
            $table->dropForeign(['work_state_id']);
        });
        Schema::dropIfExists('project_task_pic_approvalstates');
    }
};
